<?php
/**
 * Classe de Criptografia do OnePass
 * Utiliza AES-256-GCM para garantir Confidencialidade e Integridade.
 */

class PluginOnepassCrypto {

    private const CIPHER_ALGO = 'aes-256-gcm';
    // Armazena a chave no diretório de configurações seguro do GLPI
    private const KEY_FILE = GLPI_CONFIG_DIR . '/onepass_master.key';

    /**
     * Recupera a Master Key. Se não existir, gera uma nova de 256 bits (32 bytes).
     * Em um ambiente real, garanta que o usuário do webserver (ex: www-data) 
     * tenha permissão de escrita apenas na primeira execução.
     */
    private static function getMasterKey(): string {
        if (!file_exists(self::KEY_FILE)) {
            $newKey = random_bytes(32);
            if (file_put_contents(self::KEY_FILE, $newKey) === false) {
                throw new \RuntimeException("OnePass: Falha ao criar a Master Key no diretório de configuração.");
            }
            chmod(self::KEY_FILE, 0600); // Restringe acesso apenas ao dono do processo
            return $newKey;
        }

        $key = file_get_contents(self::KEY_FILE);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException("OnePass: Master Key inválida ou corrompida.");
        }

        return $key;
    }

    /**
     * Criptografa o texto em claro.
     * Retorna o payload em Base64 contendo: IV + Dados Criptografados + Tag de Autenticação
     */
    public static function encrypt(string $plainText): string {
        if (empty($plainText)) {
            return '';
        }

        $key = self::getMasterKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = random_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $plainText,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException("OnePass: Falha na criptografia dos dados.");
        }

        // Empacota tudo e converte para base64 para armazenamento seguro no banco (tipo TEXT)
        $payload = $iv . $encrypted . $tag;
        return base64_encode($payload);
    }

    /**
     * Descriptografa o payload e verifica a integridade.
     */
    public static function decrypt(string $base64Payload): string {
        if (empty($base64Payload)) {
            return '';
        }

        $key = self::getMasterKey();
        $payload = base64_decode($base64Payload);
        
        if ($payload === false) {
            return ''; // Payload inválido
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $tagLength = 16; // O GCM por padrão usa tags de 16 bytes

        // O payload deve ser no mínimo maior que a soma do IV e da Tag
        if (strlen($payload) <= ($ivLength + $tagLength)) {
            return ''; // Evita falha de segmentação ou warnings
        }

        // Desempacota as partes
        $iv = substr($payload, 0, $ivLength);
        $tag = substr($payload, -$tagLength);
        $encryptedData = substr($payload, $ivLength, -$tagLength);

        $decrypted = openssl_decrypt(
            $encryptedData,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            // Se cair aqui, a senha foi alterada no banco por fora do sistema, 
            // ou a Master Key foi perdida/alterada.
            return '### DADOS CORROMPIDOS OU CHAVE INVÁLIDA ###';
        }

        return $decrypted;
    }
}