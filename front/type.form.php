<?php
/**
 * Controlador de Formulário: Tipos de Senha
 */

include("../../../inc/includes.php");

$type = new PluginOnepassType();

// ==========================================
// PROCESSAMENTO (POST)
// ==========================================
if (isset($_POST["add"])) {
    $type->check(-1, CREATE, $_POST);
    $type->add($_POST);
    Html::back();
    exit;
} else if (isset($_POST["update"])) {
    $type->check($_POST["id"], UPDATE);
    $type->update($_POST);
    Html::back();
    exit;
} else if (isset($_POST["purge"])) {
    $type->check($_POST["id"], PURGE);
    $type->delete($_POST, 1);
    $type->redirectToList();
    exit;
}

// ==========================================
// EXIBIÇÃO (GET)
// ==========================================
$type->checkGlobal(READ);

// Amarra a modal/tela de edição também à árvore do OnePass
Html::header("Tipos de Senha", $_SERVER['PHP_SELF'], "admin", "pluginonepassmenu", "type");

$type->display([
    'id' => $_GET["id"] ?? 0
]);

Html::footer();