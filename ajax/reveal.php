<?php
/**
 * Endpoint AJAX seguro com 2FA Dinâmico e Trava Anti-Fraude
 */

include("../../../inc/includes.php");
header('Content-Type: application/json');

if (!Session::haveRight('plugin_onepass_read', 1)) {
    echo json_encode(['error' => 'Acesso negado. Sessão inválida ou sem permissão.']);
    exit;
}

$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? 'reveal';

if ($id <= 0) {
    echo json_encode(['error' => 'ID de credencial inválido.']);
    exit;
}

$pwd = new PluginOnepassPassword();
if (!$pwd->getFromDB($id)) {
    echo json_encode(['error' => 'Credencial não encontrada ou excluída.']);
    exit;
}

$level = $pwd->fields['access_level'];
$owner = $pwd->fields['users_id'];
$current_user_id = Session::getLoginUserID();

$can_view = false;
if ($level == 1) $can_view = true;
elseif ($level == 2 && $owner == $current_user_id) $can_view = true;
elseif ($level == 3 && Session::haveRight('plugin_onepass_level_confidential', 1)) $can_view = true;
elseif ($level == 4 && Session::haveRight('plugin_onepass_level_strict', 1)) $can_view = true;

if (!$can_view) {
    echo json_encode(['error' => 'Você não possui o nível de confidencialidade necessário para ver esta senha.']);
    exit;
}

$two_fa_email_used = null;

// ==========================================================
// MÓDULO 2FA: EXCLUSIVO PARA ESTRITAMENTE CONFIDENCIAL (4)
// ==========================================================
if ($level == 4) {
    $otp_provided = $_POST['otp'] ?? '';
    
    // Se não mandou o código ainda, fazemos a checagem e enviamos
    if (empty($otp_provided)) {
        global $DB;

        $email = UserEmail::getDefaultForUser($current_user_id);
        if (empty($email)) {
            echo json_encode(['error' => 'Seu perfil no GLPI não possui um e-mail cadastrado para receber o 2FA.']);
            exit;
        }

        // --- TRAVA ANTI-FRAUDE 24 HORAS ---
        // Checa nos logs do GLPI se este usuário adicionou ou removeu e-mails recentemente
        $log_query = "SELECT COUNT(*) as cpt FROM glpi_logs 
                      WHERE (
                          (itemtype = 'UserEmail' AND items_id IN (SELECT id FROM glpi_useremails WHERE users_id = $current_user_id))
                          OR 
                          (itemtype = 'User' AND items_id = $current_user_id AND (new_value LIKE '%@%' OR old_value LIKE '%@%'))
                      ) 
                      AND date_mod > (NOW() - INTERVAL 24 HOUR)";
        
        $result = $DB->query($log_query);
        $row = $DB->fetchAssoc($result);
        if ($row['cpt'] > 0) {
            echo json_encode(['error' => 'ALERTA DE SEGURANÇA: Detectamos uma alteração de e-mail na sua conta nas últimas 24 horas. Por medida de prevenção a fraudes e roubo de credenciais críticas, o envio de tokens 2FA está bloqueado temporariamente.']);
            exit;
        }
        // -----------------------------------
        
        $code = sprintf("%06d", mt_rand(1, 999999));
        
        // Salva o código e o e-mail na sessão
        $_SESSION['onepass_2fa'][$id] = [
            'code'  => $code,
            'email' => $email
        ];
        
        $mmail = new GLPIMailer();
        $mmail->AddAddress($email);
        $mmail->Subject = "[OnePass] Codigo de Seguranca 2FA";
        $mmail->Body = "Ola,\n\nVoce solicitou acesso a uma credencial Estritamente Confidencial no OnePass.\n\nSeu codigo de autorizacao e: $code\n\nEste codigo e valido apenas para este acesso.";
        
        if (!$mmail->send()) {
            echo json_encode(['error' => 'Falha ao enviar o e-mail de 2FA. Verifique o SMTP do GLPI.']);
            exit;
        }
        
        $parts = explode('@', $email);
        $masked = substr($parts[0], 0, 1) . '*****' . substr($parts[0], -1) . '@' . $parts[1];
        
        echo json_encode(['require_2fa' => true, 'email' => $masked]);
        exit;
        
    } else {
        // Validação do Código Recebido
        if (!isset($_SESSION['onepass_2fa'][$id]) || $_SESSION['onepass_2fa'][$id]['code'] !== $otp_provided) {
            echo json_encode(['error' => 'Código 2FA incorreto ou expirado. Tente novamente.']);
            exit;
        }
        // Captura o e-mail que foi usado para logar na auditoria e destrói a sessão
        $two_fa_email_used = $_SESSION['onepass_2fa'][$id]['email'];
        unset($_SESSION['onepass_2fa'][$id]);
    }
}
// ==========================================================

$clear_pwd = PluginOnepassCrypto::decrypt($pwd->fields['password_hash']);

if ($clear_pwd === '### DADOS CORROMPIDOS OU CHAVE INVÁLIDA ###') {
    echo json_encode(['error' => 'Falha na integridade: A senha foi adulterada.']);
    exit;
}

// Passa o e-mail usado no 2FA para ser gravado no banco de logs
PluginOnepassPassword::logAudit($id, $action, $two_fa_email_used);

echo json_encode(['success' => true, 'password' => $clear_pwd]);