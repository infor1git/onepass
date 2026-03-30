<?php
/**
 * Classe de Dicionário: Tipos de Senha
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginOnepassType extends CommonDropdown {

    public $can_be_translated = false;

    static function getTypeName($nb = 0) {
        return 'Tipos de Senha';
    }

    /**
     * Força o botão de Adicionar nativo do GLPI a apontar para o nosso formulário
     */
    static function getFormURL($full = true) {
        return Plugin::getWebDir('onepass', $full) . '/front/type.form.php';
    }

    static function getSearchURL($full = true) {
        return Plugin::getWebDir('onepass', $full) . '/front/type.php';
    }

    // 1. Força a classe a usar as mesmas permissões que já configuramos no perfil
    public static function canCreate() {
        return Session::haveRight('plugin_onepass_create', 1);
    }
    public static function canView() {
        return Session::haveRight('plugin_onepass_read', 1);
    }
    public static function canUpdate() {
        return Session::haveRight('plugin_onepass_update', 1);
    }
    public static function canDelete() {
        return Session::haveRight('plugin_onepass_delete', 1);
    }

    // 2. Define as colunas exatas que aparecerão na tela type.php
    public function rawSearchOptions() {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => 'Características Básicas'];

        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => 'Nome do Tipo',
            'datatype'      => 'itemlink',
            'massiveaction' => false
        ];

        $tab[] = [
            'id'            => '16', // O GLPI usa o ID 16 por padrão para comentários de dropdowns
            'table'         => $this->getTable(),
            'field'         => 'comment',
            'name'          => 'Comentários / Descrição',
            'datatype'      => 'text',
            'massiveaction' => false
        ];

        return $tab;
    }
}