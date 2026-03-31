<?php

function plugin_onepass_install() {
    global $DB;
    $migration = new Migration(PLUGIN_ONEPASS_VERSION);

    // 1. Tabela Principal de Senhas (existente)
    if (!$DB->tableExists('glpi_plugin_onepass_passwords')) {
        $query = "CREATE TABLE `glpi_plugin_onepass_passwords` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `entities_id` int(11) NOT NULL DEFAULT '0',
            `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `password_hash` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `access_level` int(11) NOT NULL DEFAULT '1',
            `users_id` int(11) NOT NULL,
            `itemtype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `items_id` int(11) NOT NULL DEFAULT '0',
            `date_mod` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `item` (`itemtype`,`items_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, "Erro ao criar tabela glpi_plugin_onepass_passwords");
    }

    // 2. Tabela de Auditoria (existente, mas com o campo novo)
    if (!$DB->tableExists('glpi_plugin_onepass_audits')) {
        $query = "CREATE TABLE `glpi_plugin_onepass_audits` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `plugin_onepass_passwords_id` int(11) NOT NULL,
            `users_id` int(11) NOT NULL,
            `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `two_fa_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
            `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `plugin_onepass_passwords_id` (`plugin_onepass_passwords_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, "Erro ao criar tabela de auditoria");
    }

    // 3. Tabela Dicionário de Tipos
    if (!$DB->tableExists('glpi_plugin_onepass_types')) {
        $query = "CREATE TABLE `glpi_plugin_onepass_types` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `comment` text COLLATE utf8mb4_unicode_ci,
            PRIMARY KEY (`id`),
            KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, "Erro ao criar tabela de tipos");
    }

    // 4. ATUALIZAÇÕES (Migrations)
    if (!$DB->fieldExists('glpi_plugin_onepass_passwords', 'plugin_onepass_types_id')) {
        $migration->addField('glpi_plugin_onepass_passwords', 'plugin_onepass_types_id', 'int(11) NOT NULL DEFAULT 0');
    }
    if (!$DB->fieldExists('glpi_plugin_onepass_passwords', 'comment')) {
        $migration->addField('glpi_plugin_onepass_passwords', 'comment', 'text COLLATE utf8mb4_unicode_ci');
    }
    
    // NOVA COLUNA 2FA na Auditoria
    if (!$DB->fieldExists('glpi_plugin_onepass_audits', 'two_fa_email')) {
        $migration->addField('glpi_plugin_onepass_audits', 'two_fa_email', 'varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER action');
    }

    $migration->executeMigration();
    return true;
}

function plugin_onepass_uninstall() {
    global $DB;

    // Em plugins comerciais com dados sensíveis, avalie se deve fazer um DROP ou Soft Delete.
    // Aqui usamos o padrão do GLPI, mas emitindo um warning na documentação.
    $tables = [
        'glpi_plugin_onepass_passwords',
        'glpi_plugin_onepass_audits'
    ];

    foreach ($tables as $table) {
        $DB->dropTable($table);
    }

    return true;
}