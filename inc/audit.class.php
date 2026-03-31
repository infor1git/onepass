<?php
/**
 * Classe de Interface: Trilha de Auditoria e Logs Inalteráveis
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginOnepassAudit extends CommonGLPI {

    /**
     * Define o nome padrão do item
     */
    static function getTypeName($nb = 0) {
        return 'Auditoria de Acessos';
    }

    /**
     * Configura o nome da Aba e a "bolinha" com o contador de logs
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        // A aba só aparece dentro do formulário de Senha e apenas se estiver salva no banco (ID > 0)
        if ($item->getType() == 'PluginOnepassPassword' && $item->getID() > 0) {
            
            // Verifica se o usuário tem a permissão mínima de ler
            if (!Session::haveRight('plugin_onepass_read', 1)) {
                return '';
            }

            global $DB;
            $count = countElementsInTable('glpi_plugin_onepass_audits', ['plugin_onepass_passwords_id' => $item->getID()]);
            
            return "Auditoria de Acessos <sup class='tab_nb'>$count</sup>";
        }
        return '';
    }

    /**
     * Aciona o desenho do conteúdo da Aba
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'PluginOnepassPassword') {
            self::showListForPassword($item);
        }
        return true;
    }

    /**
     * Desenha a tabela de logs
     */
    public static function showListForPassword(PluginOnepassPassword $password) {
        global $DB;

        $ID = $password->getID();
        if (!$ID) return false;

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_onepass_audits',
            'WHERE' => ['plugin_onepass_passwords_id' => $ID],
            'ORDER' => 'date_creation DESC'
        ]);

        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='tab_bg_1'><th colspan='6'>Trilha Inalterável de Acessos (Compliance)</th></tr>";
        echo "<tr class='tab_bg_2'>";
        echo "<th>Data e Hora</th>";
        echo "<th>Usuário</th>";
        echo "<th>Ação Realizada</th>";
        echo "<th>E-mail Autorizado (2FA)</th>"; // Nova Coluna
        echo "<th>Endereço IP</th>";
        echo "<th>Dispositivo / Navegador</th>";
        echo "</tr>";

        if (count($iterator) == 0) {
            echo "<tr class='tab_bg_2'><td colspan='6' class='center'>Nenhum acesso registrado até o momento.</td></tr>";
        } else {
            foreach ($iterator as $data) {
                $user_name = getUserName($data['users_id']);
                
                $action_html = "";
                switch ($data['action']) {
                    case 'reveal': $action_html = "<span style='color: #0d6efd; font-weight: 500;'><i class='fas fa-eye'></i> Revelou a Senha</span>"; break;
                    case 'copy':   $action_html = "<span style='color: #198754; font-weight: 500;'><i class='fas fa-copy'></i> Copiou a Senha</span>"; break;
                    case 'modify': $action_html = "<span style='color: #fd7e14; font-weight: 500;'><i class='fas fa-pen'></i> Alterou a Senha</span>"; break;
                    case 'view':   $action_html = "<span style='color: #6c757d; font-weight: 500;'><i class='fas fa-search'></i> Visualizou Cadastro</span>"; break;
                    default:       $action_html = $data['action'];
                }

                $user_agent = mb_substr($data['user_agent'], 0, 45);
                if (mb_strlen($data['user_agent']) > 45) $user_agent .= '...';

                // Se houver um e-mail gravado pelo 2FA, coloca uma tag bonita nele
                $email_html = $data['two_fa_email'] ? "<span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 0.9em;'><i class='fas fa-envelope' style='color:#6c757d;'></i> " . htmlentities($data['two_fa_email']) . "</span>" : "<span style='color:#ccc;'>N/A</span>";

                echo "<tr class='tab_bg_2'>";
                echo "<td class='center'>" . Html::convDateTime($data['date_creation']) . "</td>";
                echo "<td><strong>" . $user_name . "</strong></td>";
                echo "<td>" . $action_html . "</td>";
                echo "<td>" . $email_html . "</td>";
                echo "<td>" . $data['ip_address'] . "</td>";
                echo "<td title='" . htmlentities($data['user_agent']) . "' style='color: #888; font-size: 0.9em;'>" . $user_agent . "</td>";
                echo "</tr>";
            }
        }
        echo "</table></div>";
    }
}