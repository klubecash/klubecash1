<?php
/**
 * Controller de Assinaturas
 * Gerencia assinaturas de lojas no Klube Cash
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class SubscriptionController {
    private $conn;

    public function __construct($db = null) {
        $this->conn = $db ?? (new Database())->getConnection();
    }

    /**
     * Atribui um plano a uma loja (cria ou atualiza assinatura)
     *
     * @param int $lojaId ID da loja
     * @param string $planoSlug Slug do plano (klube-start, klube-plus, etc)
     * @param int|null $trialDaysOverride Dias de trial customizados
     * @param string $ciclo Ciclo: monthly ou yearly
     * @return array ['success' => bool, 'message' => string, 'assinatura_id' => int]
     */
    public function assignPlanToStore($lojaId, $planoSlug, $trialDaysOverride = null, $ciclo = 'monthly') {
        try {
            // Buscar plano
            $plano = $this->getPlanBySlug($planoSlug);
            if (!$plano) {
                return ['success' => false, 'message' => 'Plano não encontrado'];
            }

            // Verificar se já existe assinatura ativa para esta loja
            $existingSubscription = $this->getActiveSubscriptionByStore($lojaId);

            $trialDays = $trialDaysOverride ?? $plano['trial_dias'];
            $trialEnd = $trialDays > 0 ? date('Y-m-d', strtotime("+{$trialDays} days")) : null;

            $currentPeriodStart = date('Y-m-d');
            $currentPeriodEnd = $ciclo === 'yearly'
                ? date('Y-m-d', strtotime('+1 year'))
                : date('Y-m-d', strtotime('+1 month'));

            if ($existingSubscription) {
                // Atualizar assinatura existente
                $sql = "UPDATE assinaturas SET
                        plano_id = ?,
                        status = ?,
                        ciclo = ?,
                        trial_end = ?,
                        current_period_start = ?,
                        current_period_end = ?,
                        next_invoice_date = ?,
                        updated_at = NOW()
                        WHERE id = ?";

                $stmt = $this->conn->prepare($sql);
                $status = $trialEnd ? 'trial' : 'ativa';
                $nextInvoiceDate = $trialEnd ?? $currentPeriodEnd;

                $stmt->execute([
                    $plano['id'],
                    $status,
                    $ciclo,
                    $trialEnd,
                    $currentPeriodStart,
                    $currentPeriodEnd,
                    $nextInvoiceDate,
                    $existingSubscription['id']
                ]);

                return [
                    'success' => true,
                    'message' => 'Plano atualizado com sucesso',
                    'assinatura_id' => $existingSubscription['id']
                ];
            } else {
                // Criar nova assinatura
                $sql = "INSERT INTO assinaturas (
                        tipo, loja_id, plano_id, status, ciclo,
                        trial_end, current_period_start, current_period_end,
                        next_invoice_date, gateway
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->conn->prepare($sql);
                $status = $trialEnd ? 'trial' : 'ativa';
                $nextInvoiceDate = $trialEnd ?? $currentPeriodEnd;

                $stmt->execute([
                    'loja',
                    $lojaId,
                    $plano['id'],
                    $status,
                    $ciclo,
                    $trialEnd,
                    $currentPeriodStart,
                    $currentPeriodEnd,
                    $nextInvoiceDate,
                    'abacate'
                ]);

                return [
                    'success' => true,
                    'message' => 'Assinatura criada com sucesso',
                    'assinatura_id' => $this->conn->lastInsertId()
                ];
            }
        } catch (Exception $e) {
            error_log("Erro ao atribuir plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()];
        }
    }

    /**
     * Gera uma fatura para assinatura
     *
     * @param int $assinaturaId ID da assinatura
     * @param string|null $dueDate Data de vencimento (Y-m-d)
     * @param float|null $amountOverride Valor customizado
     * @return array ['success' => bool, 'fatura_id' => int, 'message' => string]
     */
    public function generateInvoiceForSubscription($assinaturaId, $dueDate = null, $amountOverride = null) {
        try {
            // Buscar assinatura com plano
            $sql = "SELECT a.*, p.preco_mensal, p.preco_anual, p.nome as plano_nome
                    FROM assinaturas a
                    JOIN planos p ON a.plano_id = p.id
                    WHERE a.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$assinaturaId]);
            $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assinatura) {
                return ['success' => false, 'message' => 'Assinatura não encontrada'];
            }

            // PROTEÇÃO CONTRA FATURAS DUPLICADAS
            // Verificar se já existe fatura para este mês/ano
            $sqlCheck = "SELECT id, numero, amount, status
                        FROM faturas
                        WHERE assinatura_id = ?
                        AND YEAR(created_at) = YEAR(NOW())
                        AND MONTH(created_at) = MONTH(NOW())
                        AND status IN ('pending', 'paid')
                        LIMIT 1";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([$assinaturaId]);
            $existingInvoice = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingInvoice) {
                return [
                    'success' => false,
                    'message' => 'Já existe fatura para este mês (' . $existingInvoice['numero'] . ')',
                    'existing_invoice_id' => $existingInvoice['id'],
                    'existing_invoice_number' => $existingInvoice['numero']
                ];
            }

            // Calcular valor
            $amount = $amountOverride ?? (
                $assinatura['ciclo'] === 'yearly' ? $assinatura['preco_anual'] : $assinatura['preco_mensal']
            );

            // Data de vencimento
            $dueDate = $dueDate ?? $assinatura['next_invoice_date'] ?? date('Y-m-d', strtotime('+7 days'));

            // Gerar número da fatura
            $invoiceNumber = $this->generateInvoiceNumber();

            // Inserir fatura
            $sql = "INSERT INTO faturas (
                    assinatura_id, numero, amount, currency, status,
                    due_date, payment_method, gateway
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $assinaturaId,
                $invoiceNumber,
                $amount,
                'BRL',
                'pending',
                $dueDate,
                'pix',
                'abacate'
            ]);

            $faturaId = $this->conn->lastInsertId();

            return [
                'success' => true,
                'fatura_id' => $faturaId,
                'numero' => $invoiceNumber,
                'amount' => $amount,
                'message' => 'Fatura gerada com sucesso'
            ];
        } catch (Exception $e) {
            error_log("Erro ao gerar fatura: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao gerar fatura: ' . $e->getMessage()];
        }
    }

    /**
     * Avança período da assinatura após pagamento
     *
     * @param int $faturaId ID da fatura paga
     * @return array ['success' => bool, 'message' => string]
     */
    public function advancePeriodOnPaid($faturaId) {
        try {
            // Buscar fatura e assinatura
            $sql = "SELECT f.*, a.ciclo, a.current_period_end
                    FROM faturas f
                    JOIN assinaturas a ON f.assinatura_id = a.id
                    WHERE f.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$faturaId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return ['success' => false, 'message' => 'Fatura não encontrada'];
            }

            // Calcular novo período
            $currentEnd = $data['current_period_end'];
            $ciclo = $data['ciclo'];

            $newPeriodStart = date('Y-m-d', strtotime($currentEnd . ' +1 day'));
            $newPeriodEnd = $ciclo === 'yearly'
                ? date('Y-m-d', strtotime($newPeriodStart . ' +1 year'))
                : date('Y-m-d', strtotime($newPeriodStart . ' +1 month'));

            $nextInvoiceDate = $newPeriodEnd;

            // Atualizar assinatura
            $sql = "UPDATE assinaturas SET
                    status = 'ativa',
                    current_period_start = ?,
                    current_period_end = ?,
                    next_invoice_date = ?,
                    trial_end = NULL,
                    updated_at = NOW()
                    WHERE id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $newPeriodStart,
                $newPeriodEnd,
                $nextInvoiceDate,
                $data['assinatura_id']
            ]);

            return [
                'success' => true,
                'message' => 'Período avançado com sucesso',
                'new_period_end' => $newPeriodEnd
            ];
        } catch (Exception $e) {
            error_log("Erro ao avançar período: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao avançar período: ' . $e->getMessage()];
        }
    }

    /**
     * Marca assinaturas inadimplentes (após grace period)
     *
     * @param int $gracePeriodDays Dias de tolerância após vencimento
     * @return int Número de assinaturas marcadas como inadimplentes
     */
    public function markDelinquentIfOverdue($gracePeriodDays = 3) {
        try {
            $graceDate = date('Y-m-d', strtotime("-{$gracePeriodDays} days"));

            $sql = "UPDATE assinaturas a
                    JOIN faturas f ON f.assinatura_id = a.id
                    SET a.status = 'inadimplente', a.updated_at = NOW()
                    WHERE a.status IN ('ativa', 'trial')
                    AND f.status = 'pending'
                    AND f.due_date < ?
                    AND a.id NOT IN (
                        SELECT DISTINCT assinatura_id FROM faturas WHERE status = 'paid'
                    )";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$graceDate]);

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erro ao marcar inadimplentes: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Suspende uma assinatura
     */
    public function suspendSubscription($assinaturaId) {
        $sql = "UPDATE assinaturas SET status = 'suspensa', updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$assinaturaId]);
    }

    /**
     * Cancela uma assinatura
     */
    public function cancelSubscription($assinaturaId, $cancelAt = null) {
        $cancelDate = $cancelAt ?? date('Y-m-d');
        $sql = "UPDATE assinaturas SET
                status = 'cancelada',
                cancel_at = ?,
                canceled_at = NOW(),
                updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$cancelDate, $assinaturaId]);
    }

    /**
     * Busca plano pelo slug
     */
    private function getPlanBySlug($slug) {
        $sql = "SELECT * FROM planos WHERE slug = ? AND ativo = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca assinatura ativa de uma loja
     * CORRIGIDO: Apenas status realmente ativos (trial e ativa)
     * Exclui: inadimplente, cancelada, suspensa
     */
    public function getActiveSubscriptionByStore($lojaId) {
        $sql = "SELECT * FROM assinaturas
                WHERE loja_id = ? AND tipo = 'loja'
                AND status IN ('trial', 'ativa')
                ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$lojaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca faturas de uma assinatura
     */
    public function getInvoicesBySubscription($assinaturaId) {
        $sql = "SELECT * FROM faturas WHERE assinatura_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$assinaturaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gera número único para fatura
     */
    private function generateInvoiceNumber() {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $random = strtoupper(substr(uniqid(), -6));
        return "{$prefix}-{$year}{$month}-{$random}";
    }

    /**
     * Busca assinatura por ID
     */
    public function getSubscriptionById($id) {
        $sql = "SELECT a.*, p.nome as plano_nome, p.slug as plano_slug, p.features_json,
                       l.nome_fantasia, l.email as loja_email
                FROM assinaturas a
                JOIN planos p ON a.plano_id = p.id
                LEFT JOIN lojas l ON a.loja_id = l.id
                WHERE a.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Faz upgrade de plano com cálculo de valor proporcional
     *
     * @param int $assinaturaId ID da assinatura
     * @param string $newPlanoSlug Slug do novo plano
     * @param string|null $newCiclo Novo ciclo (se quiser mudar)
     * @return array ['success' => bool, 'message' => string, 'fatura_id' => int|null]
     */
    public function upgradeSubscription($assinaturaId, $newPlanoSlug, $newCiclo = null) {
        try {
            // Buscar assinatura atual com plano
            $sql = "SELECT a.*, p.id as plano_atual_id, p.preco_mensal as preco_atual_mensal,
                           p.preco_anual as preco_atual_anual, p.nome as plano_atual_nome
                    FROM assinaturas a
                    JOIN planos p ON a.plano_id = p.id
                    WHERE a.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$assinaturaId]);
            $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assinatura) {
                return ['success' => false, 'message' => 'Assinatura não encontrada'];
            }

            // Buscar novo plano
            $newPlano = $this->getPlanBySlug($newPlanoSlug);
            if (!$newPlano) {
                return ['success' => false, 'message' => 'Plano não encontrado'];
            }

            // Se não especificou novo ciclo, manter o atual
            $ciclo = $newCiclo ?? $assinatura['ciclo'];

            // Calcular preços
            $precoAtual = $assinatura['ciclo'] === 'yearly'
                ? $assinatura['preco_atual_anual']
                : $assinatura['preco_atual_mensal'];

            $precoNovo = $ciclo === 'yearly'
                ? $newPlano['preco_anual']
                : $newPlano['preco_mensal'];

            // Calcular dias restantes no período atual
            $hoje = new DateTime();
            $fimPeriodo = new DateTime($assinatura['current_period_end']);
            $diasRestantes = $hoje->diff($fimPeriodo)->days;

            // Se já passou do período, não cobrar proporcional
            if ($diasRestantes <= 0) {
                $diasRestantes = 0;
                $valorProporcional = 0;
            } else {
                // Calcular valor proporcional da diferença de preço
                $diasTotaisPeriodo = $ciclo === 'yearly' ? 365 : 30;
                $diferencaPreco = $precoNovo - $precoAtual;

                // Apenas cobra proporcional se for upgrade (preço maior)
                if ($diferencaPreco > 0) {
                    $valorProporcional = ($diferencaPreco / $diasTotaisPeriodo) * $diasRestantes;
                } else {
                    // Downgrade - não gera crédito, apenas aplica no próximo período
                    $valorProporcional = 0;
                }
            }

            // Atualizar assinatura
            $sql = "UPDATE assinaturas SET
                    plano_id = ?,
                    ciclo = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$newPlano['id'], $ciclo, $assinaturaId]);

            $faturaId = null;

            // Se há valor proporcional a cobrar, gerar fatura
            if ($valorProporcional > 0) {
                $result = $this->generateInvoiceForSubscription(
                    $assinaturaId,
                    date('Y-m-d', strtotime('+3 days')), // Vence em 3 dias
                    $valorProporcional
                );

                if ($result['success']) {
                    $faturaId = $result['fatura_id'];
                }
            }

            // Registrar histórico da mudança
            $this->logSubscriptionChange(
                $assinaturaId,
                $assinatura['plano_atual_id'],
                $newPlano['id'],
                $assinatura['ciclo'],
                $ciclo,
                $valorProporcional > 0 ? 'upgrade' : 'downgrade',
                $valorProporcional
            );

            return [
                'success' => true,
                'message' => 'Plano alterado com sucesso',
                'valor_proporcional' => $valorProporcional,
                'fatura_id' => $faturaId,
                'dias_restantes' => $diasRestantes
            ];

        } catch (Exception $e) {
            error_log("Erro ao fazer upgrade: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao processar upgrade: ' . $e->getMessage()];
        }
    }

    /**
     * Registra mudança de plano no log (para auditoria)
     */
    private function logSubscriptionChange($assinaturaId, $planoAntigoId, $planoNovoId, $cicloAntigo, $cicloNovo, $tipoMudanca, $valorAjuste) {
        try {
            $sql = "INSERT INTO assinaturas_historico (
                    assinatura_id, plano_antigo_id, plano_novo_id,
                    ciclo_antigo, ciclo_novo, tipo_mudanca, valor_ajuste
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $assinaturaId, $planoAntigoId, $planoNovoId,
                $cicloAntigo, $cicloNovo, $tipoMudanca, $valorAjuste
            ]);
        } catch (Exception $e) {
            // Se a tabela não existe, apenas logar (não falhar o upgrade)
            error_log("Aviso: Não foi possível registrar histórico de mudança: " . $e->getMessage());
        }
    }

    /**
     * Lista todas as assinaturas com filtros opcionais
     */
    public function listSubscriptions($filters = []) {
        $sql = "SELECT a.*, p.nome as plano_nome, l.nome_fantasia
                FROM assinaturas a
                JOIN planos p ON a.plano_id = p.id
                LEFT JOIN lojas l ON a.loja_id = l.id
                WHERE 1=1";

        $params = [];

        if (isset($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['loja_id'])) {
            $sql .= " AND a.loja_id = ?";
            $params[] = $filters['loja_id'];
        }

        $sql .= " ORDER BY a.created_at DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
