<?php

declare(strict_types=1);

namespace App\Services\StoreProvision;

use PDO;

require_once __DIR__ . '/StoreProvisionService.php';

/**
 * Cadastro do lojista em etapas, depois de assinar na Loja do Grupo Klube. A loja já nasce aprovada; o painel só abre
 * quando as 4 etapas terminam. Cada etapa grava na hora (dá para sair e voltar de onde parou).
 *  1 Dados do negócio · 2 Endereço · 3 Cashback · 4 Revisar e concluir
 */
final class StoreOnboardingService
{
    public const STEPS = 4;
    public const CATEGORIES = ['Alimentação', 'Vestuário', 'Eletrônicos', 'Casa e Decoração', 'Beleza e Saúde', 'Serviços', 'Educação', 'Entretenimento', 'Outros'];
    public const STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function __construct(private PDO $db)
    {
    }

    /** Há cadastro pendente para esta loja? Falha na checagem nunca tranca a pessoa fora (devolve false). */
    public function pending(int $storeId): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM loja_onboarding WHERE store_id = ? AND completed_at IS NULL LIMIT 1');
            $stmt->execute([$storeId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $error) {
            return false; // tabela ainda não existe (nenhuma loja foi criada por assinatura) ou banco indisponível
        }
    }

    /** @return array<string, mixed> dados atuais para preencher as telas */
    public function load(int $storeId): array
    {
        $store = $this->db->prepare('SELECT l.id, l.nome_fantasia, l.razao_social, l.cnpj, l.telefone, l.categoria, l.descricao, l.website, l.cashback_ativo, l.porcentagem_cliente, l.status, u.email AS usuario_email
            FROM lojas l LEFT JOIN usuarios u ON u.id = l.usuario_id WHERE l.id = ? LIMIT 1');
        $store->execute([$storeId]);
        $row = $store->fetch(PDO::FETCH_ASSOC) ?: [];
        $addr = $this->db->prepare('SELECT cep, logradouro, numero, complemento, bairro, cidade, estado FROM lojas_endereco WHERE loja_id = ? ORDER BY id LIMIT 1');
        $addr->execute([$storeId]);
        $address = $addr->fetch(PDO::FETCH_ASSOC) ?: [];
        $ob = $this->db->prepare('SELECT step FROM loja_onboarding WHERE store_id = ? LIMIT 1');
        $ob->execute([$storeId]);
        return ['store' => $row, 'address' => $address, 'step' => max(1, min(self::STEPS, (int) ($ob->fetchColumn() ?: 1)))];
    }

    /**
     * Valida e grava uma etapa. Devolve a próxima etapa ou a lista de erros em português.
     * @param array<string, mixed> $input
     * @return array{ok:bool, step:int, errors:list<string>}
     */
    public function save(int $storeId, int $step, array $input): array
    {
        $errors = [];
        if ($step < 1 || $step > self::STEPS) { return ['ok' => false, 'step' => 1, 'errors' => ['Etapa inválida.']]; }
        if ($step === 1) {
            $name = $this->text($input['nome_fantasia'] ?? '', 100);
            $legal = $this->text($input['razao_social'] ?? '', 150);
            $phone = preg_replace('/\D+/', '', (string) ($input['telefone'] ?? '')) ?? '';
            $category = (string) ($input['categoria'] ?? '');
            $about = $this->text($input['descricao'] ?? '', 500);
            if (mb_strlen($name) < 2) { $errors[] = 'Informe o nome da loja.'; }
            if (mb_strlen($legal) < 2) { $errors[] = 'Informe a razão social (ou o seu nome, se for MEI ou pessoa física).'; }
            if (strlen($phone) < 10 || strlen($phone) > 13) { $errors[] = 'Informe um telefone válido com DDD.'; }
            if (!in_array($category, self::CATEGORIES, true)) { $errors[] = 'Escolha uma categoria.'; }
            if ($errors === []) {
                $this->db->prepare('UPDATE lojas SET nome_fantasia = ?, razao_social = ?, telefone = ?, categoria = ?, descricao = ? WHERE id = ?')
                    ->execute([$name, $legal, substr($phone, 0, 20), $category, $about !== '' ? $about : null, $storeId]);
            }
        } elseif ($step === 2) {
            $cep = preg_replace('/\D+/', '', (string) ($input['cep'] ?? '')) ?? '';
            $street = $this->text($input['logradouro'] ?? '', 255);
            $number = $this->text($input['numero'] ?? '', 20);
            $comp = $this->text($input['complemento'] ?? '', 100);
            $district = $this->text($input['bairro'] ?? '', 100);
            $city = $this->text($input['cidade'] ?? '', 100);
            $state = strtoupper((string) ($input['estado'] ?? ''));
            if (strlen($cep) !== 8) { $errors[] = 'Informe o CEP com 8 números.'; }
            if ($street === '' || $number === '' || $district === '' || $city === '') { $errors[] = 'Preencha rua, número, bairro e cidade.'; }
            if (!in_array($state, self::STATES, true)) { $errors[] = 'Escolha o estado.'; }
            if ($errors === []) {
                $has = $this->db->prepare('SELECT id FROM lojas_endereco WHERE loja_id = ? ORDER BY id LIMIT 1');
                $has->execute([$storeId]);
                $id = (int) ($has->fetchColumn() ?: 0);
                $vals = [substr($cep, 0, 8), $street, $number, $comp !== '' ? $comp : null, $district, $city, $state];
                if ($id > 0) {
                    $this->db->prepare('UPDATE lojas_endereco SET cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ? WHERE id = ?')->execute([...$vals, $id]);
                } else {
                    $this->db->prepare('INSERT INTO lojas_endereco (cep, logradouro, numero, complemento, bairro, cidade, estado, loja_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$vals, $storeId]);
                }
            }
        } elseif ($step === 3) {
            $active = ($input['cashback_ativo'] ?? '1') === '1' ? 1 : 0;
            $this->db->prepare('UPDATE lojas SET cashback_ativo = ?, data_config_cashback = NOW() WHERE id = ?')->execute([$active, $storeId]);
        } elseif ($step === 4) {
            return $this->complete($storeId);
        }
        if ($errors !== []) { return ['ok' => false, 'step' => $step, 'errors' => $errors]; }
        $next = min(self::STEPS, $step + 1);
        $this->db->prepare('UPDATE loja_onboarding SET step = GREATEST(step, ?), updated_at = NOW() WHERE store_id = ? AND completed_at IS NULL')->execute([$next, $storeId]);
        return ['ok' => true, 'step' => $next, 'errors' => []];
    }

    /** Só conclui com as etapas 1 e 2 completas; libera o painel. @return array{ok:bool, step:int, errors:list<string>} */
    public function complete(int $storeId): array
    {
        $data = $this->load($storeId);
        $s = $data['store'];
        $a = $data['address'];
        $missing = [];
        if (($s['categoria'] ?? 'Outros') === '' || mb_strlen((string) ($s['telefone'] ?? '')) < 10 || mb_strlen((string) ($s['nome_fantasia'] ?? '')) < 2) { $missing[] = 'Complete os dados do negócio (etapa 1).'; }
        if (($a['cep'] ?? '') === '' || ($a['cidade'] ?? '') === '' || ($a['estado'] ?? '') === '') { $missing[] = 'Complete o endereço (etapa 2).'; }
        if ($missing !== []) { return ['ok' => false, 'step' => 1, 'errors' => $missing]; }
        $this->db->prepare('UPDATE loja_onboarding SET completed_at = NOW(), step = ?, updated_at = NOW() WHERE store_id = ? AND completed_at IS NULL')->execute([self::STEPS, $storeId]);
        return ['ok' => true, 'step' => self::STEPS, 'errors' => []];
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        return mb_substr($text, 0, $max);
    }
}
