<?php

date_default_timezone_set('America/Sao_Paulo');

/**
 * config/database.php
 * Klube Cash - Sistema de Cashback
 *
 * Banco de dados:
 * MySQL gerenciado pelo Coolify (rede interna)
 */

// ======================================================
// CONFIGURAÇÕES DO BANCO COOLIFY
// ======================================================

$envValue = static function (array $names, string $default = ''): string {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }
    return $default;
};

$connectionUrl = $envValue(['DATABASE_URL', 'MYSQL_URL']);
$urlParts = $connectionUrl !== '' ? parse_url($connectionUrl) : [];
$urlParts = is_array($urlParts) ? $urlParts : [];
$urlValue = static function (string $key, string $default = '') use ($urlParts): string {
    $value = $urlParts[$key] ?? '';
    return is_scalar($value) && trim((string) $value) !== '' ? urldecode((string) $value) : $default;
};

define('DB_HOST', $envValue(['DB_HOST', 'MYSQL_HOST'], $urlValue('host', 'mysql-database-9rnzmfh5g7y53xijjhc3ct7h')));
define('DB_PORT', (int) $envValue(['DB_PORT', 'MYSQL_PORT'], $urlValue('port', '3306')));
define('DB_NAME', $envValue(['DB_DATABASE', 'DB_NAME', 'MYSQL_DATABASE'], ltrim($urlValue('path', '/default'), '/')));
define('DB_USER', $envValue(['DB_USERNAME', 'DB_USER', 'MYSQL_USER'], $urlValue('user', 'mysql')));
$configuredPassword = $envValue(['DB_PASSWORD', 'MYSQL_PASSWORD']);
define('DB_PASS', $configuredPassword !== '' ? $configuredPassword : ($urlValue('pass') !== '' ? $urlValue('pass') : $envValue(['DB_PASS'])));


/**
 * Classe Database
 * Gerencia a conexão PDO com o MySQL do Coolify.
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
            throw new RuntimeException('Variável de ambiente DB_PASSWORD (ou DB_PASS) não configurada.');
        }

        try {

            // O MySQL do Coolify está na rede interna; SSL não é necessário.
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
