<?php

declare(strict_types=1);

use App\Services\Admin\AdminMutationService;
use App\Services\Admin\AdminReadService;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/../services/admin/AdminApiException.php';
require_once __DIR__ . '/../services/admin/AdminMoney.php';
require_once __DIR__ . '/../services/admin/AdminAuditService.php';
require_once __DIR__ . '/../services/admin/AdminIdempotencyService.php';
require_once __DIR__ . '/../services/admin/AdminReadService.php';
require_once __DIR__ . '/../services/admin/AdminMutationService.php';

/**
 * Adaptador de compatibilidade para as telas PHP antigas.
 * Toda regra nova vive nos serviços versionados de Admin; este arquivo apenas
 * traduz contratos enquanto o rollback seletivo permanecer disponível.
 */
final class AdminController
{
    private static function read(): AdminReadService
    {
        return new AdminReadService(Database::getConnection());
    }

    private static function mutations(): AdminMutationService
    {
        return new AdminMutationService(Database::getConnection(), (int) ($_SESSION['user_id'] ?? 0));
    }

    /** @return array{status:bool,message?:string,data?:mixed} */
    private static function run(callable $callback): array
    {
        try {
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            return ['status' => true, 'data' => $callback()];
        } catch (Throwable $exception) {
            if ($exception instanceof \App\Services\Admin\AdminApiException) {
                return ['status' => false, 'message' => $exception->getMessage()];
            }
            error_log('admin.legacy.failure type=' . get_class($exception));
            return ['status' => false, 'message' => 'Não foi possível concluir a operação administrativa.'];
        }
    }

    public static function getDashboardData(): array
    {
        return self::run(fn () => self::read()->dashboard());
    }

    public static function manageUsers(array $filters = [], int $page = 1): array
    {
        return self::run(function () use ($filters, $page): array {
            $result = self::read()->users([
                'search' => (string) ($filters['busca'] ?? ''), 'type' => (string) ($filters['tipo'] ?? ''), 'status' => (string) ($filters['status'] ?? ''),
            ], $page);
            $users = array_map(static fn (array $item): array => [
                'id' => $item['id'], 'nome' => $item['name'], 'email' => $item['email'], 'telefone' => $item['phone'], 'tipo' => $item['type'], 'status' => $item['status'],
                'tipo_cliente' => $item['customerType'], 'loja_vinculada_id' => $item['linkedStoreId'], 'nome_loja_vinculada' => $item['linkedStoreName'],
                'subtipo_funcionario' => $item['employeeSubtype'], 'data_criacao' => $item['createdAt'], 'ultimo_login' => $item['lastLoginAt'],
            ], $result['items']);
            $stats = Database::getConnection()->query("SELECT COUNT(*) total,COUNT(CASE WHEN status='ativo' THEN 1 END) ativos,COUNT(CASE WHEN status='inativo' THEN 1 END) inativos,COUNT(CASE WHEN status='bloqueado' THEN 1 END) bloqueados FROM usuarios")->fetch(PDO::FETCH_ASSOC);
            return ['usuarios' => $users, 'estatisticas' => $stats ?: [], 'paginacao' => self::legacyPagination($result['pagination'])];
        });
    }

    public static function getUserDetails(int $id): array
    {
        return self::run(function () use ($id): array {
            $item = self::read()->user($id)['item'];
            return ['id' => $item['id'], 'nome' => $item['name'], 'email' => $item['email'], 'telefone' => $item['phone'], 'tipo' => $item['type'], 'status' => $item['status'], 'tipo_cliente' => $item['customerType'], 'loja_vinculada_id' => $item['linkedStoreId'], 'subtipo_funcionario' => $item['employeeSubtype']];
        });
    }

    public static function updateUser(int $id, array $data): array
    {
        return self::run(fn () => self::mutations()->updateUser($id, ['name' => $data['nome'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['telefone'] ?? null, 'type' => $data['tipo'] ?? null, 'linkedStoreId' => $data['loja_vinculada_id'] ?? null, 'employeeSubtype' => $data['subtipo_funcionario'] ?? null]));
    }

    public static function updateUserStatus(int $id, string $status): array
    {
        return self::run(fn () => self::mutations()->updateUserStatus($id, $status));
    }

    public static function manageStoresWithBalance(array $filters = [], int $page = 1): array
    {
        return self::manageStores($filters, $page);
    }

    public static function manageStores(array $filters = [], int $page = 1): array
    {
        return self::run(function () use ($filters, $page): array {
            $result = self::read()->stores(['search' => (string) ($filters['busca'] ?? $filters['search'] ?? ''), 'status' => (string) ($filters['status'] ?? ''), 'category' => (string) ($filters['categoria'] ?? $filters['category'] ?? '')], $page);
            $stores = array_map(static fn (array $item): array => [
                'id' => $item['id'], 'nome_fantasia' => $item['name'], 'razao_social' => $item['legalName'], 'cnpj' => $item['cnpj'], 'email' => $item['email'], 'telefone' => $item['phone'],
                'categoria' => $item['category'], 'status' => $item['status'], 'observacao' => $item['notes'], 'porcentagem_cliente' => $item['customerCashbackPercentage'],
                'cashback_ativo' => $item['cashbackEnabled'], 'nome_usuario' => $item['ownerName'], 'total_transacoes' => $item['transactionsCount'], 'valor_total_transacoes' => $item['grossAmountCents'] / 100,
                'data_cadastro' => $item['registeredAt'], 'data_aprovacao' => $item['approvedAt'],
            ], $result['items']);
            $db = Database::getConnection();
            $stats = $db->query("SELECT COUNT(*) total,COUNT(CASE WHEN status='aprovado' THEN 1 END) aprovadas,COUNT(CASE WHEN status='pendente' THEN 1 END) pendentes,COUNT(CASE WHEN status='rejeitado' THEN 1 END) rejeitadas FROM lojas")->fetch(PDO::FETCH_ASSOC);
            $categories = $db->query('SELECT DISTINCT categoria FROM lojas WHERE categoria IS NOT NULL ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);
            return ['lojas' => $stores, 'estatisticas' => $stats ?: [], 'categorias' => $categories, 'paginacao' => self::legacyPagination($result['pagination'])];
        });
    }

    public static function getStoreDetails(int $id): array
    {
        return self::run(fn () => self::read()->store($id)['item']);
    }

    public static function getStoreDetailsWithBalance(int $id): array
    {
        return self::getStoreDetails($id);
    }

    public static function updateStore(int $id, array $data): array
    {
        return self::run(fn () => self::mutations()->updateStore($id, [
            'name' => $data['nome_fantasia'] ?? null, 'legalName' => $data['razao_social'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['telefone'] ?? null,
            'category' => $data['categoria'] ?? null, 'description' => $data['descricao'] ?? null, 'website' => $data['website'] ?? null,
            'customerCashbackPercentage' => $data['porcentagem_cliente'] ?? null, 'cashbackEnabled' => $data['cashback_ativo'] ?? null,
        ]));
    }

    public static function updateStoreStatus(int $id, string $status, string $notes = ''): array
    {
        return self::run(fn () => self::mutations()->updateStoreStatus($id, $status, $notes, 'legacy-store-' . $id . '-' . $status . '-' . time()));
    }

    public static function manageTransactionsWithBalance(array $filters = [], int $page = 1): array
    {
        return self::manageTransactions($filters, $page);
    }

    public static function manageTransactions(array $filters = [], int $page = 1): array
    {
        return self::run(function () use ($filters, $page): array {
            $result = self::read()->transactions(['search' => (string) ($filters['busca'] ?? ''), 'status' => (string) ($filters['status'] ?? ''), 'storeId' => (string) ($filters['loja_id'] ?? ''), 'startDate' => (string) ($filters['data_inicio'] ?? ''), 'endDate' => (string) ($filters['data_fim'] ?? '')], $page);
            $items = array_map(static fn (array $item): array => [
                'id' => $item['id'], 'codigo_transacao' => $item['code'], 'nome_usuario' => $item['customerName'], 'nome_loja' => $item['storeName'],
                'valor_total' => $item['grossAmountCents'] / 100, 'valor_saldo_usado' => $item['balanceUsedCents'] / 100, 'valor_pago' => $item['paidAmountCents'] / 100,
                'valor_cliente' => $item['cashbackAmountCents'] / 100, 'status' => $item['status'], 'financial_model' => $item['financialModel'], 'data_transacao' => $item['occurredAt'],
            ], $result['items']);
            $stores = Database::getConnection()->query("SELECT id,nome_fantasia FROM lojas WHERE status='aprovado' ORDER BY nome_fantasia")->fetchAll(PDO::FETCH_ASSOC);
            return ['transacoes' => $items, 'lojas' => $stores, 'estatisticas' => [], 'paginacao' => self::legacyPagination($result['pagination'])];
        });
    }

    public static function getTransactionDetailsWithBalance(int $id): array
    {
        return self::getTransactionDetails($id);
    }

    public static function getTransactionDetails(int $id): array
    {
        return self::run(fn () => self::read()->transaction($id)['item']);
    }

    public static function updateTransactionStatus(int $id, string $status, string $notes = ''): array
    {
        return self::run(fn () => self::mutations()->legacyTransactionStatus($id, $status, $notes, 'legacy-transaction-' . $id . '-' . $status . '-' . time()));
    }

    public static function getFinancialReports(array $filters = []): array
    {
        return self::run(fn () => self::read()->reports(['startDate' => (string) ($filters['data_inicio'] ?? ''), 'endDate' => (string) ($filters['data_fim'] ?? '')]));
    }

    public static function generateReport(string $type, array $filters = []): array
    {
        return self::getFinancialReports($filters);
    }

    public static function getSettings(): array
    {
        return self::run(function (): array {
            $settings = self::read()->settings();
            return ['porcentagem_total' => $settings['cashback']['customerPercentage'], 'porcentagem_cliente' => $settings['cashback']['customerPercentage'], 'porcentagem_admin' => 0, 'porcentagem_loja' => 0];
        });
    }

    public static function updateSettings(array $data): array
    {
        return self::run(fn () => self::mutations()->updateSettings(['customerPercentage' => $data['porcentagem_cliente'] ?? 5, 'balanceEnabled' => true, 'minimumUseCents' => 100, 'maximumPurchasePercentage' => 100, 'lowBalanceNotification' => true, 'lowBalanceThresholdCents' => 1000, 'newTransactionEmail' => true, 'approvedPaymentEmail' => true, 'availableBalanceEmail' => true, 'lowBalanceEmail' => true, 'expiredBalanceEmail' => true]));
    }

    public static function getAvailableStores(): array
    {
        return self::run(fn () => Database::getConnection()->query("SELECT id,nome_fantasia,email,status FROM lojas ORDER BY nome_fantasia")->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function getStoreByEmail(string $email): array
    {
        return self::run(function () use ($email): array { $stmt = Database::getConnection()->prepare('SELECT id,nome_fantasia,email,status FROM lojas WHERE email=:email LIMIT 1'); $stmt->execute([':email' => strtolower(trim($email))]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: []; });
    }

    public static function updateAdminBalance(float $value, ?int $transactionId = null, string $description = ''): bool
    {
        // O modelo atual não cria saldo administrativo. Mantido apenas para evitar
        // fatal error em consumidores legados; nenhuma movimentação nova é criada.
        return false;
    }

    /** @param array<string,int> $pagination @return array<string,int> */
    private static function legacyPagination(array $pagination): array
    {
        return ['pagina_atual' => $pagination['page'], 'itens_por_pagina' => $pagination['pageSize'], 'total_itens' => $pagination['totalItems'], 'total_paginas' => $pagination['totalPages']];
    }
}

if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'AdminController.php') {
    header('Content-Type: application/json; charset=UTF-8');
    $action = (string) ($_REQUEST['action'] ?? '');
    $result = match ($action) {
        'dashboard' => AdminController::getDashboardData(),
        'users' => AdminController::manageUsers([], max(1, (int) ($_REQUEST['page'] ?? 1))),
        'getUserDetails' => AdminController::getUserDetails((int) ($_REQUEST['user_id'] ?? 0)),
        'update_user_status' => AdminController::updateUserStatus((int) ($_REQUEST['user_id'] ?? 0), (string) ($_REQUEST['status'] ?? '')),
        'get_available_stores' => AdminController::getAvailableStores(),
        'get_store_by_email' => AdminController::getStoreByEmail((string) ($_REQUEST['email'] ?? '')),
        'update_transaction_status' => AdminController::updateTransactionStatus((int) ($_REQUEST['transaction_id'] ?? 0), (string) ($_REQUEST['status'] ?? ''), (string) ($_REQUEST['observacao'] ?? '')),
        default => ['status' => false, 'message' => 'Ação legada não encontrada.'],
    };
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
