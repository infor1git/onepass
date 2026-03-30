<?php
/**
 * Controlador de Ações e Exibição do Formulário
 */

include("../../../inc/includes.php");

$plugin = new PluginOnepassPassword();

// ==========================================
// BLOCO 1: PROCESSAMENTO DE AÇÕES (POST)
// ==========================================

if (isset($_POST["add"])) {
    $plugin->check(-1, CREATE, $_POST);
    $plugin->add($_POST);
    Html::back();
    exit;
} 
else if (isset($_POST["update"])) {
    $plugin->check($_POST["id"], UPDATE);
    $plugin->update($_POST);
    Html::back();
    exit;
} 
else if (isset($_POST["purge"])) {
    $plugin->check($_POST["id"], PURGE);
    $plugin->delete($_POST, 1);
    $plugin->redirectToList();
    exit;
}

// ==========================================
// BLOCO 2: EXIBIÇÃO DA TELA (GET)
// ==========================================

// Se o código chegou até aqui, é porque o usuário clicou em "+" ou em uma senha existente
// para abrir o formulário. Então vamos desenhar a tela.

// Verifica se o usuário tem permissão mínima para ver a tela
$plugin->checkGlobal(READ);

// Monta o cabeçalho do GLPI marcando o menu ativo
Html::header("OnePass - Formulário de Senha", $_SERVER['PHP_SELF'], "admin", "pluginonepassmenu", "password");

// Identifica se estamos editando uma senha existente (id > 0) ou criando uma nova (id = 0)
$id = $_GET["id"] ?? 0;

// O método display() do GLPI cuida de encapsular e chamar o nosso showForm() com segurança
$plugin->display([
    'id'       => $id,
    'itemtype' => $_GET['itemtype'] ?? '',
    'items_id' => $_GET['items_id'] ?? 0
]);

// Renderiza o rodapé do sistema
Html::footer();