<?php

date_default_timezone_set('America/Sao_Paulo');

/**
 * config/database.php
 * Klube Cash - Sistema de Cashback
 *
 * Banco de dados:
 * MySQL configurável por ambiente (Coolify em produção; Aiven como fallback)
 */

// ======================================================
// CONFIGURAÇÕES DO BANCO
// ======================================================

$env = static function (string $name, ?string $fallback = null): ?string {
    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        return $fallback;
    }
    return (string) $value;
};

// Os nomes novos (DB_DATABASE/DB_USERNAME/DB_PASSWORD) são usados pelo
// Coolify. Os aliases antigos continuam funcionando para não quebrar o
// ambiente Aiven durante a transição.
define('DB_HOST', $env('DB_HOST', 'mysql-2829cd07-klubecash.e.aivencloud.com'));
define('DB_PORT', (int) $env('DB_PORT', '24053'));
define('DB_NAME', $env('DB_DATABASE', $env('DB_NAME', 'defaultdb')));
define('DB_USER', $env('DB_USERNAME', $env('DB_USER', 'avnadmin')));
define('DB_PASS', $env('DB_PASSWORD', $env('DB_PASS', '')));

// Coolify gera um certificado próprio. Quando DB_SSL_CA não for informado,
// exigimos TLS sem validação de CA; somente o fallback Aiven usa config/ca.pem.
define('DB_SSL_MODE', strtolower($env('DB_SSL_MODE', 'required')));
$sslCa = getenv('DB_SSL_CA');
if ($sslCa === false || trim((string) $sslCa) === '') {
    if (str_contains(DB_HOST, 'aivencloud.com')) {
        $sslCa = __DIR__ . '/ca.pem';
    } else {
        // O Coolify usa certificado próprio; o bundle local apenas habilita
        // a negociação TLS enquanto DB_SSL_VERIFY permanece desativado.
        foreach (['/etc/ssl/cert.pem', '/etc/ssl/certs/ca-certificates.crt'] as $candidate) {
            if (file_exists($candidate)) {
                $sslCa = $candidate;
                break;
            }
        }
        $sslCa ??= '';
    }
}
define('DB_SSL_CA', (string) $sslCa);
$sslVerify = getenv('DB_SSL_VERIFY');
define(
    'DB_SSL_VERIFY',
    $sslVerify === false
        ? str_contains(DB_HOST, 'aivencloud.com')
        : filter_var($sslVerify, FILTER_VALIDATE_BOOL)
);


/**
 * Classe Database
 * Gerencia a conexão PDO com o MySQL.
 */
class Database
{
    private static $connection = null;

    /**
     * Retorna a conexão com o banco de dados.
     *
     * @return PDO
     */
    public static function getConnection()
    {
        // Se a conexão já existe, reutiliza.
        if (self::$connection !== null) {
            return self::$connection;
        }

        if (DB_PASS === '') {
            throw new RuntimeException('Variável de ambiente DB_PASS não configurada.');
        }

        try {

            // DSN de conexão com o MySQL
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [

                // Exibe erros como exceções
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // Retorna consultas como arrays associativos
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Usa prepared statements reais
                PDO::ATTR_EMULATE_PREPARES => false,

            ];

            if (DB_SSL_MODE !== 'disable' && DB_SSL_MODE !== 'disabled' && DB_SSL_MODE !== 'off') {
                if (DB_SSL_CA !== '' && file_exists(DB_SSL_CA)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
                }
                // O proxy TLS do Coolify usa certificado próprio. A validação
                // pode ser ativada quando uma CA confiável for fornecida.
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = DB_SSL_VERIFY;
            }

            $persistentSetting = getenv('DB_PERSISTENT');
            $options[PDO::ATTR_PERSISTENT] = $persistentSetting === false
                ? PHP_SAPI !== 'cli'
                : filter_var((string) $persistentSetting, FILTER_VALIDATE_BOOL);

            // Cria conexão
            self::$connection = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $options
            );

            // Configura timezone da sessão MySQL
            self::$connection->exec(
                "SET time_zone = '-03:00'"
            );

            // Cria tabela email_queue se necessário
            return self::$connection;

        } catch (PDOException $e) {

            error_log('database.connection.failed type=' . get_class($e));
            throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $e);

        } catch (Exception $e) {

            error_log('database.configuration.failed type=' . get_class($e));
            throw new RuntimeException('Erro na configuração do banco de dados.', 0, $e);
        }
    }


    /**
     * Fecha a conexão.
     *
     * @return void
     */
    public static function closeConnection()
    {
        self::$connection = null;
    }
}
