-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 29/09/2025 às 22:09
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u383946504_klubecash`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_reserva_cashback`
--

CREATE TABLE `admin_reserva_cashback` (
  `id` int(11) NOT NULL,
  `valor_total` decimal(10,2) DEFAULT 0.00,
  `valor_disponivel` decimal(10,2) DEFAULT 0.00,
  `valor_usado` decimal(10,2) DEFAULT 0.00,
  `ultima_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `admin_reserva_cashback`
--

INSERT INTO `admin_reserva_cashback` (`id`, `valor_total`, `valor_disponivel`, `valor_usado`, `ultima_atualizacao`) VALUES
(1, 21.70, 21.70, 0.00, '2025-09-19 19:27:05');

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_reserva_movimentacoes`
--

CREATE TABLE `admin_reserva_movimentacoes` (
  `id` int(11) NOT NULL,
  `transacao_id` int(11) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `tipo` enum('credito','debito') DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_operacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `admin_reserva_movimentacoes`
--

INSERT INTO `admin_reserva_movimentacoes` (`id`, `transacao_id`, `valor`, `tipo`, `descricao`, `data_operacao`) VALUES
(16, 1186, 7.00, 'credito', 'Reserva de cashback - Pagamento #1186 aprovado - Total de clientes: 1', '2025-09-19 19:26:06'),
(17, 1185, 7.00, 'credito', 'Reserva de cashback - Pagamento #1185 aprovado - Total de clientes: 1', '2025-09-19 19:26:46'),
(18, 1184, 0.70, 'credito', 'Reserva de cashback - Pagamento #1184 aprovado - Total de clientes: 1', '2025-09-19 19:27:02'),
(19, 1183, 7.00, 'credito', 'Reserva de cashback - Pagamento #1183 aprovado - Total de clientes: 1', '2025-09-19 19:27:05');

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_saldo`
--

CREATE TABLE `admin_saldo` (
  `id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_disponivel` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_pendente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ultima_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `admin_saldo`
--

INSERT INTO `admin_saldo` (`id`, `valor_total`, `valor_disponivel`, `valor_pendente`, `ultima_atualizacao`) VALUES
(1, 0.00, 0.00, 0.00, '2025-09-17 21:10:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_saldo_movimentacoes`
--

CREATE TABLE `admin_saldo_movimentacoes` (
  `id` int(11) NOT NULL,
  `transacao_id` int(11) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('credito','debito') NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_operacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `key_prefix` varchar(10) NOT NULL,
  `partner_name` varchar(100) NOT NULL,
  `partner_email` varchar(100) NOT NULL,
  `permissions` text NOT NULL,
  `rate_limit_per_minute` int(11) DEFAULT 60,
  `rate_limit_per_hour` int(11) DEFAULT 1000,
  `is_active` tinyint(1) DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `webhook_secret` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `api_keys`
--

INSERT INTO `api_keys` (`id`, `key_hash`, `key_prefix`, `partner_name`, `partner_email`, `permissions`, `rate_limit_per_minute`, `rate_limit_per_hour`, `is_active`, `last_used_at`, `created_at`, `expires_at`, `webhook_url`, `webhook_secret`, `notes`) VALUES
(5, 'bb8ed1ec755809d6472a0b1ec1275a16fc497b71509eb0723eccc9e25810e186', 'kc_live', 'API Live Test', 'live@klubecash.com', '[\"*\"]', 1000, 10000, 1, '2025-08-25 22:49:35', '2025-08-25 22:18:01', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `api_logs`
--

CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL,
  `api_key_id` int(11) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `status_code` int(11) NOT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `request_body` text DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `api_rate_limits`
--

CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL,
  `api_key_id` int(11) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `requests_count` int(11) DEFAULT 0,
  `window_start` timestamp NULL DEFAULT current_timestamp(),
  `window_type` enum('minute','hour','day') DEFAULT 'minute'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bot_consultas`
--

CREATE TABLE `bot_consultas` (
  `id` int(11) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `tipo_consulta` varchar(50) NOT NULL,
  `data_consulta` timestamp NULL DEFAULT current_timestamp(),
  `usuario_encontrado` tinyint(4) DEFAULT 0,
  `dados_capturados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_capturados`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `bot_consultas`
--

INSERT INTO `bot_consultas` (`id`, `telefone`, `tipo_consulta`, `data_consulta`, `usuario_encontrado`, `dados_capturados`) VALUES
(1, '5538991045205', 'consulta_saldo', '2025-09-25 22:31:50', 0, NULL),
(2, '5538991045205', 'consulta_saldo', '2025-09-25 22:31:59', 0, NULL),
(3, '5538991045205', 'consulta_saldo', '2025-09-25 22:33:28', 0, NULL),
(4, '5538991045205', 'consulta_saldo', '2025-09-25 22:39:43', 0, NULL),
(5, '5538991045205', 'consulta_saldo', '2025-09-25 22:41:04', 0, NULL),
(6, '5538991045205', 'consulta_saldo', '2025-09-25 22:41:12', 0, NULL),
(7, '5538991045205', 'consulta_saldo', '2025-09-25 22:41:48', 0, NULL),
(8, '34993357697', 'consulta_saldo', '2025-09-25 22:45:37', 0, NULL),
(9, '38991045205', 'consulta_saldo', '2025-09-25 22:46:41', 0, NULL),
(10, '34993357697', 'consulta_saldo', '2025-09-25 22:48:26', 0, NULL),
(11, '38991045205', 'consulta_saldo', '2025-09-25 22:48:30', 0, NULL),
(12, '38991045205', 'consulta_saldo', '2025-09-25 22:55:37', 0, NULL),
(13, '38991045205', 'consulta_saldo', '2025-09-25 22:55:46', 0, NULL),
(14, '38991045205', 'consulta_saldo', '2025-09-25 22:56:18', 0, NULL),
(15, '34993357697', 'consulta_saldo', '2025-09-25 22:58:32', 0, NULL),
(16, '34993357697', 'consulta_saldo', '2025-09-25 22:58:49', 0, NULL),
(17, '38991045205', 'consulta_saldo', '2025-09-25 22:58:51', 0, NULL),
(18, '34993357697', 'consulta_saldo', '2025-09-25 23:23:35', 0, NULL),
(19, '34993357697', 'consulta_saldo', '2025-09-25 23:50:09', 0, NULL),
(20, '34993357697', 'consulta_saldo', '2025-09-26 00:09:08', 0, NULL),
(21, '34993357697', 'consulta_saldo', '2025-09-26 00:15:23', 0, NULL),
(22, '38991045205', 'consulta_saldo', '2025-09-26 10:07:32', 0, NULL),
(23, '38991045205', 'consulta_saldo', '2025-09-26 10:07:53', 0, NULL),
(24, '38991045205', 'consulta_saldo', '2025-09-26 10:14:57', 0, NULL),
(25, '38991045205', 'consulta_saldo', '2025-09-26 10:15:06', 0, NULL),
(26, '38991045205', 'consulta_saldo', '2025-09-26 10:16:03', 0, NULL),
(27, '38991045205', 'consulta_saldo', '2025-09-26 10:16:36', 0, NULL),
(28, '38991045205', 'consulta_saldo', '2025-09-26 10:18:35', 0, NULL),
(29, '38991045205', 'consulta_saldo', '2025-09-26 10:25:49', 0, NULL),
(30, '38991045205', 'menu_ajuda', '2025-09-26 10:26:08', 0, NULL),
(31, '38991045205', 'menu_ajuda', '2025-09-26 10:26:24', 0, NULL),
(32, '38991045205', 'menu_ajuda', '2025-09-26 10:26:31', 0, NULL),
(33, '38991045205', 'menu_ajuda', '2025-09-26 10:27:20', 0, NULL),
(34, '38991045205', 'menu_ajuda', '2025-09-26 10:28:04', 0, NULL),
(35, '38991045205', 'menu_ajuda', '2025-09-26 10:28:36', 0, NULL),
(36, '38991045205', 'menu_ajuda', '2025-09-26 10:29:51', 0, NULL),
(37, '38991045205', 'menu_ajuda', '2025-09-26 10:30:06', 0, NULL),
(38, '38991045205', 'menu_ajuda', '2025-09-26 10:44:03', 0, NULL),
(39, '38991045205', 'menu_ajuda', '2025-09-26 10:44:06', 0, NULL),
(40, '38991045205', 'menu_ajuda', '2025-09-26 10:44:17', 0, NULL),
(41, '38991045205', 'menu_ajuda', '2025-09-26 10:44:35', 0, NULL),
(42, '38991045205', 'menu_ajuda', '2025-09-26 10:45:33', 0, NULL),
(43, '38991045205', 'menu_ajuda', '2025-09-26 10:46:20', 0, NULL),
(44, '38991045205', 'menu_ajuda', '2025-09-26 10:46:29', 0, NULL),
(45, '38991045205', 'consulta_saldo', '2025-09-26 10:46:48', 0, NULL),
(46, '38991045205', 'consulta_saldo', '2025-09-26 10:47:14', 0, NULL),
(47, '38991045205', 'consulta_saldo', '2025-09-26 10:47:17', 0, NULL),
(48, '38991045205', 'consulta_saldo', '2025-09-26 10:47:29', 0, NULL),
(49, '38991045205', 'consulta_saldo', '2025-09-26 10:48:31', 0, NULL),
(50, '34991191534', 'menu_ajuda', '2025-09-26 10:51:52', 0, NULL),
(51, '34991191534', 'menu_ajuda', '2025-09-26 10:53:51', 0, NULL),
(52, '34991191534', 'consulta_saldo', '2025-09-26 10:55:08', 0, NULL),
(53, '38991045205', 'consulta_saldo', '2025-09-26 10:55:56', 0, NULL),
(54, '38991045205', 'menu_ajuda', '2025-09-26 11:03:47', 0, NULL),
(55, '38991045205', 'consulta_saldo', '2025-09-26 11:12:19', 0, NULL),
(56, '38991045205', 'consulta_saldo', '2025-09-26 11:15:02', 0, NULL),
(57, '38991045205', 'consulta_saldo', '2025-09-26 11:21:26', 0, NULL),
(58, '38991045205', 'consulta_saldo', '2025-09-26 11:23:01', 0, NULL),
(59, '5538991045205', 'consulta_saldo', '2025-09-26 11:24:39', 0, NULL),
(60, '38991045205', 'consulta_saldo', '2025-09-26 11:25:06', 0, NULL),
(61, '38991045205', 'consulta_saldo', '2025-09-26 11:26:15', 0, NULL),
(62, '38991045205', 'menu_ajuda', '2025-09-26 11:29:33', 0, NULL),
(63, '38991045205', 'consulta_saldo', '2025-09-26 11:30:34', 0, NULL),
(64, '38991045205', 'consulta_saldo', '2025-09-26 14:50:38', 0, NULL),
(65, '34998002600', 'consulta_saldo', '2025-09-26 14:50:53', 0, NULL),
(66, '38991045205', 'consulta_saldo', '2025-09-26 14:51:32', 0, NULL),
(67, '38991045205', 'consulta_saldo', '2025-09-26 14:52:11', 0, NULL),
(68, '34991191534', 'consulta_saldo', '2025-09-26 14:52:18', 0, NULL),
(69, '34998002600', 'consulta_saldo', '2025-09-26 14:52:28', 0, NULL),
(70, '34993357697', 'consulta_saldo', '2025-09-26 14:53:11', 0, NULL),
(71, '38991045205', 'consulta_saldo', '2025-09-26 14:56:01', 0, NULL),
(72, '38991045205', 'consulta_saldo', '2025-09-26 14:56:14', 0, NULL),
(73, '38991045205', 'consulta_saldo', '2025-09-26 14:56:29', 0, NULL),
(74, '34998002600', 'consulta_saldo', '2025-09-26 14:56:52', 0, NULL),
(75, '38991045205', 'consulta_saldo', '2025-09-26 15:28:09', 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cashback_movimentacoes`
--

CREATE TABLE `cashback_movimentacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `tipo_operacao` enum('credito','uso','estorno') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `saldo_anterior` decimal(10,2) NOT NULL,
  `saldo_atual` decimal(10,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `transacao_origem_id` int(11) DEFAULT NULL,
  `transacao_uso_id` int(11) DEFAULT NULL,
  `data_operacao` timestamp NULL DEFAULT current_timestamp(),
  `pagamento_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `cashback_movimentacoes`
--

INSERT INTO `cashback_movimentacoes` (`id`, `usuario_id`, `loja_id`, `criado_por`, `tipo_operacao`, `valor`, `saldo_anterior`, `saldo_atual`, `descricao`, `transacao_origem_id`, `transacao_uso_id`, `data_operacao`, `pagamento_id`) VALUES
(337, 9, 59, NULL, 'credito', 120.00, 0.00, 120.00, 'Cashback MVP instantâneo - Código: KC25092615583190179', 686, NULL, '2025-09-26 18:58:35', NULL),
(338, 9, 59, NULL, 'credito', 30.00, 120.00, 150.00, 'Cashback MVP instantâneo - Código: KC25092919062385127', 687, NULL, '2025-09-29 22:06:25', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cashback_notificacoes`
--

CREATE TABLE `cashback_notificacoes` (
  `id` int(11) NOT NULL,
  `transacao_id` int(11) NOT NULL,
  `status` enum('enviada','erro','pendente') NOT NULL DEFAULT 'pendente',
  `observacao` text DEFAULT NULL,
  `data_tentativa` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cashback_notification_retries`
--

CREATE TABLE `cashback_notification_retries` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_error` text DEFAULT NULL,
  `next_retry` datetime DEFAULT NULL,
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cashback_saldos`
--

CREATE TABLE `cashback_saldos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `saldo_disponivel` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_creditado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_usado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `ultima_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `cashback_saldos`
--

INSERT INTO `cashback_saldos` (`id`, `usuario_id`, `loja_id`, `saldo_disponivel`, `total_creditado`, `total_usado`, `data_criacao`, `ultima_atualizacao`) VALUES
(163, 180, 59, 200.00, 200.00, 0.00, '2025-09-15 01:44:47', '2025-09-25 22:53:56'),
(304, 9, 38, 1000.00, 1000.00, 0.00, '2025-09-25 22:56:07', '2025-09-26 14:47:28'),
(305, 140, 59, 1000.00, 1000.00, 0.00, '2025-09-25 22:57:31', '2025-09-25 22:57:31'),
(306, 9, 34, 2.50, 2.50, 0.00, '2025-09-26 10:15:54', '2025-09-26 10:15:54'),
(307, 9, 59, 150.00, 150.00, 0.00, '2025-09-26 18:58:35', '2025-09-29 22:06:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comissoes_status_historico`
--

CREATE TABLE `comissoes_status_historico` (
  `id` int(11) NOT NULL,
  `comissao_id` int(11) NOT NULL,
  `status_anterior` enum('pendente','aprovado','cancelado') NOT NULL,
  `status_novo` enum('pendente','aprovado','cancelado') NOT NULL,
  `observacao` text DEFAULT NULL,
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_2fa`
--

CREATE TABLE `configuracoes_2fa` (
  `id` int(11) NOT NULL,
  `habilitado` tinyint(1) DEFAULT 0,
  `tempo_expiracao_minutos` int(11) DEFAULT 5,
  `max_tentativas` int(11) DEFAULT 3,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_cashback`
--

CREATE TABLE `configuracoes_cashback` (
  `id` int(11) NOT NULL,
  `porcentagem_cliente` decimal(5,2) NOT NULL,
  `porcentagem_admin` decimal(5,2) NOT NULL,
  `porcentagem_loja` decimal(5,2) NOT NULL,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configuracoes_cashback`
--

INSERT INTO `configuracoes_cashback` (`id`, `porcentagem_cliente`, `porcentagem_admin`, `porcentagem_loja`, `data_atualizacao`) VALUES
(1, 10.00, 0.00, 0.00, '2025-09-23 18:33:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_notificacao`
--

CREATE TABLE `configuracoes_notificacao` (
  `id` int(11) NOT NULL,
  `email_nova_transacao` tinyint(1) DEFAULT 1,
  `email_pagamento_aprovado` tinyint(1) DEFAULT 1,
  `email_saldo_disponivel` tinyint(1) DEFAULT 1,
  `email_saldo_baixo` tinyint(1) DEFAULT 1,
  `email_saldo_expirado` tinyint(1) DEFAULT 1,
  `push_nova_transacao` tinyint(1) DEFAULT 1,
  `push_saldo_disponivel` tinyint(1) DEFAULT 1,
  `push_promocoes` tinyint(1) DEFAULT 1,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configuracoes_notificacao`
--

INSERT INTO `configuracoes_notificacao` (`id`, `email_nova_transacao`, `email_pagamento_aprovado`, `email_saldo_disponivel`, `email_saldo_baixo`, `email_saldo_expirado`, `push_nova_transacao`, `push_saldo_disponivel`, `push_promocoes`, `data_atualizacao`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1, '2025-05-19 14:40:18');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_saldo`
--

CREATE TABLE `configuracoes_saldo` (
  `id` int(11) NOT NULL,
  `permitir_uso_saldo` tinyint(1) DEFAULT 1,
  `valor_minimo_uso` decimal(10,2) DEFAULT 1.00,
  `percentual_maximo_uso` decimal(5,2) DEFAULT 100.00,
  `tempo_expiracao_dias` int(11) DEFAULT 0,
  `notificar_saldo_baixo` tinyint(1) DEFAULT 1,
  `limite_saldo_baixo` decimal(10,2) DEFAULT 10.00,
  `permitir_transferencia` tinyint(1) DEFAULT 0,
  `taxa_transferencia` decimal(5,2) DEFAULT 0.00,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configuracoes_saldo`
--

INSERT INTO `configuracoes_saldo` (`id`, `permitir_uso_saldo`, `valor_minimo_uso`, `percentual_maximo_uso`, `tempo_expiracao_dias`, `notificar_saldo_baixo`, `limite_saldo_baixo`, `permitir_transferencia`, `taxa_transferencia`, `data_atualizacao`) VALUES
(1, 1, 10.00, 100.00, 0, 1, 10.00, 0, 0.00, '2025-08-14 18:52:54');

-- --------------------------------------------------------

--
-- Estrutura para tabela `email_campaigns`
--

CREATE TABLE `email_campaigns` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `assunto` varchar(255) NOT NULL,
  `conteudo_html` text NOT NULL,
  `conteudo_texto` text DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_agendamento` datetime DEFAULT NULL,
  `status` enum('rascunho','agendado','enviando','enviado','cancelado') DEFAULT 'rascunho',
  `total_emails` int(11) DEFAULT 0,
  `emails_enviados` int(11) DEFAULT 0,
  `emails_falharam` int(11) DEFAULT 0,
  `criado_por` varchar(100) DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `email_campaigns`
--

INSERT INTO `email_campaigns` (`id`, `titulo`, `assunto`, `conteudo_html`, `conteudo_texto`, `data_criacao`, `data_agendamento`, `status`, `total_emails`, `emails_enviados`, `emails_falharam`, `criado_por`) VALUES
(2, 'Newsletter - Contagem Regressiva Final', '🚀 Últimos dias antes do lançamento da Klube Cash!', '<div style=\"max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif; background: #ffffff;\">\r\n    <!-- Header com gradiente -->\r\n    <div style=\"background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;\">\r\n        <img src=\"https://klubecash.com/assets/images/logobranco.png\" alt=\"Klube Cash\" style=\"height: 60px; margin-bottom: 1rem;\">\r\n        <h1 style=\"margin: 0; font-size: 2rem; font-weight: 800;\">⏰ ÚLTIMA SEMANA!</h1>\r\n        <p style=\"margin: 1rem 0 0; font-size: 1.2rem; opacity: 0.95;\">O lançamento da Klube Cash está chegando!</p>\r\n    </div>\r\n    \r\n    <!-- Conteúdo principal -->\r\n    <div style=\"background: white; padding: 2rem;\">\r\n        <h2 style=\"color: #FF7A00; text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem;\">🎯 Faltam apenas alguns dias!</h2>\r\n        \r\n        <!-- Contagem regressiva visual -->\r\n        <div style=\"background: #FFF7ED; border: 2px solid #FF7A00; border-radius: 12px; padding: 2rem; margin: 1.5rem 0; text-align: center;\">\r\n            <h3 style=\"color: #FF7A00; margin: 0 0 1rem; font-size: 1.2rem;\">📅 Data de Lançamento Oficial:</h3>\r\n            <p style=\"font-size: 2rem; font-weight: 800; color: #FF7A00; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.1);\">9 de Junho • 18:00</p>\r\n            <p style=\"color: #666; margin: 0.5rem 0 0; font-size: 1rem;\">Horário de Brasília</p>\r\n        </div>\r\n        \r\n        <h3 style=\"color: #333; margin: 2rem 0 1rem; font-size: 1.3rem;\">🎁 Benefícios exclusivos para primeiros cadastrados:</h3>\r\n        <div style=\"background: #F8FAFC; border-left: 4px solid #FF7A00; padding: 1.5rem; border-radius: 0 8px 8px 0;\">\r\n            <ul style=\"color: #444; line-height: 2; margin: 0; padding-left: 1.5rem; font-size: 1rem;\">\r\n                \r\n                <li><strong style=\"color: #FF7A00;\">Cashback Garantido</strong> de 5%</li>\r\n                <li><strong style=\"color: #FF7A00;\">Acesso antecipado</strong> às melhores ofertas</li>\r\n                <li><strong style=\"color: #FF7A00;\">Suporte premium</strong></li>\r\n                <li><strong style=\"color: #FF7A00;\">Zero taxas\r\n            </ul>\r\n        </div>\r\n        \r\n        <!-- Call to action -->\r\n        <div style=\"text-align: center; margin: 2.5rem 0;\">\r\n            <a href=\"https://klubecash.com\" style=\"background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3); transition: transform 0.2s ease;\">\r\n                🚀 Estar Pronto no Lançamento\r\n            </a>\r\n        </div>\r\n        \r\n        <!-- Informações adicionais -->\r\n        <div style=\"background: #F0F9FF; border-left: 4px solid #3B82F6; padding: 1.5rem; border-radius: 0 8px 8px 0; margin: 2rem 0;\">\r\n            <h4 style=\"color: #1E40AF; margin: 0 0 0.5rem; font-size: 1.1rem;\">📱 Como funciona:</h4>\r\n            <p style=\"color: #1E3A8A; margin: 0; line-height: 1.6;\">\r\n                1. Faça suas compras normalmente<br>\r\n                2. Apresente seu email ou codigo cadastrado na Klube Cash<br>\r\n                3. Receba dinheiro de volta automaticamente na sua Conta Klube Cash<br>\r\n                4. Use seu cashback em novas compras\r\n            </p>\r\n        </div>\r\n    </div>\r\n    \r\n    <!-- Footer -->\r\n    <div style=\"background: #FFF7ED; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #FFE4B5;\">\r\n        <p style=\"color: #666; font-size: 0.9rem; margin: 0 0 1rem;\">\r\n            Siga-nos nas redes sociais para acompanhar todas as novidades:\r\n        </p>\r\n        <div style=\"margin-bottom: 1rem;\">\r\n            <a href=\"https://instagram.com/klubecash\" style=\"color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;\">📸 Instagram</a>\r\n            <a href=\"https://tiktok.com/@klube.cash\" style=\"color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;\">🎵 TikTok</a>\r\n        </div>\r\n        <p style=\"color: #999; font-size: 0.8rem; margin: 0;\">\r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.<br>\r\n            Você está recebendo este email porque se cadastrou em nossa lista de lançamento.\r\n        </p>\r\n    </div>\r\n</div>', '\r\n    \r\n    \r\n        \r\n        ⏰ ÚLTIMA SEMANA!\r\n        O lançamento da Klube Cash está chegando!\r\n    \r\n    \r\n    \r\n    \r\n        🎯 Faltam apenas alguns dias!\r\n        \r\n        \r\n        \r\n            📅 Data de Lançamento Oficial:\r\n            9 de Junho • 18:00\r\n            Horário de Brasília\r\n        \r\n        \r\n        🎁 Benefícios exclusivos para primeiros cadastrados:\r\n        \r\n            \r\n                \r\n                Cashback Garantido de 5%\r\n                Acesso antecipado às melhores ofertas\r\n                Suporte premium\r\n                Zero taxas\r\n            \r\n        \r\n        \r\n        \r\n        \r\n            \r\n                🚀 Estar Pronto no Lançamento\r\n            \r\n        \r\n        \r\n        \r\n        \r\n            📱 Como funciona:\r\n            \r\n                1. Faça suas compras normalmente\n\r\n                2. Apresente seu email ou codigo cadastrado na Klube Cash\n\r\n                3. Receba dinheiro de volta automaticamente na sua Conta Klube Cash\n\r\n                4. Use seu cashback em novas compras\r\n            \r\n        \r\n    \r\n    \r\n    \r\n    \r\n        \r\n            Siga-nos nas redes sociais para acompanhar todas as novidades:\r\n        \r\n        \r\n            📸 Instagram\r\n            🎵 TikTok\r\n        \r\n        \r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.\n\r\n            Você está recebendo este email porque se cadastrou em nossa lista de lançamento.\r\n        \r\n    \r\n', '2025-06-03 02:13:18', NULL, 'cancelado', 0, 0, 0, 'admin'),
(3, 'Newsletter - Contagem Regressiva Final', '🚀 Últimos dias antes do lançamento da Klube Cash!', '<div style=\"max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif; background: #ffffff;\">\r\n    <!-- Header com gradiente -->\r\n    <div style=\"background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;\">\r\n        <img src=\"https://klubecash.com/assets/images/logobranco.png\" alt=\"Klube Cash\" style=\"height: 60px; margin-bottom: 1rem;\">\r\n        <h1 style=\"margin: 0; font-size: 2rem; font-weight: 800;\">⏰ ÚLTIMA SEMANA!</h1>\r\n        <p style=\"margin: 1rem 0 0; font-size: 1.2rem; opacity: 0.95;\">O lançamento da Klube Cash está chegando!</p>\r\n    </div>\r\n    \r\n    <!-- Conteúdo principal -->\r\n    <div style=\"background: white; padding: 2rem;\">\r\n        <h2 style=\"color: #FF7A00; text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem;\">🎯 Faltam apenas alguns dias!</h2>\r\n        \r\n        <!-- Contagem regressiva visual -->\r\n        <div style=\"background: #FFF7ED; border: 2px solid #FF7A00; border-radius: 12px; padding: 2rem; margin: 1.5rem 0; text-align: center;\">\r\n            <h3 style=\"color: #FF7A00; margin: 0 0 1rem; font-size: 1.2rem;\">📅 Data de Lançamento Oficial:</h3>\r\n            <p style=\"font-size: 2rem; font-weight: 800; color: #FF7A00; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.1);\">9 de Junho • 18:00</p>\r\n            <p style=\"color: #666; margin: 0.5rem 0 0; font-size: 1rem;\">Horário de Brasília</p>\r\n        </div>\r\n        \r\n        <h3 style=\"color: #333; margin: 2rem 0 1rem; font-size: 1.3rem;\">🎁 Benefícios exclusivos para primeiros cadastrados:</h3>\r\n        <div style=\"background: #F8FAFC; border-left: 4px solid #FF7A00; padding: 1.5rem; border-radius: 0 8px 8px 0;\">\r\n            <ul style=\"color: #444; line-height: 2; margin: 0; padding-left: 1.5rem; font-size: 1rem;\">\r\n                \r\n                <li><strong style=\"color: #FF7A00;\">Cashback Garantido</strong> de 5%</li>\r\n                <li><strong style=\"color: #FF7A00;\">Acesso antecipado</strong> às melhores ofertas</li>\r\n                <li><strong style=\"color: #FF7A00;\">Suporte premium</strong></li>\r\n                <li><strong style=\"color: #FF7A00;\">Zero taxas\r\n            </ul>\r\n        </div>\r\n        \r\n        <!-- Call to action -->\r\n        <div style=\"text-align: center; margin: 2.5rem 0;\">\r\n            <a href=\"https://klubecash.com\" style=\"background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3); transition: transform 0.2s ease;\">\r\n                🚀 Estar Pronto no Lançamento\r\n            </a>\r\n        </div>\r\n        \r\n        <!-- Informações adicionais -->\r\n        <div style=\"background: #F0F9FF; border-left: 4px solid #3B82F6; padding: 1.5rem; border-radius: 0 8px 8px 0; margin: 2rem 0;\">\r\n            <h4 style=\"color: #1E40AF; margin: 0 0 0.5rem; font-size: 1.1rem;\">📱 Como funciona:</h4>\r\n            <p style=\"color: #1E3A8A; margin: 0; line-height: 1.6;\">\r\n                1. Faça suas compras normalmente<br>\r\n                2. Apresente seu email ou codigo cadastrado na Klube Cash<br>\r\n                3. Receba dinheiro de volta automaticamente na sua Conta Klube Cash<br>\r\n                4. Use seu cashback em novas compras\r\n            </p>\r\n        </div>\r\n    </div>\r\n    \r\n    <!-- Footer -->\r\n    <div style=\"background: #FFF7ED; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #FFE4B5;\">\r\n        <p style=\"color: #666; font-size: 0.9rem; margin: 0 0 1rem;\">\r\n            Siga-nos nas redes sociais para acompanhar todas as novidades:\r\n        </p>\r\n        <div style=\"margin-bottom: 1rem;\">\r\n            <a href=\"https://instagram.com/klubecash\" style=\"color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;\">📸 Instagram</a>\r\n            <a href=\"https://tiktok.com/@klube.cash\" style=\"color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;\">🎵 TikTok</a>\r\n        </div>\r\n        <p style=\"color: #999; font-size: 0.8rem; margin: 0;\">\r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.<br>\r\n            Você está recebendo este email porque se cadastrou em nossa lista de lançamento.\r\n        </p>\r\n    </div>\r\n</div>', '\r\n    \r\n    \r\n        \r\n        ⏰ ÚLTIMA SEMANA!\r\n        O lançamento da Klube Cash está chegando!\r\n    \r\n    \r\n    \r\n    \r\n        🎯 Faltam apenas alguns dias!\r\n        \r\n        \r\n        \r\n            📅 Data de Lançamento Oficial:\r\n            9 de Junho • 18:00\r\n            Horário de Brasília\r\n        \r\n        \r\n        🎁 Benefícios exclusivos para primeiros cadastrados:\r\n        \r\n            \r\n                \r\n                Cashback Garantido de 5%\r\n                Acesso antecipado às melhores ofertas\r\n                Suporte premium\r\n                Zero taxas\r\n            \r\n        \r\n        \r\n        \r\n        \r\n            \r\n                🚀 Estar Pronto no Lançamento\r\n            \r\n        \r\n        \r\n        \r\n        \r\n            📱 Como funciona:\r\n            \r\n                1. Faça suas compras normalmente\n\r\n                2. Apresente seu email ou codigo cadastrado na Klube Cash\n\r\n                3. Receba dinheiro de volta automaticamente na sua Conta Klube Cash\n\r\n                4. Use seu cashback em novas compras\r\n            \r\n        \r\n    \r\n    \r\n    \r\n    \r\n        \r\n            Siga-nos nas redes sociais para acompanhar todas as novidades:\r\n        \r\n        \r\n            📸 Instagram\r\n            🎵 TikTok\r\n        \r\n        \r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.\n\r\n            Você está recebendo este email porque se cadastrou em nossa lista de lançamento.\r\n        \r\n    \r\n', '2025-06-03 02:13:26', NULL, 'cancelado', 0, 0, 0, 'admin'),
(4, 'Newsletter - Dicas de Cashback', '💰 Como maximizar seu cashback - Dicas exclusivas da Klube Cash', '<div style=\"max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif; background: #ffffff;\">\r\n    <!-- Header -->\r\n    <div style=\"background: linear-gradient(135deg, #10B981, #34D399); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;\">\r\n        <img src=\"https://klubecash.com/assets/images/logobranco.png\" alt=\"Klube Cash\" style=\"height: 60px; margin-bottom: 1rem;\">\r\n        <h1 style=\"margin: 0; font-size: 1.8rem; font-weight: 800;\">💡 Dicas de Ouro para Cashback</h1>\r\n        <p style=\"margin: 1rem 0 0; font-size: 1.1rem; opacity: 0.95;\">Aprenda a maximizar seus ganhos!</p>\r\n    </div>\r\n    \r\n    <!-- Conteúdo -->\r\n    <div style=\"background: white; padding: 2rem;\">\r\n        <h2 style=\"color: #059669; margin-bottom: 1.5rem; font-size: 1.4rem;\">🎯 Como ganhar ainda mais dinheiro de volta</h2>\r\n        \r\n        <p style=\"color: #666; line-height: 1.8; margin-bottom: 2rem; font-size: 1rem;\">\r\n            Preparamos dicas exclusivas para você se tornar um expert em cashback e maximizar seus ganhos desde o primeiro dia na Klube Cash!\r\n        </p>\r\n        \r\n        <!-- Dicas -->\r\n        <div style=\"margin: 2rem 0;\">\r\n            <!-- Dica 1 -->\r\n            <div style=\"background: #F0FDF4; border-left: 4px solid #10B981; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;\">\r\n                <h3 style=\"color: #065F46; margin: 0 0 1rem; font-size: 1.2rem;\">🛒 Dica #1: Planeje suas Compras</h3>\r\n                <p style=\"color: #064E3B; margin: 0; line-height: 1.6;\">\r\n                    <strong>Concentre suas compras</strong> em dias específicos da semana. Muitas lojas oferecem cashback extra às quartas e sextas-feiras. Você pode ganhar até <strong>12% de volta</strong> em vez dos 5% padrão!\r\n                </p>\r\n            </div>\r\n            \r\n            <!-- Dica 2 -->\r\n            <div style=\"background: #FEF7FF; border-left: 4px solid #A855F7; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;\">\r\n                <h3 style=\"color: #7C2D12; margin: 0 0 1rem; font-size: 1.2rem;\">💳 Dica #2: Combine Promoções</h3>\r\n                <p style=\"color: #92400E; margin: 0; line-height: 1.6;\">\r\n                    Use cupons de desconto das lojas <strong>junto</strong> com o cashback da Klube Cash. É desconto duplo! Já tivemos clientes que economizaram 30% em uma única compra combinando ofertas.\r\n                </p>\r\n            </div>\r\n            \r\n            <!-- Dica 3 -->\r\n            <div style=\"background: #FFF7ED; border-left: 4px solid #FF7A00; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;\">\r\n                <h3 style=\"color: #C2410C; margin: 0 0 1rem; font-size: 1.2rem;\">📱 Dica #3: Use o App (em breve)</h3>\r\n                <p style=\"color: #EA580C; margin: 0; line-height: 1.6;\">\r\n                    Nosso app móvel terá <strong>notificações em tempo real</strong> quando você estiver perto de lojas parceiras. Você nunca mais vai esquecer de usar seu cashback!\r\n                </p>\r\n            </div>\r\n            \r\n            <!-- Dica 4 -->\r\n            <div style=\"background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;\">\r\n                <h3 style=\"color: #1D4ED8; margin: 0 0 1rem; font-size: 1.2rem;\">🎁 Dica #4: Indique Amigos</h3>\r\n                <p style=\"color: #1E40AF; margin: 0; line-height: 1.6;\">\r\n                    Para cada amigo que você indicar, <strong>ambos ganham R$ 15 de bônus</strong>. É uma maneira fácil de aumentar seu saldo sem gastar nada!\r\n                </p>\r\n            </div>\r\n        </div>\r\n        \r\n        <!-- Exemplo prático -->\r\n        <div style=\"background: #F8FAFC; border: 2px solid #E2E8F0; border-radius: 12px; padding: 2rem; margin: 2rem 0;\">\r\n            <h3 style=\"color: #374151; margin: 0 0 1rem; font-size: 1.3rem;\">📊 Exemplo Prático</h3>\r\n            <p style=\"color: #6B7280; margin: 0 0 1rem; line-height: 1.6;\">\r\n                <strong>Situação:</strong> Compra de R$ 200 em roupas numa quarta-feira\r\n            </p>\r\n            <ul style=\"color: #4B5563; line-height: 1.8; margin: 0; padding-left: 1.5rem;\">\r\n                <li>Cashback padrão (5%): R$ 10</li>\r\n                <li>Bônus dia da semana (+2%): R$ 4</li>\r\n                <li>Cupom da loja (15% desconto): R$ 30</li>\r\n                <li><strong style=\"color: #10B981;\">Total economizado: R$ 44 (22% da compra!)</strong></li>\r\n            </ul>\r\n        </div>\r\n        \r\n        <!-- CTA -->\r\n        <div style=\"text-align: center; margin: 2.5rem 0;\">\r\n            <a href=\"https://klubecash.com\" style=\"background: linear-gradient(135deg, #10B981, #34D399); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);\">\r\n                💰 Quero Começar a Economizar\r\n            </a>\r\n        </div>\r\n        \r\n        <div style=\"background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 8px; padding: 1.5rem; margin: 2rem 0;\">\r\n            <p style=\"color: #92400E; margin: 0; text-align: center; font-weight: 600;\">\r\n                💡 <strong>Lembre-se:</strong> Essas dicas funcionam melhor quando usadas em conjunto. \r\n                Teste diferentes combinações e descubra qual funciona melhor para seu perfil de compras!\r\n            </p>\r\n        </div>\r\n    </div>\r\n    \r\n    <!-- Footer -->\r\n    <div style=\"background: #F0FDF4; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #BBF7D0;\">\r\n        <p style=\"color: #166534; font-size: 0.9rem; margin: 0 0 1rem; font-weight: 600;\">\r\n            🏆 Compartilhe essas dicas e ajude seus amigos a economizar também!\r\n        </p>\r\n        <div style=\"margin-bottom: 1rem;\">\r\n            <a href=\"https://instagram.com/klubecash\" style=\"color: #10B981; text-decoration: none; margin: 0 1rem; font-weight: 600;\">📸 Instagram</a>\r\n            <a href=\"https://tiktok.com/@klube.cash\" style=\"color: #10B981; text-decoration: none; margin: 0 1rem; font-weight: 600;\">🎵 TikTok</a>\r\n        </div>\r\n        <p style=\"color: #999; font-size: 0.8rem; margin: 0;\">\r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.\r\n        </p>\r\n    </div>\r\n</div>', '\r\n    \r\n    \r\n        \r\n        💡 Dicas de Ouro para Cashback\r\n        Aprenda a maximizar seus ganhos!\r\n    \r\n    \r\n    \r\n    \r\n        🎯 Como ganhar ainda mais dinheiro de volta\r\n        \r\n        \r\n            Preparamos dicas exclusivas para você se tornar um expert em cashback e maximizar seus ganhos desde o primeiro dia na Klube Cash!\r\n        \r\n        \r\n        \r\n        \r\n            \r\n            \r\n                🛒 Dica #1: Planeje suas Compras\r\n                \r\n                    Concentre suas compras em dias específicos da semana. Muitas lojas oferecem cashback extra às quartas e sextas-feiras. Você pode ganhar até 12% de volta em vez dos 5% padrão!\r\n                \r\n            \r\n            \r\n            \r\n            \r\n                💳 Dica #2: Combine Promoções\r\n                \r\n                    Use cupons de desconto das lojas junto com o cashback da Klube Cash. É desconto duplo! Já tivemos clientes que economizaram 30% em uma única compra combinando ofertas.\r\n                \r\n            \r\n            \r\n            \r\n            \r\n                📱 Dica #3: Use o App (em breve)\r\n                \r\n                    Nosso app móvel terá notificações em tempo real quando você estiver perto de lojas parceiras. Você nunca mais vai esquecer de usar seu cashback!\r\n                \r\n            \r\n            \r\n            \r\n            \r\n                🎁 Dica #4: Indique Amigos\r\n                \r\n                    Para cada amigo que você indicar, ambos ganham R$ 15 de bônus. É uma maneira fácil de aumentar seu saldo sem gastar nada!\r\n                \r\n            \r\n        \r\n        \r\n        \r\n        \r\n            📊 Exemplo Prático\r\n            \r\n                Situação: Compra de R$ 200 em roupas numa quarta-feira\r\n            \r\n            \r\n                Cashback padrão (5%): R$ 10\r\n                Bônus dia da semana (+2%): R$ 4\r\n                Cupom da loja (15% desconto): R$ 30\r\n                Total economizado: R$ 44 (22% da compra!)\r\n            \r\n        \r\n        \r\n        \r\n        \r\n            \r\n                💰 Quero Começar a Economizar\r\n            \r\n        \r\n        \r\n        \r\n            \r\n                💡 Lembre-se: Essas dicas funcionam melhor quando usadas em conjunto. \r\n                Teste diferentes combinações e descubra qual funciona melhor para seu perfil de compras!\r\n            \r\n        \r\n    \r\n    \r\n    \r\n    \r\n        \r\n            🏆 Compartilhe essas dicas e ajude seus amigos a economizar também!\r\n        \r\n        \r\n            📸 Instagram\r\n            🎵 TikTok\r\n        \r\n        \r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.\r\n        \r\n    \r\n', '2025-06-03 02:14:28', NULL, 'cancelado', 0, 0, 0, 'admin'),
(5, 'Newsletter - Contagem Regressiva Final', '🚀 Últimos dias antes do lançamento da Klube Cash!', '<meta charset=\"UTF-8\">\r\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n<div style=\"max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif;\">\r\n    <div style=\"background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;\">\r\n        <img src=\"https://klubecash.com/assets/images/logobranco.png\" alt=\"Klube Cash\" style=\"height: 60px; margin-bottom: 1rem;\">\r\n        <h1 style=\"margin: 0; font-size: 1.8rem; font-weight: 800;\">A KlubeCash Chegou!</h1>\r\n        <p style=\"margin: 1rem 0 0; font-size: 1.1rem; opacity: 0.95;\">Você tem acesso antecipado ao sistema.</p>\r\n    </div>\r\n    \r\n    <div style=\"background: white; padding: 2rem;\">\r\n        <h2 style=\"color: #333; margin-bottom: 1.5rem;\">Olá, futuro membro da Klube Cash! 👋</h2>\r\n        \r\n        <p style=\"color: #666; line-height: 1.8; margin-bottom: 2rem;\">\r\n            O KlubeCash está quase pronto para ser lançado e você foi um dos escolhidos para ter <strong>acesso antecipado</strong>! Como os primeiros inscritos têm prioridade, registre-se agora e seja um dos pioneiros a descobrir todas as novidades e vantagens incríveis que preparamos.\r\n        </p>\r\n        \r\n        <div style=\"background: #FFF7ED; border-left: 4px solid #FF7A00; padding: 1.5rem; border-radius: 0 8px 8px 0; margin: 2rem 0;\">\r\n            <h3 style=\"color: #EA580C; margin: 0 0 1rem;\">✨ Seja um Pioneiro KlubeCash!</h3>\r\n            <p style=\"color: #9A3412; margin: 0; line-height: 1.6;\">\r\n                Ao se registrar no acesso antecipado, você garante sua vaga para explorar em primeira mão uma plataforma pensada para revolucionar a sua forma de ganhar cashback e aproveitar benefícios exclusivos. Não fique de fora!\r\n            </p>\r\n        </div>\r\n        \r\n        <h3 style=\"color: #FF7A00; margin: 2rem 0 1rem;\">📋 Por que se registrar agora?</h3>\r\n        <ul style=\"color: #666; line-height: 1.8; margin: 0 0 2rem; padding-left: 1.5rem;\">\r\n            <li><strong>Acesso Exclusivo:</strong> Garanta sua entrada VIP antes do lançamento oficial.</li>\r\n            <li><strong>Vantagens Únicas:</strong> Descubra funcionalidades e ofertas especiais para os primeiros membros.</li>\r\n            <li><strong>Novidades em Primeira Mão:</strong> Fique sabendo de tudo sobre o KlubeCash antes de todo mundo.</li>\r\n        </ul>\r\n        \r\n        <div style=\"text-align: center; margin: 2.5rem 0;\">\r\n            <a href=\"https://klubecash.com/registro\" style=\"background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3);\">\r\n                🚀 Quero Acesso Antecipado!\r\n            </a>\r\n        </div>\r\n        \r\n        <p style=\"color: #666; line-height: 1.6; margin: 1rem 0;\">\r\n            Estamos ansiosos para ter você conosco desde o início dessa jornada! Clique no botão acima e faça parte da comunidade KlubeCash. As vagas para o acesso antecipado são limitadas!\r\n        </p>\r\n    </div>\r\n    \r\n    <div style=\"background: #FFF7ED; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #FFE4B5;\">\r\n        <p style=\"color: #666; font-size: 0.9rem; margin: 0 0 1rem;\">\r\n            Siga-nos nas redes sociais para mais novidades!\r\n        </p>\r\n        <div style=\"margin-bottom: 1rem;\">\r\n            <a href=\"https://instagram.com/klubecash\" style=\"color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;\">📸 Instagram</a>\r\n            <a href=\"https://tiktok.com/@klube.cash\" style=\"color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;\">🎵 TikTok</a>\r\n        </div>\r\n        <p style=\"color: #999; font-size: 0.8rem; margin: 0;\">\r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.<br>\r\n            Você está recebendo este email porque se cadastrou em nossa lista de lançamento ou demonstrou interesse no KlubeCash.\r\n        </p>\r\n    </div>\r\n</div>', '\r\n\r\n\r\n    \r\n        \r\n        A KlubeCash Chegou!\r\n        Você tem acesso antecipado ao sistema.\r\n    \r\n    \r\n    \r\n        Olá, futuro membro da Klube Cash! 👋\r\n        \r\n        \r\n            O KlubeCash está quase pronto para ser lançado e você foi um dos escolhidos para ter acesso antecipado! Como os primeiros inscritos têm prioridade, registre-se agora e seja um dos pioneiros a descobrir todas as novidades e vantagens incríveis que preparamos.\r\n        \r\n        \r\n        \r\n            ✨ Seja um Pioneiro KlubeCash!\r\n            \r\n                Ao se registrar no acesso antecipado, você garante sua vaga para explorar em primeira mão uma plataforma pensada para revolucionar a sua forma de ganhar cashback e aproveitar benefícios exclusivos. Não fique de fora!\r\n            \r\n        \r\n        \r\n        📋 Por que se registrar agora?\r\n        \r\n            Acesso Exclusivo: Garanta sua entrada VIP antes do lançamento oficial.\r\n            Vantagens Únicas: Descubra funcionalidades e ofertas especiais para os primeiros membros.\r\n            Novidades em Primeira Mão: Fique sabendo de tudo sobre o KlubeCash antes de todo mundo.\r\n        \r\n        \r\n        \r\n            \r\n                🚀 Quero Acesso Antecipado!\r\n            \r\n        \r\n        \r\n        \r\n            Estamos ansiosos para ter você conosco desde o início dessa jornada! Clique no botão acima e faça parte da comunidade KlubeCash. As vagas para o acesso antecipado são limitadas!\r\n        \r\n    \r\n    \r\n    \r\n        \r\n            Siga-nos nas redes sociais para mais novidades!\r\n        \r\n        \r\n            📸 Instagram\r\n            🎵 TikTok\r\n        \r\n        \r\n            &copy; 2025 Klube Cash. Todos os direitos reservados.\n\r\n            Você está recebendo este email porque se cadastrou em nossa lista de lançamento ou demonstrou interesse no KlubeCash.\r\n        \r\n    \r\n', '2025-06-03 02:15:35', '2025-06-02 23:16:00', 'agendado', 25, 0, 0, 'admin');

-- --------------------------------------------------------

--
-- Estrutura para tabela `email_envios`
--

CREATE TABLE `email_envios` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('pendente','enviado','falhou','bounce') DEFAULT 'pendente',
  `tentativas` int(11) DEFAULT 0,
  `data_envio` timestamp NULL DEFAULT NULL,
  `erro_mensagem` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `assunto_padrao` varchar(255) DEFAULT NULL,
  `conteudo_html` text NOT NULL,
  `tipo` enum('newsletter','promocional','informativo') DEFAULT 'newsletter',
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ip_block`
--

CREATE TABLE `ip_block` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `block_expiry` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas`
--

CREATE TABLE `lojas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nome_fantasia` varchar(100) NOT NULL,
  `razao_social` varchar(150) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `categoria` varchar(50) DEFAULT 'Outros',
  `porcentagem_cashback` decimal(5,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('pendente','aprovado','rejeitado') DEFAULT 'pendente',
  `observacao` text DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp(),
  `data_aprovacao` timestamp NULL DEFAULT NULL,
  `porcentagem_cliente` decimal(5,2) DEFAULT 5.00 COMMENT 'Percentual de cashback para o cliente (%)',
  `porcentagem_admin` decimal(5,2) DEFAULT 5.00 COMMENT 'Percentual de comissão para o admin/plataforma (%)',
  `cashback_ativo` tinyint(1) DEFAULT 1 COMMENT 'Se a loja oferece cashback (0=inativo, 1=ativo)',
  `data_config_cashback` timestamp NULL DEFAULT NULL COMMENT 'Data da última configuração de cashback'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `lojas`
--

INSERT INTO `lojas` (`id`, `usuario_id`, `nome_fantasia`, `razao_social`, `cnpj`, `email`, `senha_hash`, `telefone`, `categoria`, `porcentagem_cashback`, `descricao`, `website`, `logo`, `status`, `observacao`, `data_cadastro`, `data_aprovacao`, `porcentagem_cliente`, `porcentagem_admin`, `cashback_ativo`, `data_config_cashback`) VALUES
(34, 55, 'Kaua Matheus da Silva Lopes', 'Kaua Matheus da Silva Lopes', '59826857000108', 'kaua@syncholding.com.br', NULL, '(38) 99104-5205', 'Serviços', 5.00, 'Criador de Sites', 'https://syncholding.com.br', NULL, 'aprovado', NULL, '2025-05-25 19:17:34', '2025-09-14 11:25:48', 5.00, 5.00, 1, '2025-09-13 10:56:04'),
(38, 63, 'KLUBE DIGITAL', 'Klube Digital Estratégia e Performance Ltda.', '18431312000115', 'acessoriafredericofagundes@gmail.com', NULL, '(34) 99335-7697', 'Serviços', 5.00, '', '', NULL, 'aprovado', NULL, '2025-06-07 16:11:42', '2025-06-08 19:36:33', 2.50, 2.50, 1, '2025-08-30 01:23:59'),
(59, 159, 'Sync Holding', 'Kaua Matheus da Silva Lopes', '59826857000109', 'kauamathes123487654@gmail.com', NULL, '(34) 99800-2600', 'Serviços', 10.00, '', 'https://syncholding.com.br', NULL, 'aprovado', NULL, '2025-08-15 13:52:55', '2025-08-15 13:53:38', 10.00, 0.00, 1, '2025-09-23 18:33:34'),
(60, 173, 'ELITE SEMIJOIAS MOZAR FRANCISCO LUIZ ME', 'MOZAR FRANCISCO LUIZ', '18381956000146', 'elitesemijoiaspatosdeminas@gmail.com', NULL, '(34) 99217-2404', 'Outros', 10.00, 'ATACADO DE SEMIJOIAS', '', NULL, 'aprovado', NULL, '2025-08-29 17:22:01', '2025-08-29 18:03:45', 10.00, 0.00, 1, '2025-08-30 01:57:18'),
(61, 175, 'Digo.com', 'Digo Comércio e Varejo', '62491384000140', 'digovarejo@gmail.com', NULL, '(11) 97088-3167', 'Eletrônicos', 10.00, 'Varejista iPhone', '', NULL, 'aprovado', NULL, '2025-09-13 14:49:08', '2025-09-13 15:15:48', 5.00, 5.00, 1, NULL),
(62, 177, 'RSF SONHO DE NOIVA EVENTOS E NEGOCIOS LTDA', 'RSF SONHO DE NOIVA EVENTOS E NEGOCIOS LTDA', '22640009000108', 'cleacasamentos@gmail.com', NULL, '(85) 99632-4231', 'Serviços', 10.00, '', '', NULL, 'aprovado', NULL, '2025-09-14 19:47:52', '2025-09-14 19:55:58', 5.00, 5.00, 1, NULL),
(63, NULL, 'dasfDA', 'DSAsdsa', '22640009000104', 'cleacasamentos@gmail.com.br', NULL, '(35) 45454-4544', 'Eletrônicos', 10.00, '', 'https://cleacasamentos.com.br', NULL, 'pendente', NULL, '2025-09-14 19:51:57', NULL, 5.00, 5.00, 1, NULL),
(64, NULL, 'teste', 'ds', '22640009000110', 'kauanupix@gmail.com', NULL, '(85) 99632-4231', 'Outros', 10.00, '', 'https://cleacasamentos.com.br', NULL, 'pendente', NULL, '2025-09-14 20:01:31', NULL, 5.00, 5.00, 1, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas_contato`
--

CREATE TABLE `lojas_contato` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas_endereco`
--

CREATE TABLE `lojas_endereco` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `lojas_endereco`
--

INSERT INTO `lojas_endereco` (`id`, `loja_id`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`) VALUES
(12, 38, '38706-325', 'Rua Doutor Dolor Borges', '300', '', 'Planalto', 'Patos de Minas', 'MG'),
(29, 59, '38705-376', 'Rua Francisco Braga da Mota', '146', 'Ap 101', 'jardim panoramico', 'Patos de Minas', 'MG'),
(30, 60, '38700-973', 'Rua Major Gote', '1800', 'CAIXA POSTAL 2063', 'CENTRO', 'Patos de Minas - MG', 'MG'),
(31, 61, '12970-000', 'Rua das Araucárias', '55', '', 'Ipe', 'Piracaia', 'SP'),
(32, 62, '60713-240', 'R AMERICO ROCHA LIMA', '584', '', 'Manoel Satiro', 'Fortaleza', 'CE');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas_favoritas`
--

CREATE TABLE `lojas_favoritas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `n8n_webhook_logs`
--

CREATE TABLE `n8n_webhook_logs` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message_sent` tinyint(1) DEFAULT 0,
  `n8n_response` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('info','success','warning','error') DEFAULT 'info',
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `lida` tinyint(1) DEFAULT 0,
  `data_leitura` timestamp NULL DEFAULT NULL,
  `link` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_comissao`
--

CREATE TABLE `pagamentos_comissao` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `metodo_pagamento` varchar(50) NOT NULL,
  `numero_referencia` varchar(100) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `observacao_admin` text DEFAULT NULL,
  `data_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_aprovacao` timestamp NULL DEFAULT NULL,
  `status` enum('pendente','aprovado','rejeitado','pix_aguardando','pix_expirado') DEFAULT 'pendente',
  `pix_charge_id` varchar(255) DEFAULT NULL,
  `pix_qr_code` text DEFAULT NULL,
  `pix_qr_code_image` text DEFAULT NULL,
  `pix_paid_at` timestamp NULL DEFAULT NULL,
  `mp_payment_id` varchar(255) DEFAULT NULL,
  `mp_qr_code` text DEFAULT NULL,
  `mp_qr_code_base64` longtext DEFAULT NULL,
  `mp_status` varchar(50) DEFAULT 'pending',
  `openpix_charge_id` varchar(255) DEFAULT NULL,
  `openpix_qr_code` text DEFAULT NULL,
  `openpix_qr_code_image` varchar(500) DEFAULT NULL,
  `openpix_correlation_id` varchar(255) DEFAULT NULL,
  `openpix_status` varchar(50) DEFAULT NULL,
  `openpix_paid_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_devolucoes`
--

CREATE TABLE `pagamentos_devolucoes` (
  `id` int(11) NOT NULL,
  `pagamento_id` int(11) NOT NULL,
  `mp_payment_id` varchar(255) NOT NULL,
  `mp_refund_id` varchar(255) DEFAULT NULL,
  `valor_devolucao` decimal(10,2) NOT NULL,
  `motivo` text NOT NULL,
  `tipo` enum('total','parcial') DEFAULT 'total',
  `status` enum('solicitado','processando','aprovado','rejeitado','erro') DEFAULT 'solicitado',
  `solicitado_por` int(11) NOT NULL,
  `aprovado_por` int(11) DEFAULT NULL,
  `data_solicitacao` timestamp NULL DEFAULT current_timestamp(),
  `data_processamento` timestamp NULL DEFAULT NULL,
  `observacao_admin` text DEFAULT NULL,
  `dados_mp` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_transacoes`
--

CREATE TABLE `pagamentos_transacoes` (
  `id` int(11) NOT NULL,
  `pagamento_id` int(11) NOT NULL,
  `transacao_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamento_transacoes`
--

CREATE TABLE `pagamento_transacoes` (
  `id` int(11) NOT NULL,
  `pagamento_id` int(11) NOT NULL,
  `transacao_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `recuperacao_senha`
--

CREATE TABLE `recuperacao_senha` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `data_expiracao` timestamp NOT NULL,
  `usado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sessoes`
--

CREATE TABLE `sessoes` (
  `id` varchar(255) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_inicio` timestamp NULL DEFAULT current_timestamp(),
  `data_expiracao` timestamp NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `store_balance_payments`
--

CREATE TABLE `store_balance_payments` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `metodo_pagamento` varchar(50) NOT NULL DEFAULT 'pix',
  `numero_referencia` varchar(100) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `status` enum('pendente','em_processamento','aprovado','rejeitado') DEFAULT 'pendente',
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_processamento` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes_cashback`
--

CREATE TABLE `transacoes_cashback` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `valor_cashback` decimal(10,2) NOT NULL,
  `valor_cliente` decimal(10,2) NOT NULL,
  `valor_admin` decimal(10,2) NOT NULL,
  `valor_loja` decimal(10,2) NOT NULL,
  `codigo_transacao` varchar(50) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `data_transacao` timestamp NULL DEFAULT current_timestamp(),
  `data_criacao_usuario` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('pendente','aprovado','cancelado','pagamento_pendente') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `transacoes_cashback`
--

INSERT INTO `transacoes_cashback` (`id`, `usuario_id`, `loja_id`, `criado_por`, `valor_total`, `valor_cashback`, `valor_cliente`, `valor_admin`, `valor_loja`, `codigo_transacao`, `descricao`, `data_transacao`, `data_criacao_usuario`, `status`) VALUES
(367, 180, 59, NULL, 800.00, 200.00, 200.00, 0.00, 0.00, 'KC25091422441319527-SH', 'Desenvolvimento do Site e sistemas Web, Clea Casamentos', '2025-09-14 22:42:00', '2025-09-15 01:44:47', 'aprovado'),
(680, 180, 62, NULL, 50.00, 5.00, 2.50, 2.50, 0.00, 'KC25092420385019033', 'alugo um acessório calça social.', '2025-09-24 20:35:00', '2025-09-24 23:39:46', 'pendente'),
(686, 9, 59, NULL, 1200.00, 120.00, 120.00, 0.00, 0.00, 'KC25092615583190179', '', '2025-09-26 15:58:00', '2025-09-26 18:58:35', 'aprovado'),
(687, 9, 59, NULL, 300.00, 30.00, 30.00, 0.00, 0.00, 'KC25092919062385127', '', '2025-09-29 19:06:00', '2025-09-29 22:06:25', 'aprovado');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes_comissao`
--

CREATE TABLE `transacoes_comissao` (
  `id` int(11) NOT NULL,
  `tipo_usuario` enum('admin','loja') NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `transacao_id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `valor_comissao` decimal(10,2) NOT NULL,
  `data_transacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pendente','aprovado','cancelado') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes_saldo_usado`
--

CREATE TABLE `transacoes_saldo_usado` (
  `id` int(11) NOT NULL,
  `transacao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `valor_usado` decimal(10,2) NOT NULL,
  `data_uso` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes_status_historico`
--

CREATE TABLE `transacoes_status_historico` (
  `id` int(11) NOT NULL,
  `transacao_id` int(11) NOT NULL,
  `status_anterior` enum('pendente','aprovado','cancelado') NOT NULL,
  `status_novo` enum('pendente','aprovado','cancelado') NOT NULL,
  `observacao` text DEFAULT NULL,
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `status` enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
  `tipo` enum('cliente','admin','loja','funcionario') DEFAULT 'cliente',
  `senat` enum('Sim','Não') DEFAULT 'Não',
  `tipo_cliente` enum('completo','visitante') DEFAULT 'completo',
  `loja_criadora_id` int(11) DEFAULT NULL,
  `google_id` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `provider` enum('local','google') DEFAULT 'local',
  `email_verified` tinyint(1) DEFAULT 0,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_code` varchar(6) DEFAULT NULL,
  `two_factor_expires` datetime DEFAULT NULL,
  `two_factor_verified` tinyint(1) DEFAULT 0,
  `tentativas_2fa` int(11) DEFAULT 0,
  `bloqueado_2fa_ate` timestamp NULL DEFAULT NULL,
  `ultimo_2fa_enviado` timestamp NULL DEFAULT NULL,
  `loja_vinculada_id` int(11) DEFAULT NULL,
  `subtipo_funcionario` enum('funcionario','gerente','coordenador','assistente','financeiro','vendedor') DEFAULT 'funcionario' COMMENT 'Campo apenas para organização interna - não afeta permissões',
  `mvp` enum('sim','nao') DEFAULT 'nao'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `cpf`, `senha_hash`, `data_criacao`, `ultimo_login`, `status`, `tipo`, `senat`, `tipo_cliente`, `loja_criadora_id`, `google_id`, `avatar_url`, `provider`, `email_verified`, `two_factor_enabled`, `two_factor_code`, `two_factor_expires`, `two_factor_verified`, `tentativas_2fa`, `bloqueado_2fa_ate`, `ultimo_2fa_enviado`, `loja_vinculada_id`, `subtipo_funcionario`, `mvp`) VALUES
(9, 'Kaua Matheus da Silva Lope', 'kauamatheus920@gmail.com', '38991045205', '15692134616', '$2y$10$ZBHPPEjv69ihoxjJatuJZefND4d0UNGpzK.UG1fji3BeETLymm7eu', '2025-05-05 19:45:04', '2025-09-27 12:06:20', 'ativo', 'cliente', 'Sim', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(10, 'Frederico', 'repertoriofredericofagundes@gmail.com', NULL, NULL, '$2y$10$yGjHS8rJq49AuLeuVrZHkOUPSkzNLs79A6H52HwwY8DpzLA2A95Ay', '2025-05-05 21:45:46', '2025-09-15 18:30:09', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(11, 'Kaua Lopés', 'kaua@klubecash.com', NULL, NULL, '$2y$10$3cp74UJto1IK9R4f8wx.su3HR.SdXKPLotS4OLck7BxMLOhuJMtHq', '2025-05-07 12:19:05', '2025-09-29 20:31:08', 'ativo', 'admin', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(55, 'Matheus', 'kauamathes123487654@gmail.com', '34991191534', NULL, '$2y$10$VwSfpE6zvr72HI19RLFLF.Dw4VKMjbGajc5l6mN3jQiaoHK1GUR0u', '2025-05-25 19:17:34', '2025-09-26 10:14:16', 'ativo', 'loja', 'Sim', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'sim'),
(61, 'Frederico Fagundes', 'fredericofagundes0@gmail.com', NULL, NULL, '$2y$10$Lcszebxu3vPCg4dNkDhP7eAvk07mvjEvFLNz4pFYdMveo0skeNFWi', '2025-06-05 17:48:45', '2025-09-27 22:33:11', 'ativo', 'admin', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(63, 'KLUBE DIGITAL', 'acessoriafredericofagundes@gmail.com', '(34) 99335-7697', NULL, '$2y$10$VuDfT8bieSTLToSbmd3EzOVkmwNLYeC9itIfm2kxl3f54OpnZpd5O', '2025-06-07 16:11:42', '2025-09-25 19:36:19', 'ativo', 'loja', 'Sim', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'sim'),
(71, 'Roberto Magalhães Corrêa ', 'ropatosmg@gmail.com', '5534993171602', NULL, '$2y$10$77e0qthXH0AJkZFGJR0APu9fifxY/M8BvkNOGrHMBMBmAv7W3SohO', '2025-06-10 00:08:12', '2025-06-10 00:08:51', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(72, 'Sabrina', 'sabrina290623@gmail.com', '(34) 99842-3591', NULL, '$2y$10$1FNgzRYI0AbiCYymdAgBlOWe2uIJn.PwU24.AUe3UP7pf5bA1ImJO', '2025-06-10 00:11:51', '2025-06-10 00:12:00', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(73, 'Frederico Fagundes', 'klubecash@gmail.com', '(34) 99335-7697', NULL, '$2y$10$cM0f9co4abNHzxiOD0ZgjuZchVNk9o3v6mOadv2aByV.s339xdTPu', '2025-06-10 00:14:24', '2025-09-27 22:35:11', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(74, 'Amanda rosa ', 'aricken31@gmail.com', '(34) 99975-8423', NULL, '$2y$10$aV.0Wj3E2dMRHSX7KqHa9u0.LsHiHDdBEpD/yOzCB.QC4uFcu72/K', '2025-06-10 00:15:41', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(75, 'Felipe Vieira ribeiro', 'ribeirofilepe34@gmail.com', '(34) 99712-8998', NULL, '$2y$10$MpCAnHh7GN8ToE7b3FGzcurkrl8TA4Ffm69NECs0ePdMJcuvW0iNC', '2025-06-10 00:40:43', '2025-08-30 20:41:38', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(76, 'Gabriela Steffany da Silva ', 'gabisteffany@icloud.com', '(34) 98700-3621', NULL, '$2y$10$eFewesljEaKuqWpeFRnuy.Xh/FJ4sXLz8thior8hzQUytyrDisYay', '2025-06-10 00:41:33', '2025-06-10 00:45:29', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(77, 'Bruna Leal Ribeiro', 'brunna.leal00@gmail.com', '(34) 99982-8286', NULL, '$2y$10$Og4FZ3ealFiMAvj2gAIR0etd35frBRFNz/0CoefkAOqXkjOK/0ZLy', '2025-06-10 00:41:56', '2025-06-10 00:42:07', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(78, 'Gabriele Soares Souza ', 'soaresgabriele25@gmail.com', '(34) 99960-8386', NULL, '$2y$10$BgfPzZTWZ4Qa412NtFZQQ.QAoO9k8Y5G.GFiaLvBIqX5rbUt99sfG', '2025-06-10 02:24:49', '2025-06-10 02:25:03', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(79, 'Pedro Henrique Duarte ', 'pedrohduarte98@gmail.com', '5534998437197', NULL, '$2y$10$CSUkXDPCL6rdd2cMhEhPKO0dq.D7ioZ9ywNef8wf0CFcBDufwgBeu', '2025-06-10 05:22:24', '2025-06-10 05:22:59', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(80, 'Pirapora', 'kaualopes@unipam.edu.br', NULL, NULL, '$2y$10$VOJ.OE4rGXEWrq55slY41uz0POqQ2ZCph71mpaW9C3gIdoF38TXcm', '2025-06-10 18:44:22', NULL, 'ativo', 'loja', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(81, 'Lucas Fagundes da Silva ', 'lucasfagundes934@gmail.com', '(34) 99218-9099', NULL, '$2y$10$obpHzgu/lTbA9BLIWsz8yebeD3rroMp9cW.Xy/MxbW8A7mOom9ox2', '2025-06-11 19:29:20', '2025-06-13 00:57:44', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(86, 'Jennifer aryane ', 'jenniferlimaxz@gmail.com', '(55) 98497-1703', NULL, '$2y$10$Qeai.iOuOCYSrTMmFm7b1OE4WeHvgzmem4SLeJGa20bvjJJGzhZYG', '2025-07-07 17:05:39', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(87, 'Jennifer aryane ', 'jenniferlopesxz@gmail.com', '(55) 98497-1703', NULL, '$2y$10$FxTmg8XDk50WOKlUAZzaeOAF.sPVIgcZHyryCUlZMern1Hy363CFO', '2025-07-07 17:06:49', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(88, 'Rafael Augusto Alves Silva ', 'rafaelaugustoalvessilva5@gmail.com', '(34) 99665-7725', NULL, '$2y$10$B8CcTlZLjn2swhyPXdjnQeq3sl5.j6nnyVbqkL9wwzkcM.ulaFBwW', '2025-07-07 18:28:49', '2025-07-07 22:36:48', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(111, 'Ana Caroliny Ferreira De Almeida ', 'anacarolinyferreiradealmeida5@gmail.com', '(11) 97880-6283', NULL, '$2y$10$di3MoK7n.I9v3S3UN.xF6.qQX4w.BlqxfDl7cEGjCJElaAyNEYFM6', '2025-07-16 03:14:07', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(118, 'Clarissa', 'clarissalopes296@gmail.com', NULL, NULL, '$2y$10$g/2OVjHI54UuC4zbBiiNSuFk.3UIJtQbSG1hoEb/pxnIlNQwQk6UO', '2025-07-22 21:39:03', '2025-07-26 19:28:54', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 'nao'),
(121, 'Kaua', '', '38991045003', NULL, NULL, '2025-08-13 22:05:06', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(134, 'KAua', 'visitante_38991045004_loja_34@klubecash.local', '38991045004', NULL, NULL, '2025-08-14 01:50:58', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(135, 'Kaua Lopéscd', 'visitante_11450807392_loja_34@klubecash.local', '11450807392', NULL, NULL, '2025-08-14 02:04:38', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(137, 'Teste Corrigido 23:09:03', 'visitante_11233143249_loja_34@klubecash.local', '11233143249', NULL, NULL, '2025-08-14 02:09:03', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(138, 'João Teste', 'visitante_11987654321_loja_34@klubecash.local', '11987654321', NULL, NULL, '2025-08-14 02:18:32', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(139, 'Cecilia', 'visitante_34991191534_loja_34@klubecash.local', '34991191534', NULL, NULL, '2025-08-14 02:21:26', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(140, 'Frederico', 'visitante_34993357697_loja_34@klubecash.local', '34993357697', NULL, NULL, '2025-08-14 02:27:29', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(141, 'Jaqueline maria ', 'sousalima20189@gmail.com', '(34) 99771-3760', NULL, '$2y$10$t3FvhtIQs/Z8azhQl6WUbeubrf1Rj5J15B8Fh6KW4OKC2jHrQNRla', '2025-08-14 07:07:11', '2025-08-14 07:07:29', 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(142, 'Frederico Fagundes', 'visitante_34993357697_loja_38@klubecash.local', '34993357697', NULL, NULL, '2025-08-14 07:31:17', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(143, 'jean junior', 'visitante_34992708603_loja_38@klubecash.local', '34992708603', NULL, NULL, '2025-08-14 08:46:55', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(144, 'roberto magalhaes', 'visitante_34993171602_loja_38@klubecash.local', '34993171602', NULL, NULL, '2025-08-14 09:10:45', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(145, 'Frederico Fagundes', 'visitante_3497635735_loja_38@klubecash.local', '34997635735', NULL, NULL, '2025-08-14 13:54:43', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(146, 'Kamilla', 'visitante_34988247844_loja_38@klubecash.local', '34988247844', NULL, NULL, '2025-08-14 15:03:01', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(147, 'Fábio Eduardo', 'visitante_34992369765_loja_38@klubecash.local', '34992369765', NULL, NULL, '2025-08-14 15:13:32', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(148, 'Frederico', 'visitante_34993357698_loja_38@klubecash.local', '34993357698', NULL, NULL, '2025-08-14 17:11:46', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(149, 'giovanna moreira', 'visitante_34963466409_loja_38@klubecash.local', '34963466409', NULL, NULL, '2025-08-14 18:46:25', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(150, 'GUIUGAO', 'visitante_34996346409_loja_38@klubecash.local', '34996346409', NULL, NULL, '2025-08-14 18:49:50', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(151, 'Ana Livia', 'visitante_34998176771_loja_38@klubecash.local', '34998176771', NULL, NULL, '2025-08-14 19:47:25', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(152, 'Alessandra Regis', 'visitante_34991927053_loja_38@klubecash.local', '34991927053', NULL, NULL, '2025-08-14 19:50:17', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(153, 'Cleides felix', 'visitante_38998693037_loja_38@klubecash.local', '38998693037', NULL, NULL, '2025-08-14 19:53:57', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(154, 'Aurélia Cristina', 'visitante_34998721675_loja_38@klubecash.local', '34998721675', NULL, NULL, '2025-08-14 19:57:57', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(155, 'Bruna leal', 'visitante_34999828286_loja_38@klubecash.local', '34999828286', NULL, NULL, '2025-08-14 20:09:40', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(156, 'Vitória Filipa', 'visitante_55349972501_loja_38@klubecash.local', '55349972501', NULL, NULL, '2025-08-14 20:13:31', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(157, 'Pyetro swanson', 'visitante_34991251830_loja_38@klubecash.local', '34991251830', NULL, NULL, '2025-08-14 20:15:45', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(158, 'Carla Gonçalves', 'visitante_34998966741_loja_38@klubecash.local', '34998966741', NULL, NULL, '2025-08-15 01:02:50', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(159, 'Sync Holding', 'kaua@syncholding.com.br', '(34) 99800-2600', '04355521630', '$2y$10$W4Mw0j5/DhS.p0/I.D0he.aekBeq.O9.5xVoS8wntjF4L3U3P6OPW', '2025-08-15 13:52:55', '2025-09-29 22:03:21', 'ativo', 'loja', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'sim'),
(160, 'Cecilia', 'visitante_34991191534_loja_59@klubecash.local', '34991191534', NULL, NULL, '2025-08-15 14:47:55', NULL, 'ativo', 'cliente', 'Não', 'visitante', 59, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(161, 'Evaldo Gabriel', 'visitante_34991247963_loja_38@klubecash.local', '34991247963', NULL, NULL, '2025-08-15 17:02:42', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(162, 'Cecilia 3', 'visitante_34998002600_loja_59@klubecash.local', '34998002600', NULL, NULL, '2025-08-15 19:30:55', NULL, 'ativo', 'cliente', 'Não', 'visitante', 59, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(163, 'maria versiani', 'visitante_34997201631_loja_38@klubecash.local', '34997201631', NULL, NULL, '2025-08-16 16:53:41', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(164, 'Laisla Fagundes', 'visitante_55349963106_loja_38@klubecash.local', '55349963106', NULL, NULL, '2025-08-16 16:57:25', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(165, 'Laisla Fagundes', 'visitante_34996310606_loja_38@klubecash.local', '34996310606', NULL, NULL, '2025-08-16 16:58:42', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(166, 'Luh Duarte', 'visitante_34999908465_loja_38@klubecash.local', '34999908465', NULL, NULL, '2025-08-16 17:17:59', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(167, 'Ellen Monteiro', 'visitante_34992244799_loja_38@klubecash.local', '34992244799', NULL, NULL, '2025-08-18 16:53:57', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(168, 'Felipe Vieira', 'visitante_34997128998_loja_38@klubecash.local', '34997128998', NULL, NULL, '2025-08-21 20:03:48', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(169, 'Renato', 'visitante_34999975070_loja_38@klubecash.local', '34999975070', NULL, NULL, '2025-08-24 17:11:25', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(170, 'Hellen Mendes', 'visitante_34993354890_loja_38@klubecash.local', '34993354890', NULL, NULL, '2025-08-28 17:06:40', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(171, 'Hellen Mendes', 'visitante_34999354890_loja_38@klubecash.local', '34999354890', NULL, NULL, '2025-08-28 17:08:54', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(172, 'Ângela', 'visitante_34992172404_loja_38@klubecash.local', '34992172404', NULL, NULL, '2025-08-29 17:08:13', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(173, 'ELITE SEMIJOIAS MOZAR FRANCISCO LUIZ ME', 'elitesemijoiaspatosdeminas@gmail.com', '(34) 99217-2404', NULL, '$2y$10$ZuWSVnYfMCez78BDAjwgwe2pS4jGGI5TKjSS2qyloKQaArA5CazI6', '2025-08-29 17:22:01', NULL, 'ativo', 'loja', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'sim'),
(174, 'Vinicius Pais', 'visitante_11999841933_loja_34@klubecash.local', '11999841933', NULL, NULL, '2025-09-13 11:29:29', NULL, 'ativo', 'cliente', 'Não', 'visitante', 34, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(175, 'Digo.com', 'digovarejo@gmail.com', '(11) 97088-3167', NULL, '$2y$10$EfdYf7wQTFzcnydTwwVHD.z1FJRU4582k4v/oQVgwsEvpFRw3bNla', '2025-09-13 14:49:08', NULL, 'ativo', 'loja', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'sim'),
(177, 'RSF SONHO DE NOIVA EVENTOS E NEGOCIOS LTDA', 'cleacasamentos@gmail.com', '(85) 99632-4231', NULL, '$2y$10$cTaW4e9BBcO8OKGdOJ.WpeJN/g194QfJ259i3KuBP7i3.yxABtyia', '2025-09-14 19:47:52', '2025-09-24 23:35:31', 'ativo', 'loja', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(180, 'Ricardo da Silva Facundo', 'visitante_85982334146_loja_59@klubecash.local', '85982334146', NULL, NULL, '2025-09-15 01:37:26', NULL, 'ativo', 'cliente', 'Não', 'visitante', 59, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(181, 'maria joaquina', 'visitante_3499654789_loja_38@klubecash.local', '34999654789', NULL, NULL, '2025-09-15 18:32:26', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(184, 'Emanuel Caetano', 'visitante_33987063966_loja_38@klubecash.local', '33987063966', NULL, NULL, '2025-09-19 18:35:46', NULL, 'ativo', 'cliente', 'Não', 'visitante', 38, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(185, 'Teste WhatsApp', 'teste@klubecash.com', NULL, NULL, '$2y$10$CrbhTxuc9U.fwdTH2F0el.Tr8i6gzKE2Fg.q58tZAWX/gZe0h/ygG', '2025-09-20 20:10:30', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(186, 'João Primeiro', 'joão.primeiro@teste.com', '5538991045201', NULL, '$2y$10$dmWTnNzPfPAwoZmHIc9eLulthgcNJ4PRs8e5/yDBqQ/FljbgoX4km', '2025-09-20 20:30:15', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(187, 'Maria Regular', 'maria.regular@teste.com', '5538991045202', NULL, '$2y$10$ozcgNJjVPZGxCTDnUjto6..4A90UXc7P84zQ.4g/MA6ii1GJtRGJC', '2025-09-20 20:30:17', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(188, 'Carlos VIP Silva', 'carlos.vip.silva@teste.com', '5538991045203', NULL, '$2y$10$b.Yk5L3aBI2aGGxXluJG4OOsrpYAh8Gk34oOddlss4Q52Yw/4RCau', '2025-09-20 20:30:20', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(189, 'Ana Compradora', 'ana.compradora@teste.com', '5538991045204', NULL, '$2y$10$ZGiDUKvOoNGItJeT59V52Oj4FnAMWXMLP6ha6/QcIQ/MPpzm3lq8e', '2025-09-20 20:30:29', NULL, 'ativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(190, 'Желаете найти новый доход? Тест Т-Банка — ваш возможность. Вступайте https://tinyurl.com/pXi6DHBS TH', 'cthrinereynoldqoq29677y@acolm.org', '87278384256', NULL, '$2y$10$KQ0IH.nZE.HRqHi0prJrCuoQLLUzjnKV8yWJeFdvN96G1/2Q0pmc2', '2025-09-21 19:29:19', '2025-09-21 19:29:20', 'inativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(191, 'Ищете дополнительный заработок? Тест Т-Банка — ваш возможность. Присоединяйтесь https://tinyurl.com/', 'grafalovskiy00@bk.ru', '88842835549', NULL, '$2y$10$F4iUCTPEa/lkH6pnfN1RCej3V6BdGwE9V/gPymFYW/eTaP0qWtxKe', '2025-09-21 19:49:01', '2025-09-21 19:49:03', 'inativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(192, 'er', 'visitante_5534998002600_loja_59@klubecash.local', '5534998002600', NULL, NULL, '2025-09-22 01:24:50', NULL, 'ativo', 'cliente', 'Não', 'visitante', 59, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(193, 'Класс! Вам достался потрясающий приз ждёт вас! Изучите подробности по ссылке https://tinyurl.com/phA', 'veldgrube.00@mail.ru', '86756898182', NULL, '$2y$10$FAFdUdSMlo.Buv5rUx4JU.go17HPyYkNHHWLTibaHgPS8AiNlzoIG', '2025-09-23 11:24:18', '2025-09-23 11:24:19', 'inativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(194, 'Супер! Вы получили эксклюзивный сюрприз готов для вас! Изучите все подробности по ссылке https://tin', 'kateebartonmmp26936t@52sk2.org', '89714695939', NULL, '$2y$10$CVF8v3ondOv9BY0nWYQryeJdVUnUHLeUlfFcT9fg1gevw7u4on8sG', '2025-09-23 11:24:18', '2025-09-23 11:24:20', 'inativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(195, 'Kaua teste', 'visitante_3891045205_loja_59@klubecash.local', NULL, NULL, NULL, '2025-09-24 06:52:01', NULL, 'ativo', 'cliente', 'Não', 'visitante', 59, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(196, 'Вам перевод 170998 руб. забрать тут  https://tinyurl.com/bvDqJbKs NFDAW47442NFDAW', '6c2ini1uwox@lchaoge.com', '86315518115', NULL, '$2y$10$h3k/dd7XuwhV6MXl5o7KMOlDZepHMqL45w3THS7T1OGMZhNwiWT4S', '2025-09-25 22:01:38', '2025-09-25 22:01:40', 'inativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao'),
(197, 'Вау! Ваш эксклюзивный подарок готов для вас! Проверьте детали по ссылке https://tinyurl.com/gwpSjJb4', 'mantsurov1990@bk.ru', '87234617859', NULL, '$2y$10$Jgc.I73BzKatOGLrqg4W9ODGU4CEnpRWB3ZFtRuk5u9a6U/WBVpBy', '2025-09-28 21:04:35', '2025-09-28 21:04:36', 'inativo', 'cliente', 'Não', 'completo', NULL, NULL, NULL, 'local', 0, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, 'funcionario', 'nao');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_contato`
--

CREATE TABLE `usuarios_contato` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `email_alternativo` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios_contato`
--

INSERT INTO `usuarios_contato` (`id`, `usuario_id`, `telefone`, `celular`, `email_alternativo`) VALUES
(10, 9, '(38) 9842-23205', '(34) 99800-2600', 'kauanupix@gmail.com');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_endereco`
--

CREATE TABLE `usuarios_endereco` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `principal` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios_endereco`
--

INSERT INTO `usuarios_endereco` (`id`, `usuario_id`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `principal`) VALUES
(5, 9, '38705-376', 'Francisco Braga da Mota', '146', 'Ap 101', 'Jd Panoramico', 'Patos de minas', 'MG', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `verificacao_2fa`
--

CREATE TABLE `verificacao_2fa` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo` varchar(6) NOT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_expiracao` timestamp NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `webhook_errors`
--

CREATE TABLE `webhook_errors` (
  `id` int(11) NOT NULL,
  `mp_payment_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `whatsapp_cadastro_sessions`
--

CREATE TABLE `whatsapp_cadastro_sessions` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `whatsapp_cadastro_sessions`
--

INSERT INTO `whatsapp_cadastro_sessions` (`id`, `phone`, `user_id`, `state`, `data`, `created_at`, `updated_at`, `expires_at`) VALUES
(1, '34991191534', 139, 'aguardando_email', '{\"nome\":\"Cecilia\"}', '2025-08-16 14:20:17', '2025-08-16 14:29:09', '2025-08-16 11:39:09');

-- --------------------------------------------------------

--
-- Estrutura para tabela `whatsapp_evolution_logs`
--

CREATE TABLE `whatsapp_evolution_logs` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `whatsapp_logs`
--

CREATE TABLE `whatsapp_logs` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message_preview` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `message_id` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `simulation_mode` tinyint(1) NOT NULL DEFAULT 0,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `message` text DEFAULT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `whatsapp_logs`
--

INSERT INTO `whatsapp_logs` (`id`, `type`, `phone`, `message_preview`, `success`, `message_id`, `error_message`, `simulation_mode`, `additional_data`, `ip_address`, `user_agent`, `created_at`, `metadata`, `message`, `status`) VALUES
(705, 'cashback_notification', 'unknown', '⭐ *Ricardo da Silva Facundo*, sua compra foi registrada!*\n\n⏰ Liberação em até 7 dias úteis.\n\n🏪 RSF SONHO DE NOIVA EVENTOS E NEGOCIOS LTDA\n💰 Compra: R$ 50,00\n🎁 Cashback: R$ 2,50\n\n💳 Acesse: https://klubecash.com\n\n🔔 *Klube Cash - Dinhe', 1, NULL, NULL, 0, '{\"transaction_id\":680,\"message_preview\":\"\\u2b50 *Ricardo da Silva Facundo*, sua compra foi registrada!*\\n\\n\\u23f0 Libera\\u00e7\\u00e3o em at\\u00e9 7 dias \\u00fateis.\\n\",\"timestamp\":\"2025-09-26 14:05:53\",\"system\":\"FixedBrutalNotificationSystem\"}', NULL, NULL, '2025-09-26 17:05:53', NULL, NULL, 'pending');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_reserva_cashback`
--
ALTER TABLE `admin_reserva_cashback`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `admin_reserva_movimentacoes`
--
ALTER TABLE `admin_reserva_movimentacoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `admin_saldo`
--
ALTER TABLE `admin_saldo`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `admin_saldo_movimentacoes`
--
ALTER TABLE `admin_saldo_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transacao_id` (`transacao_id`);

--
-- Índices de tabela `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_hash` (`key_hash`),
  ADD UNIQUE KEY `key_prefix` (`key_prefix`),
  ADD KEY `partner_email` (`partner_email`),
  ADD KEY `is_active` (`is_active`);

--
-- Índices de tabela `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `endpoint` (`endpoint`),
  ADD KEY `api_key_id` (`api_key_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `status_code` (`status_code`);

--
-- Índices de tabela `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rate_limit` (`api_key_id`,`endpoint`,`window_type`,`window_start`);

--
-- Índices de tabela `bot_consultas`
--
ALTER TABLE `bot_consultas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_telefone` (`telefone`),
  ADD KEY `idx_data` (`data_consulta`);

--
-- Índices de tabela `cashback_movimentacoes`
--
ALTER TABLE `cashback_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `transacao_origem_id` (`transacao_origem_id`),
  ADD KEY `transacao_uso_id` (`transacao_uso_id`),
  ADD KEY `idx_usuario_loja_data` (`usuario_id`,`loja_id`,`data_operacao`),
  ADD KEY `pagamento_id` (`pagamento_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `cashback_notificacoes`
--
ALTER TABLE `cashback_notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transacao_id` (`transacao_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_data_tentativa` (`data_tentativa`);

--
-- Índices de tabela `cashback_notification_retries`
--
ALTER TABLE `cashback_notification_retries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_status_next_retry` (`status`,`next_retry`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `cashback_saldos`
--
ALTER TABLE `cashback_saldos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_usuario_loja` (`usuario_id`,`loja_id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `comissoes_status_historico`
--
ALTER TABLE `comissoes_status_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comissao_id` (`comissao_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `configuracoes_2fa`
--
ALTER TABLE `configuracoes_2fa`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `configuracoes_cashback`
--
ALTER TABLE `configuracoes_cashback`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `configuracoes_notificacao`
--
ALTER TABLE `configuracoes_notificacao`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `configuracoes_saldo`
--
ALTER TABLE `configuracoes_saldo`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `email_envios`
--
ALTER TABLE `email_envios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_campaign_email` (`campaign_id`,`email`);

--
-- Índices de tabela `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`store_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Índices de tabela `ip_block`
--
ALTER TABLE `ip_block`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`),
  ADD KEY `block_expiry` (`block_expiry`);

--
-- Índices de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`),
  ADD KEY `attempt_time` (`attempt_time`);

--
-- Índices de tabela `lojas`
--
ALTER TABLE `lojas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`),
  ADD KEY `idx_lojas_email_senha` (`email`,`senha_hash`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_lojas_cashback_config` (`cashback_ativo`,`porcentagem_cliente`,`porcentagem_admin`);

--
-- Índices de tabela `lojas_contato`
--
ALTER TABLE `lojas_contato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `lojas_endereco`
--
ALTER TABLE `lojas_endereco`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `lojas_favoritas`
--
ALTER TABLE `lojas_favoritas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`usuario_id`,`loja_id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `n8n_webhook_logs`
--
ALTER TABLE `n8n_webhook_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `pagamentos_comissao`
--
ALTER TABLE `pagamentos_comissao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `pagamentos_devolucoes`
--
ALTER TABLE `pagamentos_devolucoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pagamento_id` (`pagamento_id`),
  ADD KEY `solicitado_por` (`solicitado_por`),
  ADD KEY `aprovado_por` (`aprovado_por`);

--
-- Índices de tabela `pagamentos_transacoes`
--
ALTER TABLE `pagamentos_transacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pagamento_transacao_unique` (`pagamento_id`,`transacao_id`),
  ADD KEY `transacao_id` (`transacao_id`);

--
-- Índices de tabela `pagamento_transacoes`
--
ALTER TABLE `pagamento_transacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payment_transaction` (`pagamento_id`,`transacao_id`);

--
-- Índices de tabela `recuperacao_senha`
--
ALTER TABLE `recuperacao_senha`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `sessoes`
--
ALTER TABLE `sessoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `store_balance_payments`
--
ALTER TABLE `store_balance_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `transacoes_cashback`
--
ALTER TABLE `transacoes_cashback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `transacoes_comissao`
--
ALTER TABLE `transacoes_comissao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transacao_id` (`transacao_id`);

--
-- Índices de tabela `transacoes_saldo_usado`
--
ALTER TABLE `transacoes_saldo_usado`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transacao` (`transacao_id`),
  ADD KEY `idx_usuario_loja` (`usuario_id`,`loja_id`);

--
-- Índices de tabela `transacoes_status_historico`
--
ALTER TABLE `transacoes_status_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transacao_id` (`transacao_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD UNIQUE KEY `uk_cpf_unique` (`cpf`),
  ADD UNIQUE KEY `unique_email_not_null` (`email`),
  ADD KEY `idx_usuarios_google_id` (`google_id`),
  ADD KEY `idx_usuarios_provider` (`provider`),
  ADD KEY `idx_cpf` (`cpf`),
  ADD KEY `loja_vinculada_id` (`loja_vinculada_id`),
  ADD KEY `fk_usuarios_loja_criadora` (`loja_criadora_id`),
  ADD KEY `idx_usuarios_telefone` (`telefone`),
  ADD KEY `idx_usuarios_senat` (`senat`);

--
-- Índices de tabela `usuarios_contato`
--
ALTER TABLE `usuarios_contato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `usuarios_endereco`
--
ALTER TABLE `usuarios_endereco`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `verificacao_2fa`
--
ALTER TABLE `verificacao_2fa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_codigo` (`usuario_id`,`codigo`),
  ADD KEY `idx_expiracao` (`data_expiracao`);

--
-- Índices de tabela `webhook_errors`
--
ALTER TABLE `webhook_errors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mp_payment_id` (`mp_payment_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `whatsapp_cadastro_sessions`
--
ALTER TABLE `whatsapp_cadastro_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Índices de tabela `whatsapp_evolution_logs`
--
ALTER TABLE `whatsapp_evolution_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `whatsapp_logs`
--
ALTER TABLE `whatsapp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_success` (`success`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_reserva_movimentacoes`
--
ALTER TABLE `admin_reserva_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `admin_saldo`
--
ALTER TABLE `admin_saldo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `admin_saldo_movimentacoes`
--
ALTER TABLE `admin_saldo_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT de tabela `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `bot_consultas`
--
ALTER TABLE `bot_consultas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de tabela `cashback_movimentacoes`
--
ALTER TABLE `cashback_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=339;

--
-- AUTO_INCREMENT de tabela `cashback_notificacoes`
--
ALTER TABLE `cashback_notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cashback_notification_retries`
--
ALTER TABLE `cashback_notification_retries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `cashback_saldos`
--
ALTER TABLE `cashback_saldos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=309;

--
-- AUTO_INCREMENT de tabela `comissoes_status_historico`
--
ALTER TABLE `comissoes_status_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `configuracoes_2fa`
--
ALTER TABLE `configuracoes_2fa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `configuracoes_cashback`
--
ALTER TABLE `configuracoes_cashback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `configuracoes_notificacao`
--
ALTER TABLE `configuracoes_notificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `configuracoes_saldo`
--
ALTER TABLE `configuracoes_saldo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `email_campaigns`
--
ALTER TABLE `email_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `email_envios`
--
ALTER TABLE `email_envios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de tabela `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ip_block`
--
ALTER TABLE `ip_block`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `lojas`
--
ALTER TABLE `lojas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT de tabela `lojas_contato`
--
ALTER TABLE `lojas_contato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `lojas_endereco`
--
ALTER TABLE `lojas_endereco`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de tabela `lojas_favoritas`
--
ALTER TABLE `lojas_favoritas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `n8n_webhook_logs`
--
ALTER TABLE `n8n_webhook_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=688;

--
-- AUTO_INCREMENT de tabela `pagamentos_comissao`
--
ALTER TABLE `pagamentos_comissao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1211;

--
-- AUTO_INCREMENT de tabela `pagamentos_devolucoes`
--
ALTER TABLE `pagamentos_devolucoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `pagamentos_transacoes`
--
ALTER TABLE `pagamentos_transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=228;

--
-- AUTO_INCREMENT de tabela `pagamento_transacoes`
--
ALTER TABLE `pagamento_transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `recuperacao_senha`
--
ALTER TABLE `recuperacao_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT de tabela `store_balance_payments`
--
ALTER TABLE `store_balance_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `transacoes_cashback`
--
ALTER TABLE `transacoes_cashback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=688;

--
-- AUTO_INCREMENT de tabela `transacoes_comissao`
--
ALTER TABLE `transacoes_comissao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT de tabela `transacoes_saldo_usado`
--
ALTER TABLE `transacoes_saldo_usado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `transacoes_status_historico`
--
ALTER TABLE `transacoes_status_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198;

--
-- AUTO_INCREMENT de tabela `usuarios_contato`
--
ALTER TABLE `usuarios_contato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `usuarios_endereco`
--
ALTER TABLE `usuarios_endereco`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `verificacao_2fa`
--
ALTER TABLE `verificacao_2fa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `webhook_errors`
--
ALTER TABLE `webhook_errors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `whatsapp_cadastro_sessions`
--
ALTER TABLE `whatsapp_cadastro_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `whatsapp_evolution_logs`
--
ALTER TABLE `whatsapp_evolution_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `whatsapp_logs`
--
ALTER TABLE `whatsapp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=706;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `api_logs`
--
ALTER TABLE `api_logs`
  ADD CONSTRAINT `api_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD CONSTRAINT `api_rate_limits_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cashback_movimentacoes`
--
ALTER TABLE `cashback_movimentacoes`
  ADD CONSTRAINT `cashback_movimentacoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cashback_movimentacoes_ibfk_2` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cashback_movimentacoes_ibfk_3` FOREIGN KEY (`transacao_origem_id`) REFERENCES `transacoes_cashback` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cashback_movimentacoes_ibfk_4` FOREIGN KEY (`transacao_uso_id`) REFERENCES `transacoes_cashback` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cashback_movimentacoes_ibfk_5` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `cashback_notificacoes`
--
ALTER TABLE `cashback_notificacoes`
  ADD CONSTRAINT `cashback_notificacoes_ibfk_1` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cashback` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cashback_saldos`
--
ALTER TABLE `cashback_saldos`
  ADD CONSTRAINT `cashback_saldos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cashback_saldos_ibfk_2` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `comissoes_status_historico`
--
ALTER TABLE `comissoes_status_historico`
  ADD CONSTRAINT `comissoes_status_historico_ibfk_1` FOREIGN KEY (`comissao_id`) REFERENCES `transacoes_comissao` (`id`),
  ADD CONSTRAINT `comissoes_status_historico_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `email_envios`
--
ALTER TABLE `email_envios`
  ADD CONSTRAINT `email_envios_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `email_campaigns` (`id`);

--
-- Restrições para tabelas `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `lojas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `lojas`
--
ALTER TABLE `lojas`
  ADD CONSTRAINT `lojas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lojas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `lojas_contato`
--
ALTER TABLE `lojas_contato`
  ADD CONSTRAINT `lojas_contato_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `lojas_endereco`
--
ALTER TABLE `lojas_endereco`
  ADD CONSTRAINT `lojas_endereco_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `lojas_favoritas`
--
ALTER TABLE `lojas_favoritas`
  ADD CONSTRAINT `lojas_favoritas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `lojas_favoritas_ibfk_2` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pagamentos_comissao`
--
ALTER TABLE `pagamentos_comissao`
  ADD CONSTRAINT `pagamentos_comissao_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`),
  ADD CONSTRAINT `pagamentos_comissao_ibfk_2` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pagamentos_devolucoes`
--
ALTER TABLE `pagamentos_devolucoes`
  ADD CONSTRAINT `pagamentos_devolucoes_ibfk_1` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos_comissao` (`id`),
  ADD CONSTRAINT `pagamentos_devolucoes_ibfk_2` FOREIGN KEY (`solicitado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pagamentos_devolucoes_ibfk_3` FOREIGN KEY (`aprovado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pagamentos_transacoes`
--
ALTER TABLE `pagamentos_transacoes`
  ADD CONSTRAINT `pagamentos_transacoes_ibfk_1` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos_comissao` (`id`),
  ADD CONSTRAINT `pagamentos_transacoes_ibfk_2` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cashback` (`id`);

--
-- Restrições para tabelas `recuperacao_senha`
--
ALTER TABLE `recuperacao_senha`
  ADD CONSTRAINT `recuperacao_senha_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `sessoes`
--
ALTER TABLE `sessoes`
  ADD CONSTRAINT `sessoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `store_balance_payments`
--
ALTER TABLE `store_balance_payments`
  ADD CONSTRAINT `store_balance_payments_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `transacoes_cashback`
--
ALTER TABLE `transacoes_cashback`
  ADD CONSTRAINT `transacoes_cashback_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `transacoes_cashback_ibfk_2` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`),
  ADD CONSTRAINT `transacoes_cashback_ibfk_3` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `transacoes_comissao`
--
ALTER TABLE `transacoes_comissao`
  ADD CONSTRAINT `transacoes_comissao_ibfk_1` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cashback` (`id`);

--
-- Restrições para tabelas `transacoes_saldo_usado`
--
ALTER TABLE `transacoes_saldo_usado`
  ADD CONSTRAINT `transacoes_saldo_usado_ibfk_1` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cashback` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `transacoes_status_historico`
--
ALTER TABLE `transacoes_status_historico`
  ADD CONSTRAINT `transacoes_status_historico_ibfk_1` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cashback` (`id`);

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_loja_criadora` FOREIGN KEY (`loja_criadora_id`) REFERENCES `lojas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`loja_vinculada_id`) REFERENCES `lojas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuarios_contato`
--
ALTER TABLE `usuarios_contato`
  ADD CONSTRAINT `usuarios_contato_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `usuarios_endereco`
--
ALTER TABLE `usuarios_endereco`
  ADD CONSTRAINT `usuarios_endereco_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `verificacao_2fa`
--
ALTER TABLE `verificacao_2fa`
  ADD CONSTRAINT `verificacao_2fa_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
