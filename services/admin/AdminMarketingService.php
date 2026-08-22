<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;
use Throwable;

final class AdminMarketingService
{
    private AdminAuditService $audit;
    private AdminIdempotencyService $idempotency;

    public function __construct(private PDO $db, private int $actorId, private AdminReadService $read)
    {
        $this->audit = new AdminAuditService($db, $actorId);
        $this->idempotency = new AdminIdempotencyService($db, $actorId);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function saveTemplate(?int $id, array $input): array
    {
        $name = trim((string) ($input['name'] ?? '')); $subject = trim((string) ($input['subject'] ?? ''));
        $html = $this->sanitizeHtml((string) ($input['html'] ?? '')); $type = (string) ($input['type'] ?? 'newsletter');
        if ($name === '' || $html === '') { throw new AdminApiException('Nome e conteúdo são obrigatórios.', 422); }
        if (!in_array($type, ['newsletter', 'promocional', 'informativo'], true)) { throw new AdminApiException('Tipo de template inválido.', 422); }
        $before = null;
        if ($id !== null) {
            $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id=:id AND archived_at IS NULL LIMIT 1'); $stmt->execute([':id' => $id]); $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) { throw new AdminApiException('Template não encontrado.', 404); }
            $this->assertCurrentVersion($before, $input['updatedAt'] ?? null);
            $this->db->prepare('UPDATE email_templates SET nome=:name,assunto_padrao=:subject,conteudo_html=:html,tipo=:type,ativo=:active WHERE id=:id')->execute([
                ':name' => $name, ':subject' => $subject, ':html' => $html, ':type' => $type, ':active' => !empty($input['active']) ? 1 : 0, ':id' => $id,
            ]);
        } else {
            $this->db->prepare('INSERT INTO email_templates (nome,assunto_padrao,conteudo_html,tipo,ativo) VALUES (:name,:subject,:html,:type,:active)')->execute([
                ':name' => $name, ':subject' => $subject, ':html' => $html, ':type' => $type, ':active' => !empty($input['active']) ? 1 : 0,
            ]);
            $id = (int) $this->db->lastInsertId();
        }
        $after = ['id' => $id, 'name' => $name, 'subject' => $subject, 'type' => $type, 'active' => !empty($input['active'])];
        $this->audit->record($before ? 'email_template.update' : 'email_template.create', 'email_template', $id, $before ?: null, $after);
        return $after;
    }

    /** @return array<string, mixed> */
    public function archiveTemplate(int $id, ?string $expectedUpdatedAt = null): array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id=:id AND archived_at IS NULL LIMIT 1'); $stmt->execute([':id' => $id]); $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) { throw new AdminApiException('Template não encontrado.', 404); }
        $this->assertCurrentVersion($before, $expectedUpdatedAt);
        $this->db->prepare('UPDATE email_templates SET ativo=0,archived_at=NOW() WHERE id=:id')->execute([':id' => $id]);
        $result = ['id' => $id, 'archived' => true]; $this->audit->record('email_template.archive', 'email_template', $id, $before, $result); return $result;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function saveCampaign(?int $id, array $input): array
    {
        $title = trim((string) ($input['title'] ?? '')); $subject = trim((string) ($input['subject'] ?? ''));
        $html = $this->sanitizeHtml((string) ($input['html'] ?? '')); $text = trim((string) ($input['text'] ?? strip_tags($html)));
        $audience = (array) ($input['audience'] ?? []); $count = $this->read->audienceCount($audience);
        if ($title === '' || $subject === '' || $html === '') { throw new AdminApiException('Título, assunto e conteúdo são obrigatórios.', 422); }
        if ($count === 0) { throw new AdminApiException('O segmento selecionado não possui destinatários.', 422); }
        $before = null;
        if ($id !== null) {
            $stmt = $this->db->prepare("SELECT * FROM email_campaigns WHERE id=:id AND status='rascunho' LIMIT 1"); $stmt->execute([':id' => $id]); $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) { throw new AdminApiException('Somente campanhas em rascunho podem ser editadas.', 409); }
            $this->assertCurrentVersion($before, $input['updatedAt'] ?? null);
            $this->db->prepare('UPDATE email_campaigns SET titulo=:title,assunto=:subject,conteudo_html=:html,conteudo_texto=:text,audience_json=:audience,total_emails=:total WHERE id=:id')->execute([
                ':title' => $title, ':subject' => $subject, ':html' => $html, ':text' => $text, ':audience' => json_encode($audience, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':total' => $count, ':id' => $id,
            ]);
        } else {
            $this->db->prepare("INSERT INTO email_campaigns (titulo,assunto,conteudo_html,conteudo_texto,audience_json,status,total_emails,criado_por) VALUES (:title,:subject,:html,:text,:audience,'rascunho',:total,:actor)")->execute([
                ':title' => $title, ':subject' => $subject, ':html' => $html, ':text' => $text, ':audience' => json_encode($audience, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':total' => $count, ':actor' => (string) $this->actorId,
            ]);
            $id = (int) $this->db->lastInsertId();
        }
        $after = ['id' => $id, 'title' => $title, 'subject' => $subject, 'recipientCount' => $count, 'status' => 'rascunho'];
        $this->audit->record($before ? 'campaign.update' : 'campaign.create', 'campaign', $id, $before ?: null, $after); return $after;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function scheduleCampaign(int $id, array $input, string $key): array
    {
        $scheduledAt = trim((string) ($input['scheduledAt'] ?? '')); $timestamp = strtotime($scheduledAt);
        if ($timestamp === false || $timestamp < time() - 60) { throw new AdminApiException('Informe uma data de agendamento válida.', 422); }
        $payload = ['id' => $id, 'scheduledAt' => date('Y-m-d H:i:s', $timestamp)];
        $state = $this->idempotency->begin('campaign_schedule', $key, $payload);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT * FROM email_campaigns WHERE id=:id AND status='rascunho' LIMIT 1 FOR UPDATE"); $stmt->execute([':id' => $id]); $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) { throw new AdminApiException('Campanha não encontrada ou já processada.', 409); }
            $this->assertCurrentVersion($campaign, $input['updatedAt'] ?? null);
            $audience = (array) (json_decode((string) ($campaign['audience_json'] ?? '{}'), true) ?: []);
            [$sql, $params] = $this->read->audienceQuery($audience, false); $recipients = $this->db->prepare($sql); $recipients->execute($params); $items = $recipients->fetchAll(PDO::FETCH_ASSOC);
            if ($items === []) { throw new AdminApiException('O segmento não possui destinatários válidos.', 422); }
            $delivery = $this->db->prepare("INSERT IGNORE INTO email_envios (campaign_id,email,status,tentativas) VALUES (:campaign,:email,'pendente',0)");
            $queue = $this->db->prepare("INSERT IGNORE INTO email_queue (campaign_id,recipient_id,to_email,to_name,subject,message,status,attempts,next_attempt_at) VALUES (:campaign,:recipient,:email,:name,:subject,:message,'pending',0,:scheduled)");
            foreach ($items as $recipient) {
                $delivery->execute([':campaign' => $id, ':email' => $recipient['email']]);
                $queue->execute([':campaign' => $id, ':recipient' => $recipient['id'], ':email' => $recipient['email'], ':name' => $recipient['nome'], ':subject' => $campaign['assunto'], ':message' => $campaign['conteudo_html'], ':scheduled' => date('Y-m-d H:i:s', $timestamp)]);
            }
            $this->db->prepare("UPDATE email_campaigns SET status='agendado',requires_review=0,data_agendamento=:scheduled,total_emails=:total WHERE id=:id")->execute([':scheduled' => date('Y-m-d H:i:s', $timestamp), ':total' => count($items), ':id' => $id]);
            $this->db->commit();
            $result = ['id' => $id, 'status' => 'agendado', 'recipientCount' => count($items), 'scheduledAt' => date(DATE_ATOM, $timestamp), 'replayed' => false];
            $this->audit->record('campaign.schedule', 'campaign', $id, $campaign, $result); $this->idempotency->complete('campaign_schedule', $key, $result); return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            $this->idempotency->fail('campaign_schedule', $key); throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function cancelCampaign(int $id, ?string $expectedUpdatedAt = null): array
    {
        $stmt = $this->db->prepare("SELECT * FROM email_campaigns WHERE id=:id AND status IN ('rascunho','agendado') LIMIT 1"); $stmt->execute([':id' => $id]); $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) { throw new AdminApiException('A campanha não pode mais ser cancelada.', 409); }
        $this->assertCurrentVersion($before, $expectedUpdatedAt);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE email_campaigns SET status='cancelado' WHERE id=:id")->execute([':id' => $id]);
            $this->db->prepare("UPDATE email_queue SET status='failed',error_message='Campanha cancelada' WHERE campaign_id=:id AND status='pending'")->execute([':id' => $id]);
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $exception; }
        $result = ['id' => $id, 'status' => 'cancelado']; $this->audit->record('campaign.cancel', 'campaign', $id, $before, $result); return $result;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function queueTestEmail(int $id, array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AdminApiException('Informe um e-mail válido para o teste.', 422, ['email' => ['E-mail inválido.']]);
        }
        $stmt = $this->db->prepare('SELECT * FROM email_campaigns WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) { throw new AdminApiException('Campanha não encontrada.', 404); }
        $this->assertCurrentVersion($campaign, $input['updatedAt'] ?? null);
        $queue = $this->db->prepare(
            "INSERT INTO email_queue (campaign_id,recipient_id,to_email,to_name,subject,message,status,attempts,next_attempt_at) "
            . "VALUES (NULL,NULL,:email,'Teste administrativo',:subject,:message,'pending',0,NOW())"
        );
        $queue->execute([
            ':email' => $email,
            ':subject' => '[TESTE] ' . (string) $campaign['assunto'],
            ':message' => $this->sanitizeHtml((string) $campaign['conteudo_html']),
        ]);
        $result = ['queueId' => (int) $this->db->lastInsertId(), 'campaignId' => $id, 'queued' => true];
        $this->audit->record('campaign.test_queued', 'campaign', $id, null, $result);
        return $result;
    }

    /** @param array<string, mixed> $audience @return array<string, mixed> */
    public function audiencePreview(array $audience): array
    {
        return ['recipientCount' => $this->read->audienceCount($audience)];
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? '';
        return trim($html);
    }

    private function assertCurrentVersion(array $row, mixed $expectedUpdatedAt): void
    {
        $expected = trim((string) $expectedUpdatedAt);
        if ($expected === '') { return; }
        $expectedTime = strtotime($expected);
        $currentTime = strtotime((string) ($row['updated_at'] ?? ''));
        if ($expectedTime === false || $currentTime === false || $expectedTime !== $currentTime) {
            throw new AdminApiException('Este registro foi alterado em outra sessão. Atualize a página antes de tentar novamente.', 409);
        }
    }
}
