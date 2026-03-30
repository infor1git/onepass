<?php
/**
 * Controlador de Listagem: Tipos de Senha
 */

include("../../../inc/includes.php");

Session::checkRight("plugin_onepass_read", 1);

Html::header("Tipos de Senha", $_SERVER['PHP_SELF'], "admin", "pluginonepassmenu", "type");

Search::show('PluginOnepassType');

Html::footer();