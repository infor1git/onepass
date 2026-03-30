<?php
/**
 * Classe Principal: Gerenciamento de Senhas (CRUD), Interceptação Segura e UI
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginOnepassPassword extends CommonDBTM {

    public $dohistory = true; 
    static $rightname = 'plugin_onepass'; // Base para o controle de acesso

    static function getTypeName($nb = 0) {
        return 'Senhas (OnePass)';
    }

    // Define o ícone que aparecerá no menu principal (chave)
    static function getIcon() {
        return 'fas fa-key';
    }

    /**
     * Define o nome que aparecerá no menu lateral
     */
    static function getMenuName() {
        return 'Senhas (OnePass)';
    }

    /**
     * Sobrescreve as validações de segurança padrão do GLPI
     * para forçar o uso dos nossos direitos granulares (Read/Create/Update/Delete)
     */
    public static function canView() {
        return Session::haveRight('plugin_onepass_read', 1);
    }

    public static function canCreate() {
        return Session::haveRight('plugin_onepass_create', 1);
    }

    public static function canUpdate() {
        return Session::haveRight('plugin_onepass_update', 1);
    }

    public static function canDelete() {
        return Session::haveRight('plugin_onepass_delete', 1);
    }

    /**
     * Desenha o Formulário de Cadastro e Edição da Senha
     */
    public function showForm($ID, array $options = []) {
        global $CFG_GLPI;

        // 1. Inicializa o formulário (o GLPI limpa os campos se for ID 0)
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // 2. A CORREÇÃO: Preenche o vínculo com os dados da URL *depois* do initForm
        if ($ID == 0) {
            $this->fields['itemtype'] = $_GET['itemtype'] ?? '';
            $this->fields['items_id'] = $_GET['items_id'] ?? 0;
        }

        echo "<tr class='tab_bg_1'>";
        echo "<td>Nomenclatura (Título)</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "name", ['required' => true]);
        echo "</td>";

        echo "<td>Nível de Segurança</td>";
        echo "<td>";
        $levels = [
            1 => 'Público (Visível a todos com leitura)',
            2 => 'Privado (Visível apenas para você)',
            3 => 'Confidencial (Requer permissão avançada)',
            4 => 'Estritamente Confidencial (Requer permissão máxima)'
        ];
        Dropdown::showFromArray('access_level', $levels, ['value' => $this->fields['access_level'] ?? 1]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>Usuário (Login)</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "username");
        echo "</td>";

        echo "<td>URL / Endereço IP</td>";
        echo "<td>";
        Html::autocompletionTextField($this, "url");
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>Tipo de Senha</td>";
        echo "<td>";
        Dropdown::show('PluginOnepassType', [
            'value' => $this->fields['plugin_onepass_types_id'] ?? 0,
            'name'  => 'plugin_onepass_types_id'
        ]);
        echo "</td>";

        echo "<td>Comentário / Observações</td>";
        echo "<td>";
        echo "<textarea name='comment' placeholder='Detalhes adicionais, comandos, chaves extras...' style='width: 100%; border-radius: 4px; border: 1px solid #ced4da; padding: 6px; resize: vertical;' rows='3'>".($this->fields['comment'] ?? '')."</textarea>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>Vincular a um Ativo</td>";
        echo "<td>";
        
        $types = ['Computer', 'NetworkEquipment', 'Software'];
        $current_itemtype = $this->fields['itemtype'] ?? '';
        $current_items_id = $this->fields['items_id'] ?? 0;

        // --- MODO SMART BADGE: Agora funciona tanto na criação quanto na edição! ---
        if (!empty($current_itemtype) && $current_items_id > 0) {
            $asset = getItemForItemtype($current_itemtype);
            if ($asset && $asset->getFromDB($current_items_id)) {
                $type_icons = [
                    'Computer'         => 'fas fa-desktop',
                    'NetworkEquipment' => 'fas fa-network-wired',
                    'Software'         => 'fas fa-compact-disc'
                ];
                $icon = $type_icons[$current_itemtype] ?? 'fas fa-box';
                $asset_name = $asset->getName();
                $asset_link = $asset->getLinkURL();
                $type_name  = $asset->getTypeName(1);

                echo "
                <div id='asset_view_mode' style='display: inline-flex; align-items: center; background: #f8f9fa; border: 1px solid #ced4da; padding: 6px 14px; border-radius: 6px;'>
                    <i class='$icon' style='color: #6c757d; margin-right: 10px; font-size: 1.1em;'></i>
                    <span style='color: #495057;'><strong>$type_name:</strong></span>&nbsp;
                    <a href='$asset_link' target='_blank' style='color: #0d6efd; text-decoration: none; font-weight: 500;'>$asset_name</a>
                    <i class='fas fa-pen onepass-action-icon icon-divider' id='btn_edit_asset' title='Alterar Vínculo' style='margin-left: 15px; cursor: pointer; color: #adb5bd;'></i>
                </div>
                
                <div id='asset_edit_mode' style='display: none; align-items: center;'>";
                
                Dropdown::showSelectItemFromItemtypes([
                    'itemtypes'        => $types,
                    'entity_restrict'  => -1,
                    'itemtype_name'    => 'itemtype',
                    'items_id_name'    => 'items_id',
                    'itemtype_default' => $current_itemtype,
                    'items_id_default' => $current_items_id
                ]);

                echo "<button type='button' id='btn_cancel_asset' class='btn btn-secondary' style='margin-left: 8px; padding: 4px 10px;' title='Cancelar'><i class='fas fa-times'></i></button>";
                echo "</div>";
                
                echo "<input type='hidden' id='hidden_itemtype' name='itemtype' value='$current_itemtype'>";
                echo "<input type='hidden' id='hidden_items_id' name='items_id' value='$current_items_id'>";
            }
        } else {
            // Se abrir direto pelo painel de Administração, mostra o Dropdown vazio
            Dropdown::showSelectItemFromItemtypes([
                'itemtypes'        => $types,
                'entity_restrict'  => -1,
                'itemtype_name'    => 'itemtype',
                'items_id_name'    => 'items_id',
                'itemtype_default' => $current_itemtype,
                'items_id_default' => $current_items_id
            ]);
        }
        echo "</td>";

        echo "<td>Senha de Acesso</td>";
        echo "<td>";
        if ($ID > 0) {
            // --- MODO EDIÇÃO / VISUALIZAÇÃO DE SENHA ---
            $ajax_url = $CFG_GLPI['root_doc'] . "/plugins/onepass/ajax/reveal.php";
            
            echo "
            <style>
                .onepass-pwd-wrapper { display: inline-flex; align-items: center; background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 6px; padding: 8px 14px; min-width: 320px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); }
                .onepass-pwd-mask { flex-grow: 1; font-family: monospace; font-size: 1.2em; color: #495057; letter-spacing: 2px; user-select: none; }
                .onepass-action-icon { cursor: pointer; color: #adb5bd; font-size: 1.1em; margin-left: 14px; transition: all 0.2s ease-in-out; }
                .onepass-action-icon:hover { color: #495057; transform: scale(1.1); }
                .icon-divider { border-left: 1px solid #dee2e6; padding-left: 14px; margin-left: 4px; }
            </style>

            <div id='pwd_view_mode' class='onepass-pwd-wrapper'>
                <span id='revealed_pwd' class='onepass-pwd-mask'>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                <i class='fas fa-eye onepass-action-icon' id='btn_reveal_pwd' title='Revelar / Ocultar'></i>
                <i class='fas fa-copy onepass-action-icon' id='btn_copy_pwd' title='Copiar para a área de transferência'></i>
                <i class='fas fa-pen onepass-action-icon icon-divider' id='btn_edit_pwd' title='Alterar Senha'></i>
            </div>

            <div id='pwd_edit_mode' style='display: none; align-items: center;'>
                <input type='password' name='password_clear' id='input_password_clear' autocomplete='new-password' placeholder='Digite a nova senha...' style='width: 250px; border-radius: 4px; border: 1px solid #ced4da; padding: 6px 10px;'>
                <button type='button' id='btn_cancel_edit' class='btn btn-secondary' style='margin-left: 8px; padding: 6px 12px;' title='Cancelar Alteração'><i class='fas fa-times'></i></button>
            </div>
            ";

            echo "
            <script>
            $(function() {
                let revealTimeout;
                let isRevealed = false;
                const ajaxUrl = '{$ajax_url}';

                $(document).off('click', '#btn_copy_pwd, #btn_reveal_pwd, #btn_edit_pwd, #btn_cancel_edit, #btn_edit_asset, #btn_cancel_asset');

                function fetchPassword(action, callback) {
                    $.post(ajaxUrl, { id: {$ID}, action: action })
                    .done(function(data) {
                        if (data.success) callback(data.password);
                        else alert('Aviso OnePass: ' + (data.error || 'Erro desconhecido.'));
                    }).fail(function() { alert('OnePass Segurança: Erro de comunicação com o servidor.'); });
                }

                $(document).on('click', '#btn_copy_pwd', function() {
                    let \$btn = $(this);
                    fetchPassword('copy', function(pwd) {
                        navigator.clipboard.writeText(pwd).then(() => {
                            \$btn.removeClass('fa-copy').addClass('fa-check').css('color', '#198754');
                            setTimeout(() => { \$btn.removeClass('fa-check').addClass('fa-copy').css('color', ''); }, 2000);
                        });
                    });
                });

                $(document).on('click', '#btn_reveal_pwd', function() {
                    let \$btn = $(this);
                    let \$pwdSpan = $('#revealed_pwd');

                    if (isRevealed) {
                        \$pwdSpan.html('&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;').css('letter-spacing', '2px');
                        \$btn.removeClass('fa-eye-slash').addClass('fa-eye');
                        isRevealed = false;
                        clearTimeout(revealTimeout);
                    } else {
                        fetchPassword('reveal', function(pwd) {
                            \$pwdSpan.text(pwd).css('letter-spacing', '1px');
                            \$btn.removeClass('fa-eye').addClass('fa-eye-slash');
                            isRevealed = true;
                            clearTimeout(revealTimeout);
                            revealTimeout = setTimeout(() => {
                                \$pwdSpan.html('&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;').css('letter-spacing', '2px');
                                \$btn.removeClass('fa-eye-slash').addClass('fa-eye');
                                isRevealed = false;
                            }, 10000);
                        });
                    }
                });

                $(document).on('click', '#btn_edit_pwd', function() {
                    $('#pwd_view_mode').hide();
                    $('#pwd_edit_mode').css('display', 'flex');
                    $('#input_password_clear').focus();
                });
                $(document).on('click', '#btn_cancel_edit', function() {
                    $('#input_password_clear').val('');
                    $('#pwd_edit_mode').hide();
                    $('#pwd_view_mode').css('display', 'inline-flex');
                });
                $(document).on('click', '#btn_edit_asset', function() {
                    $('#asset_view_mode').hide();
                    $('#asset_edit_mode').css('display', 'flex');
                    $('#hidden_itemtype, #hidden_items_id').remove();
                });
                $(document).on('click', '#btn_cancel_asset', function() {
                    location.reload(); 
                });
            });
            </script>
            ";

        } else {
            // --- MODO CRIAÇÃO ---
            echo "<input type='password' name='password_clear' value='' autocomplete='new-password' required style='width: 100%; max-width: 320px; border-radius: 4px; border: 1px solid #ced4da; padding: 6px 10px;'>";
            
            // Adicionado script para lidar com a edição do ativo no modo de criação
            echo "
            <script>
            $(function() {
                $(document).off('click', '#btn_edit_asset, #btn_cancel_asset');
                $(document).on('click', '#btn_edit_asset', function() {
                    $('#asset_view_mode').hide();
                    $('#asset_edit_mode').css('display', 'flex');
                    $('#hidden_itemtype, #hidden_items_id').remove();
                });
                $(document).on('click', '#btn_cancel_asset', function() {
                    location.reload(); 
                });
            });
            </script>
            ";
        }
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
        return true;
    }

    /**
     * Motor de Busca do GLPI: Define todas as colunas disponíveis
     */
    public function rawSearchOptions() {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => 'Características Básicas'];

        $tab[] = ['id' => '1', 'table' => $this->getTable(), 'field' => 'name', 'name' => 'Nome da Credencial', 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => '2', 'table' => $this->getTable(), 'field' => 'username', 'name' => 'Usuário (Login)', 'datatype' => 'string', 'massiveaction' => false];
        $tab[] = ['id' => '3', 'table' => $this->getTable(), 'field' => 'access_level', 'name' => 'Nível de Acesso', 'datatype' => 'specific', 'massiveaction' => false];
        $tab[] = ['id' => '4', 'table' => 'glpi_plugin_onepass_types', 'field' => 'name', 'name' => 'Tipo', 'datatype' => 'dropdown', 'massiveaction' => false];
        $tab[] = ['id' => '5', 'table' => $this->getTable(), 'field' => 'comment', 'name' => 'Comentário', 'datatype' => 'text', 'massiveaction' => false];
        $tab[] = ['id' => '6', 'table' => $this->getTable(), 'field' => 'url', 'name' => 'URL / Endereço IP', 'datatype' => 'string', 'massiveaction' => false];
        
        // Coluna Polimórfica Nativa do GLPI (Ativo Vinculado)
        $tab[] = ['id' => '7', 'table' => $this->getTable(), 'field' => 'items_id', 'name' => 'Ativo Vinculado', 'datatype' => 'itemlink', 'itemlink_type' => 'itemtype', 'massiveaction' => false];

        return $tab;
    }

    /**
     * Força a "Visão Padrão" da tela principal com as colunas exigidas
     */
    public static function getDefaultSearchRequest() {
        return [
            'sort'       => 1, // Ordena pelo ID 1 (Nome)
            'desc'       => 0, // Crescente
            'field'      => [1, 6, 4, 7], // Nome, URL, Tipo, Ativo Vinculado
            'searchtype' => ['contains', 'contains', 'contains', 'contains'],
            'contains'   => ['', '', '', '']
        ];
    }

    /**
     * Traduz os IDs numéricos de nível de acesso para texto na tela de busca
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = []) {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        
        switch ($field) {
            case 'access_level':
                $levels = [
                    1 => 'Público',
                    2 => 'Privado',
                    3 => 'Confidencial',
                    4 => 'Estritamente Confidencial'
                ];
                return $levels[$values[$field]] ?? 'Desconhecido';
        }
        
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Helper para buscar todos os itens (Computadores, Redes, etc.) vinculados a um Chamado
     */
    private static function getLinkedItemsForTicket(int $tickets_id): array {
        global $DB;
        $items = [];
        $iterator = $DB->request([
            'SELECT' => ['itemtype', 'items_id'],
            'FROM'   => 'glpi_items_tickets',
            'WHERE'  => ['tickets_id' => $tickets_id]
        ]);
        foreach ($iterator as $row) {
            $items[] = $row;
        }
        return $items;
    }

    /**
     * Define o nome da aba e o contador de senhas
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        $allowed_types = ['Ticket', 'Computer', 'NetworkEquipment', 'Software'];
        if (!in_array($item->getType(), $allowed_types) || !Session::haveRight('plugin_onepass_read', 1)) {
            return '';
        }

        global $DB;
        $count = 0;

        // Lógica de Herança: Se for Ticket, conta as senhas dos ativos vinculados a ele
        if ($item->getType() == 'Ticket') {
            $linked_items = self::getLinkedItemsForTicket($item->getID());
            if (count($linked_items) > 0) {
                // Monta a condição OR para contar senhas de qualquer um dos ativos vinculados
                $or_conditions = [];
                foreach ($linked_items as $link) {
                    $or_conditions[] = ['itemtype' => $link['itemtype'], 'items_id' => $link['items_id']];
                }
                $count = countElementsInTable($this->getTable(), ['OR' => $or_conditions]);
            }
        } else {
            // Se for um Computador/Ativo direto, conta normalmente
            $count = countElementsInTable($this->getTable(), ['itemtype' => $item->getType(), 'items_id' => $item->getID()]);
        }

        // Só mostra a aba no ticket se houver alguma senha nos ativos vinculados (opcional, mas deixa a interface limpa)
        if ($item->getType() == 'Ticket' && $count == 0) {
            return "OnePass <sup class='tab_nb'>0</sup>"; 
        }

        return "OnePass <sup class='tab_nb'>$count</sup>";
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        self::showListForItem($item);
        return true;
    }

    /**
     * Lista as senhas aplicando a lógica de herança e adicionando o Cofre Inline
     */
    public static function showListForItem(CommonGLPI $item) {
        global $DB, $CFG_GLPI;

        $current_user_id = Session::getLoginUserID();
        $can_view_confidential = Session::haveRight('plugin_onepass_level_confidential', 1);
        $can_view_strict       = Session::haveRight('plugin_onepass_level_strict', 1);

        $allowed_levels = [1];
        if ($can_view_confidential) $allowed_levels[] = 3;
        if ($can_view_strict) $allowed_levels[] = 4;

        $where_condition = [];

        if ($item->getType() == 'Ticket') {
            // Lógica de herança para chamados (busca os ativos do ticket)
            $items = [];
            $iterator = $DB->request(['SELECT' => ['itemtype', 'items_id'], 'FROM' => 'glpi_items_tickets', 'WHERE' => ['tickets_id' => $item->getID()]]);
            foreach ($iterator as $row) { $items[] = $row; }
            
            if (empty($items)) {
                echo "<div class='center'><table class='tab_cadre_fixehov'><tr class='tab_bg_2'><td class='center'>Nenhum ativo vinculado a este chamado. Vincule um equipamento para ver suas senhas.</td></tr></table></div>";
                return;
            }
            $or_items = [];
            foreach ($items as $link) { $or_items[] = ['itemtype' => $link['itemtype'], 'items_id' => $link['items_id']]; }
            $where_condition['OR'] = $or_items;
        } else {
            $where_condition['itemtype'] = $item->getType();
            $where_condition['items_id'] = $item->getID();
        }

        // Trava de segurança no banco
        $where_condition = ['AND' => [ $where_condition, ['OR' => [ 'access_level' => $allowed_levels, ['access_level' => 2, 'users_id' => $current_user_id] ]] ]];

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_onepass_passwords',
            'WHERE' => $where_condition,
            'ORDER' => 'name ASC'
        ]);

        echo "<div class='center'>";
        
        // CSS embutido para os botões da tabela não quebrarem o layout
        echo "<style>
            .inline-action-icon { cursor: pointer; color: #adb5bd; margin-left: 12px; font-size: 1.1em; transition: 0.2s; }
            .inline-action-icon:hover { color: #495057; transform: scale(1.1); }
            .inline-action-success { color: #198754 !important; }
        </style>";

        if (Session::haveRight('plugin_onepass_create', 1) && $item->getType() != 'Ticket') {
            echo "<table class='tab_cadre_fixe'><tr><th class='center'>";
            echo "<a href='".Toolbox::getItemTypeFormURL('PluginOnepassPassword')."?itemtype=".$item->getType()."&items_id=".$item->getID()."' class='btn btn-primary'><i class='fas fa-plus'></i> Adicionar Nova Senha</a>";
            echo "</th></tr></table><br>";
        } elseif ($item->getType() == 'Ticket') {
            echo "<p><em>Senhas listadas pertencem aos ativos vinculados a este chamado.</em></p>";
        }

        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='tab_bg_1'>";
        echo "<th>Ativo Vinculado</th>";
        echo "<th>Nome</th>";
        echo "<th>Nível de Acesso</th>";
        echo "<th>Usuário (Login)</th>";
        echo "<th>Comentários</th>";
        echo "<th>Ações (Cofre)</th>";
        echo "</tr>";

        if (count($iterator) == 0) {
            echo "<tr class='tab_bg_2'><td colspan='6' class='center'>Nenhuma credencial disponível para o seu nível de acesso.</td></tr>";
        } else {
            foreach ($iterator as $data) {
                $level_labels = [1 => 'Público', 2 => 'Privado', 3 => 'Confidencial', 4 => 'Estritamente Confidencial'];
                $nivel = $level_labels[$data['access_level']] ?? 'Desconhecido';
                
                $asset = getItemForItemtype($data['itemtype']);
                $asset->getFromDB($data['items_id']);
                $asset_name = "<a href='".$asset->getLinkURL()."' target='_blank'>".$asset->getTypeName(1)." - ".$asset->getName()."</a>";

                // Limita o comentário a 60 caracteres para não quebrar a tabela visualmente
                $comentario_curto = mb_substr($data['comment'] ?? '', 0, 60);
                if (mb_strlen($data['comment'] ?? '') > 60) $comentario_curto .= '...';

                echo "<tr class='tab_bg_2'>";
                echo "<td>" . $asset_name . "</td>";
                echo "<td><strong>" . $data['name'] . "</strong></td>";
                echo "<td>" . $nivel . "</td>";
                echo "<td>" . ($data['username'] ?: '-') . "</td>";
                echo "<td title='".htmlentities($data['comment'] ?? '')."'>" . ($comentario_curto ?: '-') . "</td>";
                
                // MÁGICA: O Cofre Inline minimalista diretamente na coluna de ações
                echo "<td>";
                echo "<div style='display: inline-flex; align-items: center; background: #fff; border: 1px solid #ced4da; border-radius: 4px; padding: 4px 10px; min-width: 170px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);'>";
                echo "<span id='inline_pwd_{$data['id']}' style='flex-grow: 1; font-family: monospace; letter-spacing: 2px; color: #495057;'>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>";
                echo "<i class='fas fa-eye inline-action-icon btn-reveal-inline' data-id='{$data['id']}' title='Revelar/Ocultar'></i>";
                echo "<i class='fas fa-copy inline-action-icon btn-copy-inline' data-id='{$data['id']}' title='Copiar Senha'></i>";
                echo "<a href='".Toolbox::getItemTypeFormURL('PluginOnepassPassword')."?id=".$data['id']."' class='fas fa-external-link-alt inline-action-icon' title='Abrir/Editar Cadastro' style='text-decoration: none;'></a>";
                echo "</div>";
                echo "</td>";
                echo "</tr>";
            }
        }
        echo "</table></div>";

        // JavaScript inteligente que gerencia múltiplos cofres na mesma tela
        $ajax_url = $CFG_GLPI['root_doc'] . "/plugins/onepass/ajax/reveal.php";
        echo "
        <script>
        $(function() {
            let revealTimeouts = {};

            $(document).off('click', '.btn-reveal-inline').on('click', '.btn-reveal-inline', function() {
                let \$btn = $(this);
                let id = \$btn.data('id');
                let \$pwdSpan = $('#inline_pwd_' + id);

                if (\$btn.hasClass('fa-eye-slash')) {
                    \$pwdSpan.html('&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;').css('letter-spacing', '2px');
                    \$btn.removeClass('fa-eye-slash').addClass('fa-eye');
                    clearTimeout(revealTimeouts[id]);
                } else {
                    $.post('{$ajax_url}', { id: id, action: 'reveal' })
                    .done(function(data) {
                        if (data.success) {
                            \$pwdSpan.text(data.password).css('letter-spacing', '1px');
                            \$btn.removeClass('fa-eye').addClass('fa-eye-slash');
                            
                            clearTimeout(revealTimeouts[id]);
                            revealTimeouts[id] = setTimeout(() => {
                                \$pwdSpan.html('&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;').css('letter-spacing', '2px');
                                \$btn.removeClass('fa-eye-slash').addClass('fa-eye');
                            }, 10000);
                        } else {
                            alert('Aviso OnePass: ' + data.error);
                        }
                    }).fail(function() { alert('Erro de comunicação com o servidor.'); });
                }
            });

            $(document).off('click', '.btn-copy-inline').on('click', '.btn-copy-inline', function() {
                let \$btn = $(this);
                let id = \$btn.data('id');
                $.post('{$ajax_url}', { id: id, action: 'copy' })
                .done(function(data) {
                    if (data.success) {
                        navigator.clipboard.writeText(data.password).then(() => {
                            \$btn.removeClass('fa-copy').addClass('fa-check inline-action-success');
                            setTimeout(() => { \$btn.removeClass('fa-check inline-action-success').addClass('fa-copy'); }, 2000);
                        });
                    } else {
                        alert('Aviso OnePass: ' + data.error);
                    }
                });
            });
        });
        </script>
        ";
    }

    public function prepareInputForAdd($input) {
        if (isset($input['password_clear']) && !empty($input['password_clear'])) {
            $input['password_hash'] = PluginOnepassCrypto::encrypt($input['password_clear']);
            unset($input['password_clear']); 
        }
        $input['date_creation'] = $_SESSION['glpi_currenttime'];
        if (!isset($input['users_id'])) $input['users_id'] = Session::getLoginUserID();
        return $input;
    }

    public function prepareInputForUpdate($input) {
        if (isset($input['password_clear']) && !empty($input['password_clear'])) {
            $input['password_hash'] = PluginOnepassCrypto::encrypt($input['password_clear']);
            unset($input['password_clear']);
            self::logAudit($input['id'], 'modify');
        }
        $input['date_mod'] = $_SESSION['glpi_currenttime'];
        return $input;
    }

    public static function logAudit(int $password_id, string $action) {
        global $DB;
        $DB->insert('glpi_plugin_onepass_audits', [
            'plugin_onepass_passwords_id' => $password_id,
            'users_id'                    => Session::getLoginUserID(),
            'action'                      => $action,
            'ip_address'                  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent'                  => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'date_creation'               => $_SESSION['glpi_currenttime']
        ]);
    }
}