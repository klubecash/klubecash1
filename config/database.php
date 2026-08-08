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
            self::createEmailQueueTableIfNotExists(
                self::$connection
            );

            return self::$connection;

        } catch (PDOException $e) {

            error_log(
                'Erro de conexão com banco de dados: ' .
                $e->getMessage()
            );

            die(
                'Não foi possível conectar ao banco de dados. ' .
                'Por favor, tente novamente mais tarde.'
            );

        } catch (Exception $e) {

            error_log(
                'Erro de configuração do banco de dados: ' .
                $e->getMessage()
            );

            die(
                'Erro na configuração do banco de dados. ' .
                'Verifique o certificado SSL.'
            );
        }
    }


    /**
     * Cria a tabela de fila de emails caso ela ainda não exista.
     *
     * @param PDO $db
     * @return void
     */
    private static function createEmailQueueTableIfNotExists($db)
    {
        try {

            $sql = "
                CREATE TABLE IF NOT EXISTS `email_queue` (
                    `id` INT NOT NULL AUTO_INCREMENT,

                    `to_email` VARCHAR(255) NOT NULL,

                    `to_name` VARCHAR(255) DEFAULT NULL,

                    `subject` VARCHAR(255) NOT NULL,

                    `message` TEXT NOT NULL,

                    `status`
                        ENUM(
                            'pending',
                            'sending',
                            'sent',
                            'failed'
                        )
                        NOT NULL
                        DEFAULT 'pending',

                    `attempts`
                        INT NOT NULL
                        DEFAULT 0,

                    `last_attempt`
                        TIMESTAMP NULL
                        DEFAULT NULL,

                    `created_at`
                        TIMESTAMP NULL
                        DEFAULT CURRENT_TIMESTAMP,

                    PRIMARY KEY (`id`),

                    INDEX `idx_email_queue_status` (`status`),

                    INDEX `idx_email_queue_created_at` (`created_at`)

                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
            ";

            $db->exec($sql);

        } catch (PDOException $e) {

            error_log(
                'Erro ao criar tabela email_queue: ' .
                $e->getMessage()
            );
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
