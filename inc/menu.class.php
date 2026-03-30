<?php
/**
 * Classe para gerenciamento do menu principal e submenus do OnePass
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginOnepassMenu extends CommonGLPI {
    
    /**
     * Nome do menu principal que aparecerá na lateral
     */
    static function getMenuName() {
        return 'OnePass';
    }
    
    static function getMenuContent() {
        // Ícones customizados para os botões extras da Action Bar
        $types_image = '<i class="fas fa-tags" title="Tipos de Senha"></i>&nbsp; Tipos de Senha';
        $passwords_image = '<i class="fas fa-key" title="Ver Senhas"></i>&nbsp; Ver Senhas';
        
        // Estrutura principal do menu
        $menu = [
            'title' => self::getMenuName(),
            'page'  => Plugin::getWebDir('onepass') . '/front/password.php',
            'icon'  => 'fas fa-shield-alt', // Ícone do menu lateral
            'links' => [
                'search' => Plugin::getWebDir('onepass') . '/front/password.php',
                'add'    => Plugin::getWebDir('onepass') . '/front/password.form.php',
            ]
        ];
        
        // Define as opções (Submenus/Abas) respeitando as permissões
        if (Session::haveRight('plugin_onepass_read', 1)) {
            
            // 1. Submenu: Gestão de Senhas
            $menu['options']['password'] = [
                'title' => 'Gestão de Senhas',
                'page'  => Plugin::getWebDir('onepass') . '/front/password.php',
                'links' => [
                    'search' => Plugin::getWebDir('onepass') . '/front/password.php',
                    'add'    => Plugin::getWebDir('onepass') . '/front/password.form.php',
                    // Adiciona o botão de Tipos nativamente ao lado de "Adicionar"
                    $types_image => Plugin::getWebDir('onepass') . '/front/type.php',
                ]
            ];
            
            // 2. Submenu: Tipos de Senha
            $menu['options']['type'] = [
                'title' => 'Tipos de Senha',
                'page'  => Plugin::getWebDir('onepass') . '/front/type.php',
                'links' => [
                    'search' => Plugin::getWebDir('onepass') . '/front/type.php',
                    'add'    => Plugin::getWebDir('onepass') . '/front/type.form.php',
                    // Adiciona o botão de voltar para Senhas nativamente
                    $passwords_image => Plugin::getWebDir('onepass') . '/front/password.php'
                ]
            ];
        }
        
        return $menu;
    }
}