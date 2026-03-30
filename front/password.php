<?php
/**
 * Controlador de Interface - Gestão Central de Senhas
 */

include("../../../inc/includes.php");

Session::checkRight("plugin_onepass_read", 1);

Html::header("OnePass - Gestão de Senhas", $_SERVER['PHP_SELF'], "admin", "pluginonepassmenu", "password");

Search::show('PluginOnepassPassword');

Html::footer();