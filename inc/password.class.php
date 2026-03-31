<?php
/**
 * Classe Principal: Gerenciamento de Senhas (CRUD), Interceptação Segura e UI
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginOnepassPassword extends CommonDBTM {

    public $dohistory = true; 
    static $rightname = 'plugin_onepass';

    static function getTypeName($nb = 0) { return 'Senhas (OnePass)'; }
    static function getIcon() { return 'fas fa-key'; }
    static function getMenuName() { return 'Senhas (OnePass)'; }

    public static function canView() { return Session::haveRight('plugin_onepass_read', 1); }
    public static function canCreate() { return Session::haveRight('plugin_onepass_create', 1); }
    public static function canUpdate() { return Session::haveRight('plugin_onepass_update', 1); }
    public static function canDelete() { return Session::haveRight('plugin_onepass_delete', 1); }

    public function showForm($ID, array $options = []) {
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        if ($ID == 0) {
            $this->fields['itemtype'] = $_GET['itemtype'] ?? '';
            $this->fields['items_id'] = $_GET['items_id'] ?? 0;
        }

        echo "<tr class='tab_bg_1'><td>Nomenclatura (Título)</td><td>";
        Html::autocompletionTextField($this, "name", ['required' => true]);
        echo "</td><td>Nível de Segurança</td><td>";
        $levels = [1 => 'Público (Visível a todos com leitura)', 2 => 'Privado (Visível apenas para você)', 3 => 'Confidencial (Requer permissão avançada)', 4 => 'Estritamente Confidencial (Exige 2FA por E-mail)'];
        Dropdown::showFromArray('access_level', $levels, ['value' => $this->fields['access_level'] ?? 1]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Usuário (Login)</td><td>";
        Html::autocompletionTextField($this, "username");
        echo "</td><td>URL / Endereço IP</td><td>";
        Html::autocompletionTextField($this, "url");
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Tipo de Senha</td><td>";
        Dropdown::show('PluginOnepassType', ['value' => $this->fields['plugin_onepass_types_id'] ?? 0, 'name'  => 'plugin_onepass_types_id']);
        echo "</td><td>Comentário / Observações</td><td>";
        echo "<textarea name='comment' placeholder='Detalhes adicionais, comandos...' style='width: 100%; border-radius: 4px; border: 1px solid #ced4da; padding: 6px; resize: vertical;' rows='3'>".($this->fields['comment'] ?? '')."</textarea>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Vincular a um Ativo</td><td>";
        $types = ['Computer', 'NetworkEquipment', 'Software'];
        $current_itemtype = $this->fields['itemtype'] ?? '';
        $current_items_id = $this->fields['items_id'] ?? 0;

        if (!empty($current_itemtype) && $current_items_id > 0) {
            $asset = getItemForItemtype($current_itemtype);
            if ($asset && $asset->getFromDB($current_items_id)) {
                $type_icons = ['Computer' => 'fas fa-desktop', 'NetworkEquipment' => 'fas fa-network-wired', 'Software' => 'fas fa-compact-disc'];
                $icon = $type_icons[$current_itemtype] ?? 'fas fa-box';
                $asset_name = $asset->getName();
                $asset_link = $asset->getLinkURL();
                $type_name  = $asset->getTypeName(1);

                echo "<div id='asset_view_mode' style='display: inline-flex; align-items: center; background: #f8f9fa; border: 1px solid #ced4da; padding: 6px 14px; border-radius: 6px;'>
                        <i class='$icon' style='color: #6c757d; margin-right: 10px; font-size: 1.1em;'></i>
                        <span style='color: #495057;'><strong>$type_name:</strong></span>&nbsp;
                        <a href='$asset_link' target='_blank' style='color: #0d6efd; text-decoration: none; font-weight: 500;'>$asset_name</a>
                        <i class='fas fa-pen onepass-action-icon icon-divider' id='btn_edit_asset' title='Alterar Vínculo' style='margin-left: 15px; cursor: pointer; color: #adb5bd;'></i>
                      </div>
                      <div id='asset_edit_mode' style='display: none; align-items: center;'>";
                Dropdown::showSelectItemFromItemtypes(['itemtypes' => $types, 'entity_restrict' => -1, 'itemtype_name' => 'itemtype', 'items_id_name' => 'items_id', 'itemtype_default' => $current_itemtype, 'items_id_default' => $current_items_id]);
                echo "<button type='button' id='btn_cancel_asset' class='btn btn-secondary' style='margin-left: 8px; padding: 4px 10px;' title='Cancelar'><i class='fas fa-times'></i></button></div>";
                echo "<input type='hidden' id='hidden_itemtype' name='itemtype' value='$current_itemtype'><input type='hidden' id='hidden_items_id' name='items_id' value='$current_items_id'>";
            }
        } else {
            Dropdown::showSelectItemFromItemtypes(['itemtypes' => $types, 'entity_restrict' => -1, 'itemtype_name' => 'itemtype', 'items_id_name' => 'items_id', 'itemtype_default' => $current_itemtype, 'items_id_default' => $current_items_id]);
        }
        echo "</td>";

        echo "<td>Senha de Acesso</td><td>";
        if ($ID > 0) {
            $ajax_url = $CFG_GLPI['root_doc'] . "/plugins/onepass/ajax/reveal.php";
            
// Usando HEREDOC seguro para evitar conflitos de aspas no PHP
echo <<<JS
            <style>
                .onepass-pwd-wrapper { display: inline-flex; align-items: center; background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 6px; padding: 8px 14px; min-width: 320px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); }
                .onepass-pwd-mask { flex-grow: 1; font-family: monospace; font-size: 1.2em; color: #495057; letter-spacing: 2px; user-select: none; }
                .onepass-action-icon { cursor: pointer; color: #adb5bd; font-size: 1.1em; margin-left: 14px; transition: all 0.2s ease-in-out; }
                .onepass-action-icon:hover { color: #495057; transform: scale(1.1); }
                .icon-divider { border-left: 1px solid #dee2e6; padding-left: 14px; margin-left: 4px; }
            </style>

            <div id="pwd_view_mode" class="onepass-pwd-wrapper">
                <span id="revealed_pwd" class="onepass-pwd-mask">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                <i class="fas fa-eye onepass-action-icon" id="btn_reveal_pwd" title="Revelar / Ocultar"></i>
                <i class="fas fa-copy onepass-action-icon" id="btn_copy_pwd" title="Copiar para a área de transferência"></i>
                <i class="fas fa-pen onepass-action-icon icon-divider" id="btn_edit_pwd" title="Alterar Senha"></i>
            </div>

            <div id="pwd_edit_mode" style="display: none; align-items: center;">
                <input type="password" name="password_clear" id="input_password_clear" autocomplete="new-password" placeholder="Digite a nova senha..." style="width: 250px; border-radius: 4px; border: 1px solid #ced4da; padding: 6px 10px;">
                <button type="button" id="btn_cancel_edit" class="btn btn-secondary" style="margin-left: 8px; padding: 6px 12px;" title="Cancelar Alteração"><i class="fas fa-times"></i></button>
            </div>

            <script>
            $(function() {
                let revealTimeout;
                let isRevealed = false;
                const ajaxUrl = '{$ajax_url}';

                function secureCopyToClipboard(text) {
                    var textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try { document.execCommand("copy"); } catch (err) {}
                    document.body.removeChild(textArea);
                }

                function show2FAModal(email, callback) {
                    if ($("#onepass_2fa_modal").length === 0) {
                        let modalHtml = `
                        <div id="onepass_2fa_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter: blur(3px);">
                            <div style="background:#fff; border-radius:8px; width:400px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.2); font-family:sans-serif;">
                                <h3 style="margin-top:0; color:#dc3545; font-size:20px; text-align:center;"><i class="fas fa-user-lock"></i> Autenticação 2FA Exigida</h3>
                                <p style="color:#495057; text-align:center;">Enviamos um código de segurança de 6 dígitos para o e-mail abaixo:</p>
                                <p style="text-align:center; font-size:1.1em; color:#0d6efd;"><strong id="onepass_2fa_email"></strong></p>
                                <div style="margin:20px 0;">
                                    <input type="text" id="onepass_2fa_input" maxlength="6" style="width:100%; padding:15px; font-size:2em; text-align:center; letter-spacing:10px; border:2px solid #ced4da; border-radius:6px; background:#f8f9fa;" placeholder="000000" autocomplete="off">
                                </div>
                                <div style="text-align:center; margin-top: 20px;">
                                    <button type="button" class="btn btn-secondary" id="onepass_2fa_cancel" style="margin-right:10px; min-width:120px;">Cancelar</button>
                                    <button type="button" class="btn btn-primary" id="onepass_2fa_confirm" style="min-width:120px;">Autenticar</button>
                                </div>
                            </div>
                        </div>`;
                        $("body").append(modalHtml);
                    }
                    $("#onepass_2fa_email").text(email);
                    $("#onepass_2fa_input").val("");
                    $("#onepass_2fa_modal").css("display", "flex");
                    $("#onepass_2fa_input").focus();

                    $("#onepass_2fa_confirm").off("click").on("click", function() {
                        let code = $("#onepass_2fa_input").val();
                        if(code.length > 0) {
                            $("#onepass_2fa_modal").css("display", "none");
                            callback(code);
                        }
                    });

                    $("#onepass_2fa_cancel").off("click").on("click", function() {
                        $("#onepass_2fa_modal").css("display", "none");
                    });
                    
                    $("#onepass_2fa_input").off("keypress").on("keypress", function(e) {
                        if(e.which === 13) $("#onepass_2fa_confirm").click();
                    });
                }

                function fetchPassword(action, callback, otp_code = "") {
                    let postData = { id: {$ID}, action: action };
                    if (otp_code !== "") postData.otp = otp_code;

                    $.post(ajaxUrl, postData)
                    .done(function(data) {
                        if (data.require_2fa) {
                            show2FAModal(data.email, function(code) {
                                fetchPassword(action, callback, code.trim());
                            });
                        } else if (data.success) {
                            callback(data.password);
                        } else {
                            alert("Aviso OnePass: " + (data.error || "Erro desconhecido."));
                        }
                    }).fail(function() { alert("OnePass Segurança: Erro de comunicação com o servidor."); });
                }

                $(document).off("click", "#btn_copy_pwd").on("click", "#btn_copy_pwd", function() {
                    let btn = $(this);
                    fetchPassword("copy", function(pwd) {
                        secureCopyToClipboard(pwd);
                        btn.removeClass("fa-copy").addClass("fa-check").css("color", "#198754");
                        setTimeout(() => { btn.removeClass("fa-check").addClass("fa-copy").css("color", ""); }, 2000);
                    });
                });

                $(document).off("click", "#btn_reveal_pwd").on("click", "#btn_reveal_pwd", function() {
                    let btn = $(this);
                    let pwdSpan = $("#revealed_pwd");

                    if (isRevealed) {
                        pwdSpan.html("&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;").css("letter-spacing", "2px");
                        btn.removeClass("fa-eye-slash").addClass("fa-eye");
                        isRevealed = false;
                        clearTimeout(revealTimeout);
                    } else {
                        fetchPassword("reveal", function(pwd) {
                            pwdSpan.text(pwd).css("letter-spacing", "1px");
                            btn.removeClass("fa-eye").addClass("fa-eye-slash");
                            isRevealed = true;
                            clearTimeout(revealTimeout);
                            revealTimeout = setTimeout(() => {
                                pwdSpan.html("&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;").css("letter-spacing", "2px");
                                btn.removeClass("fa-eye-slash").addClass("fa-eye");
                                isRevealed = false;
                            }, 10000);
                        });
                    }
                });

                $(document).off("click", "#btn_edit_pwd").on("click", "#btn_edit_pwd", function() { $("#pwd_view_mode").hide(); $("#pwd_edit_mode").css("display", "flex"); $("#input_password_clear").focus(); });
                $(document).off("click", "#btn_cancel_edit").on("click", "#btn_cancel_edit", function() { $("#input_password_clear").val(""); $("#pwd_edit_mode").hide(); $("#pwd_view_mode").css("display", "inline-flex"); });
                $(document).off("click", "#btn_edit_asset").on("click", "#btn_edit_asset", function() { $("#asset_view_mode").hide(); $("#asset_edit_mode").css("display", "flex"); $("#hidden_itemtype, #hidden_items_id").remove(); });
                $(document).off("click", "#btn_cancel_asset").on("click", "#btn_cancel_asset", function() { location.reload(); });
            });
            </script>
JS;

        } else {
            echo "<input type='password' name='password_clear' value='' autocomplete='new-password' required style='width: 100%; max-width: 320px; border-radius: 4px; border: 1px solid #ced4da; padding: 6px 10px;'>";
            
echo <<<JS
            <script>
            $(function() { 
                $(document).off("click", "#btn_edit_asset, #btn_cancel_asset"); 
                $(document).on("click", "#btn_edit_asset", function() { 
                    $("#asset_view_mode").hide(); $("#asset_edit_mode").css("display", "flex"); $("#hidden_itemtype, #hidden_items_id").remove(); 
                }); 
                $(document).on("click", "#btn_cancel_asset", function() { location.reload(); }); 
            });
            </script>
JS;

        }
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    public function rawSearchOptions() {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => 'Características Básicas'];
        $tab[] = ['id' => '1', 'table' => $this->getTable(), 'field' => 'name', 'name' => 'Nome da Credencial', 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => '2', 'table' => $this->getTable(), 'field' => 'username', 'name' => 'Usuário (Login)', 'datatype' => 'string', 'massiveaction' => false];
        $tab[] = ['id' => '3', 'table' => $this->getTable(), 'field' => 'access_level', 'name' => 'Nível de Acesso', 'datatype' => 'specific', 'massiveaction' => false];
        $tab[] = ['id' => '4', 'table' => 'glpi_plugin_onepass_types', 'field' => 'name', 'name' => 'Tipo', 'datatype' => 'dropdown', 'massiveaction' => false];
        $tab[] = ['id' => '5', 'table' => $this->getTable(), 'field' => 'comment', 'name' => 'Comentário', 'datatype' => 'text', 'massiveaction' => false];
        $tab[] = ['id' => '6', 'table' => $this->getTable(), 'field' => 'url', 'name' => 'URL / Endereço IP', 'datatype' => 'string', 'massiveaction' => false];
        $tab[] = ['id' => '7', 'table' => $this->getTable(), 'field' => 'items_id', 'name' => 'Ativo Vinculado', 'datatype' => 'itemlink', 'itemlink_type' => 'itemtype', 'massiveaction' => false];
        return $tab;
    }

    public static function getDefaultSearchRequest() {
        return ['sort' => 1, 'desc' => 0, 'field' => [1, 6, 4, 7], 'searchtype' => ['contains', 'contains', 'contains', 'contains'], 'contains' => ['', '', '', '']];
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = []) {
        if (!is_array($values)) $values = [$field => $values];
        if ($field === 'access_level') {
            $levels = [1 => 'Público', 2 => 'Privado', 3 => 'Confidencial', 4 => 'Estritamente Confidencial'];
            return $levels[$values[$field]] ?? 'Desconhecido';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    private static function getLinkedItemsForTicket(int $tickets_id): array {
        global $DB;
        $items = [];
        $iterator = $DB->request(['SELECT' => ['itemtype', 'items_id'], 'FROM' => 'glpi_items_tickets', 'WHERE' => ['tickets_id' => $tickets_id]]);
        foreach ($iterator as $row) { $items[] = $row; }
        return $items;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        $allowed_types = ['Ticket', 'Computer', 'NetworkEquipment', 'Software'];
        if (!in_array($item->getType(), $allowed_types) || !Session::haveRight('plugin_onepass_read', 1)) return '';

        global $DB;
        $count = 0;
        if ($item->getType() == 'Ticket') {
            $linked_items = self::getLinkedItemsForTicket($item->getID());
            if (count($linked_items) > 0) {
                $or_conditions = [];
                foreach ($linked_items as $link) { $or_conditions[] = ['itemtype' => $link['itemtype'], 'items_id' => $link['items_id']]; }
                $count = countElementsInTable($this->getTable(), ['OR' => $or_conditions]);
            }
        } else {
            $count = countElementsInTable($this->getTable(), ['itemtype' => $item->getType(), 'items_id' => $item->getID()]);
        }
        if ($item->getType() == 'Ticket' && $count == 0) return "OnePass <sup class='tab_nb'>0</sup>"; 
        return "OnePass <sup class='tab_nb'>$count</sup>";
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        self::showListForItem($item);
        return true;
    }

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
            $items = [];
            $iterator = $DB->request(['SELECT' => ['itemtype', 'items_id'], 'FROM' => 'glpi_items_tickets', 'WHERE' => ['tickets_id' => $item->getID()]]);
            foreach ($iterator as $row) { $items[] = $row; }
            if (empty($items)) { echo "<div class='center'><table class='tab_cadre_fixehov'><tr class='tab_bg_2'><td class='center'>Nenhum ativo vinculado a este chamado.</td></tr></table></div>"; return; }
            $or_items = [];
            foreach ($items as $link) { $or_items[] = ['itemtype' => $link['itemtype'], 'items_id' => $link['items_id']]; }
            $where_condition['OR'] = $or_items;
        } else {
            $where_condition['itemtype'] = $item->getType();
            $where_condition['items_id'] = $item->getID();
        }

        $where_condition = ['AND' => [ $where_condition, ['OR' => [ 'access_level' => $allowed_levels, ['access_level' => 2, 'users_id' => $current_user_id] ]] ]];
        $iterator = $DB->request(['FROM' => 'glpi_plugin_onepass_passwords', 'WHERE' => $where_condition, 'ORDER' => 'name ASC']);

        echo "<div class='center'>";
        echo "<style>.inline-action-icon { cursor: pointer; color: #adb5bd; margin-left: 12px; font-size: 1.1em; transition: 0.2s; } .inline-action-icon:hover { color: #495057; transform: scale(1.1); } .inline-action-success { color: #198754 !important; }</style>";

        if (Session::haveRight('plugin_onepass_create', 1) && $item->getType() != 'Ticket') {
            echo "<table class='tab_cadre_fixe'><tr><th class='center'><a href='".Toolbox::getItemTypeFormURL('PluginOnepassPassword')."?itemtype=".$item->getType()."&items_id=".$item->getID()."' class='btn btn-primary'><i class='fas fa-plus'></i> Adicionar Nova Senha</a></th></tr></table><br>";
        }

        echo "<table class='tab_cadre_fixehov'><tr class='tab_bg_1'><th>Ativo Vinculado</th><th>Nome</th><th>Nível de Acesso</th><th>Usuário (Login)</th><th>Comentários</th><th>Ações (Cofre)</th></tr>";

        if (count($iterator) == 0) {
            echo "<tr class='tab_bg_2'><td colspan='6' class='center'>Nenhuma credencial disponível para o seu nível de acesso.</td></tr>";
        } else {
            foreach ($iterator as $data) {
                $level_labels = [1 => 'Público', 2 => 'Privado', 3 => 'Confidencial', 4 => 'Estritamente Confidencial'];
                $nivel = $level_labels[$data['access_level']] ?? 'Desconhecido';
                $asset = getItemForItemtype($data['itemtype']);
                $asset->getFromDB($data['items_id']);
                $asset_name = "<a href='".$asset->getLinkURL()."' target='_blank'>".$asset->getTypeName(1)." - ".$asset->getName()."</a>";
                $comentario_curto = mb_substr($data['comment'] ?? '', 0, 60);
                if (mb_strlen($data['comment'] ?? '') > 60) $comentario_curto .= '...';

                echo "<tr class='tab_bg_2'><td>" . $asset_name . "</td><td><strong>" . $data['name'] . "</strong></td><td>" . $nivel . "</td><td>" . ($data['username'] ?: '-') . "</td><td title='".htmlentities($data['comment'] ?? '')."'>" . ($comentario_curto ?: '-') . "</td>";
                echo "<td><div style='display: inline-flex; align-items: center; background: #fff; border: 1px solid #ced4da; border-radius: 4px; padding: 4px 10px; min-width: 170px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);'>
                      <span id='inline_pwd_{$data['id']}' style='flex-grow: 1; font-family: monospace; letter-spacing: 2px; color: #495057;'>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                      <i class='fas fa-eye inline-action-icon btn-reveal-inline' data-id='{$data['id']}' title='Revelar/Ocultar'></i>
                      <i class='fas fa-copy inline-action-icon btn-copy-inline' data-id='{$data['id']}' title='Copiar Senha'></i>
                      <a href='".Toolbox::getItemTypeFormURL('PluginOnepassPassword')."?id=".$data['id']."' class='fas fa-external-link-alt inline-action-icon' title='Abrir/Editar Cadastro' style='text-decoration: none;'></a>
                      </div></td></tr>";
            }
        }
        echo "</table></div>";

        $ajax_url = $CFG_GLPI['root_doc'] . "/plugins/onepass/ajax/reveal.php";
        
// Usando HEREDOC seguro
echo <<<JS
        <script>
        $(function() {
            let revealTimeouts = {};

            function secureCopyToClipboard(text) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try { document.execCommand("copy"); } catch (err) {}
                document.body.removeChild(textArea);
            }

            function show2FAModalList(email, callback) {
                if ($("#onepass_2fa_modal").length === 0) {
                    let modalHtml = `<div id="onepass_2fa_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter: blur(3px);">
                        <div style="background:#fff; border-radius:8px; width:400px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.2); font-family:sans-serif;">
                            <h3 style="margin-top:0; color:#dc3545; font-size:20px; text-align:center;"><i class="fas fa-user-lock"></i> Autenticação 2FA Exigida</h3>
                            <p style="color:#495057; text-align:center;">Enviamos um código de segurança de 6 dígitos para o e-mail abaixo:</p>
                            <p style="text-align:center; font-size:1.1em; color:#0d6efd;"><strong id="onepass_2fa_email"></strong></p>
                            <div style="margin:20px 0;">
                                <input type="text" id="onepass_2fa_input" maxlength="6" style="width:100%; padding:15px; font-size:2em; text-align:center; letter-spacing:10px; border:2px solid #ced4da; border-radius:6px; background:#f8f9fa;" placeholder="000000" autocomplete="off">
                            </div>
                            <div style="text-align:center; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" id="onepass_2fa_cancel" style="margin-right:10px; min-width:120px;">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="onepass_2fa_confirm" style="min-width:120px;">Autenticar</button>
                            </div>
                        </div>
                    </div>`;
                    $("body").append(modalHtml);
                }
                $("#onepass_2fa_email").text(email);
                $("#onepass_2fa_input").val("");
                $("#onepass_2fa_modal").css("display", "flex");
                $("#onepass_2fa_input").focus();

                $("#onepass_2fa_confirm").off("click").on("click", function() {
                    let code = $("#onepass_2fa_input").val();
                    if(code.length > 0) {
                        $("#onepass_2fa_modal").css("display", "none");
                        callback(code);
                    }
                });

                $("#onepass_2fa_cancel").off("click").on("click", function() {
                    $("#onepass_2fa_modal").css("display", "none");
                });
                
                $("#onepass_2fa_input").off("keypress").on("keypress", function(e) {
                    if(e.which === 13) $("#onepass_2fa_confirm").click();
                });
            }

            function fetchPasswordInline(id, action, callback, otp_code = "") {
                let postData = { id: id, action: action };
                if (otp_code !== "") postData.otp = otp_code;

                $.post('{$ajax_url}', postData)
                .done(function(data) {
                    if (data.require_2fa) {
                        show2FAModalList(data.email, function(code) {
                            fetchPasswordInline(id, action, callback, code.trim());
                        });
                    } else if (data.success) {
                        callback(data.password);
                    } else {
                        alert("Aviso OnePass: " + (data.error || "Erro desconhecido."));
                    }
                }).fail(function() { alert("Erro de comunicação com o servidor."); });
            }

            $(document).off("click", ".btn-reveal-inline").on("click", ".btn-reveal-inline", function() {
                let btn = $(this);
                let id = btn.data("id");
                let pwdSpan = $("#inline_pwd_" + id);

                if (btn.hasClass("fa-eye-slash")) {
                    pwdSpan.html("&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;").css("letter-spacing", "2px");
                    btn.removeClass("fa-eye-slash").addClass("fa-eye");
                    clearTimeout(revealTimeouts[id]);
                } else {
                    fetchPasswordInline(id, "reveal", function(pwd) {
                        pwdSpan.text(pwd).css("letter-spacing", "1px");
                        btn.removeClass("fa-eye").addClass("fa-eye-slash");
                        clearTimeout(revealTimeouts[id]);
                        revealTimeouts[id] = setTimeout(() => {
                            pwdSpan.html("&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;").css("letter-spacing", "2px");
                            btn.removeClass("fa-eye-slash").addClass("fa-eye");
                        }, 10000);
                    });
                }
            });

            $(document).off("click", ".btn-copy-inline").on("click", ".btn-copy-inline", function() {
                let btn = $(this);
                let id = btn.data("id");
                fetchPasswordInline(id, "copy", function(pwd) {
                    secureCopyToClipboard(pwd);
                    btn.removeClass("fa-copy").addClass("fa-check inline-action-success");
                    setTimeout(() => { btn.removeClass("fa-check inline-action-success").addClass("fa-copy"); }, 2000);
                });
            });
        });
        </script>
JS;
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

    public static function logAudit(int $password_id, string $action, string $two_fa_email = null) {
        global $DB;
        $DB->insert('glpi_plugin_onepass_audits', [
            'plugin_onepass_passwords_id' => $password_id,
            'users_id'                    => Session::getLoginUserID(),
            'action'                      => $action,
            'two_fa_email'                => $two_fa_email,
            'ip_address'                  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent'                  => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'date_creation'               => $_SESSION['glpi_currenttime']
        ]);
    }
}