<?php
/**
 * OnePass - Gerenciador de Senhas Profissional para GLPI
 * * @author  INFOR1
 * @license Comercial
 */

define('PLUGIN_ONEPASS_VERSION', '1.0.7');

// Inicialização do Plugin no ecosistema GLPI
function plugin_init_onepass() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['onepass'] = true;
    
    /// Registra a aba nos ativos
    Plugin::registerClass('PluginOnepassPassword', [
        'addtabon' => ['Ticket', 'Computer', 'NetworkEquipment', 'Software']
    ]);

    // Gancho moderno do GLPI 10 apontando para a classe de Menu
    $PLUGIN_HOOKS['menu_toadd']['onepass'] = ['admin' => 'PluginOnepassMenu'];

    // Injeta a aba do OnePass na configuração de Perfis do GLPI
    Plugin::registerClass('PluginOnepassProfile', [
        'addtabon' => 'Profile'
    ]);

    // Injeta a aba de Auditoria DENTRO do formulário do OnePass
    Plugin::registerClass('PluginOnepassAudit', [
        'addtabon' => ['PluginOnepassPassword']
    ]);

    // Hook para adicionar folha de estilo customizada (ex: para o Security Indicator)
    $PLUGIN_HOOKS['add_css']['onepass'] = 'css/styles.css';

    Plugin::registerClass('PluginOnepassType');
    
    $PLUGIN_HOOKS['dropdown_page']['onepass'] = ['PluginOnepassType'];
}

// Definição da versão e requisitos básicos
function plugin_version_onepass() {
    return [
        'name'           => 'OnePass',
        'version'        => PLUGIN_ONEPASS_VERSION,
        'author'         => 'INFOR1',
        'license'        => 'Comercial',
        'homepage'       => 'https://infor1.com.br/onepass',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.16', // Trava de segurança para compatibilidade da API
                'max' => '10.1.0',
            ],
            'php' => [
                'min' => '8.1' // Requisito para funções modernas de criptografia e tipagem
            ]
        ]
    ];
}

// Verificação de pré-requisitos executada antes da instalação
function plugin_onepass_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '10.0.16', '<')) {
        echo "Este plugin requer GLPI >= 10.0.16";
        return false;
    }
    
    // Verifica se a extensão OpenSSL está ativa para o motor de criptografia
    if (!extension_loaded('openssl')) {
        echo "A extensão PHP OpenSSL é obrigatória para a criptografia do OnePass.";
        return false;
    }

    return true;
}

function plugin_onepass_check_config() {
    return true;
}