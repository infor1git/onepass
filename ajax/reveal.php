<?php
/**
 * Endpoint AJAX seguro para revelar/copiar senhas e gerar log de auditoria.
 */

// Define o caminho correto para o core do GLPI
include("../../../inc/includes.php");

// Força o retorno em JSON
header('Content-Type: application/json');

// 1. O usuário precisa estar logado e ter pelo menos a permissão de abrir o OnePass
if (!Session::haveRight('plugin_onepass_read', 1)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado. Sessão inválida ou sem permissão.']);
    exit;
}

$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? 'reveal'; // pode ser 'reveal' ou 'copy'

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de credencial inválido.']);
    exit;
}

// 2. Busca a senha no banco
$pwd = new PluginOnepassPassword();
if (!$pwd->getFromDB($id)) {
    http_response_code(404);
    echo json_encode(['error' => 'Credencial não encontrada ou excluída.']);
    exit;
}

// 3. Validação de Compliance / Nível de Acesso (Strict Control)
$level = $pwd->fields['access_level'];
$owner = $pwd->fields['users_id'];
$current_user_id = Session::getLoginUserID();

$can_view = false;
if ($level == 1) { // Público
    $can_view = true;
} elseif ($level == 2 && $owner == $current_user_id) { // Privado e dono
    $can_view = true;
} elseif ($level == 3 && Session::haveRight('plugin_onepass_level_confidential', 1)) { // Confidencial
    $can_view = true;
} elseif ($level == 4 && Session::haveRight('plugin_onepass_level_strict', 1)) { // Estritamente Confidencial
    $can_view = true;
}

// Se o usuário logado esbarrou no controle de acesso, bloqueamos e alertamos
if (!$can_view) {
    http_response_code(403);
    echo json_encode(['error' => 'Você não possui o nível de confidencialidade necessário para ver esta senha.']);
    exit;
}

// 4. Descriptografa os dados
$clear_pwd = PluginOnepassCrypto::decrypt($pwd->fields['password_hash']);

if ($clear_pwd === '### DADOS CORROMPIDOS OU CHAVE INVÁLIDA ###') {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na integridade: A senha foi adulterada ou a Master Key é inválida.']);
    exit;
}

// 5. Gera a trilha de auditoria inalterável!
PluginOnepassPassword::logAudit($id, $action);

// 6. Entrega a senha em texto claro apenas para o Javascript renderizar
echo json_encode([
    'success'  => true, 
    'password' => $clear_pwd
]);