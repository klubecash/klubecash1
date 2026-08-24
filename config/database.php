<?php

date_default_timezone_set('America/Sao_Paulo');

/**
 * config/database.php
 * Klube Cash - Sistema de Cashback
 *
 * Banco de dados:
 * Aiven MySQL 8.4
 */

// ======================================================
// CONFIGURAÇÕES DO BANCO AIVEN
// ======================================================

define('DB_HOST', 'mysql-2829cd07-klubecash.e.aivencloud.com');
define('DB_PORT', 24053);
define('DB_NAME', 'defaultdb');
define('DB_USER', 'avnadmin');

// COLOQUE AQUI A NOVA SENHA DO AIVEN
$password = getenv('DB_PASS');
define('DB_PASS', $password === false ? '' : $password);

// Certificado SSL do Aiven.
// Baixe o arquivo CA Certificate no painel do Aiven
// e salve como "ca.pem" dentro da pasta config.
define('DB_SSL_CA', __DIR__ . '/ca.pem');


/**
 * Classe Database
 * Gerencia a conexão PDO com o MySQL do Aiven.
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

            // Verifica se o certificado SSL existe.
            if (!file_exists(DB_SSL_CA)) {
                throw new Exception(
                    'Certificado SSL do banco não encontrado em: ' . DB_SSL_CA
                );
            }

            // DSN de conexão com o MySQL Aiven
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

                // SSL obrigatório
                PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
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
