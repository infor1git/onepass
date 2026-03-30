<?php
/**
 * Classe de Perfis e Permissões do OnePass
 * Gerencia a matriz de direitos e níveis de acesso.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginOnepassProfile extends CommonGLPI {

    /**
     * Define as permissões customizadas do nosso plugin.
     */
    static function getRights() {
        return [
            'plugin_onepass_read'               => 'Ler Senhas',
            'plugin_onepass_create'             => 'Criar Senhas',
            'plugin_onepass_update'             => 'Atualizar Senhas',
            'plugin_onepass_delete'             => 'Deletar Senhas',
            'plugin_onepass_level_confidential' => 'Ver Confidenciais',
            'plugin_onepass_level_strict'       => 'Ver Estritamente Confidenciais'
        ];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            return 'OnePass';
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            self::showForm($item->getID());
        }
        return true;
    }

    /**
     * Renderiza o formulário de permissões com HTML seguro e compatível com GLPI 10
     */
    static function showForm($profiles_id) {
        global $DB;

        // Recupera os direitos atuais deste perfil
        $req = $DB->request('glpi_profilerights', [
            'profiles_id' => $profiles_id,
            'name'        => ['LIKE', 'plugin_onepass_%']
        ]);

        $rights = [];
        foreach ($req as $data) {
            $rights[$data['name']] = $data['rights'];
        }

        echo "<form method='post' action='" . ProfileRight::getFormURL() . "'>";
        echo "<table class='tab_cadre_fixe'>";
        
        echo "<tr class='tab_bg_1'><th colspan='2'>OnePass - Gerenciamento de Senhas</th></tr>";

        // Matriz de Acesso Básico (CRUD)
        echo "<tr class='tab_bg_2'>";
        echo "<td>Acesso Básico</td>";
        echo "<td>";
        self::renderCheckbox('plugin_onepass_read', 'Ler', $rights);
        self::renderCheckbox('plugin_onepass_create', 'Criar', $rights);
        self::renderCheckbox('plugin_onepass_update', 'Atualizar', $rights);
        self::renderCheckbox('plugin_onepass_delete', 'Deletar', $rights);
        echo "</td>";
        echo "</tr>";

        // Matriz de Nível de Confidencialidade
        echo "<tr class='tab_bg_1'><th colspan='2'>Níveis de Confidencialidade Permitidos</th></tr>";
        echo "<tr class='tab_bg_2'>";
        echo "<td>Acesso Avançado (Senhas Públicas e Privadas são padrão)</td>";
        echo "<td>";
        self::renderCheckbox('plugin_onepass_level_confidential', 'Pode ver: Confidencial', $rights);
        self::renderCheckbox('plugin_onepass_level_strict', 'Pode ver: Estritamente Confidencial', $rights);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='hidden' name='id' value='$profiles_id'>";
        echo "<input type='submit' name='update' value='Salvar' class='btn btn-primary'>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        Html::closeForm();
    }

    /**
     * Helper para gerar os checkboxes no formato exato que o GLPI espera receber no POST
     */
    private static function renderCheckbox($rightName, $label, $currentRights) {
        $checked = !empty($currentRights[$rightName]) ? "checked='checked'" : "";
        echo "<label style='margin-right: 20px; display: inline-block; cursor: pointer;'>";
        echo "<input type='hidden' name='_$rightName' value='0'>"; 
        echo "<input type='checkbox' name='_$rightName' value='1' $checked> " . $label;
        echo "</label>";
    }
}