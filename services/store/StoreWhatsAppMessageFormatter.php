<?php

declare(strict_types=1);

namespace App\Services\Store;

final class StoreWhatsAppMessageFormatter
{
    public function format(
        string $customerName,
        string $storeName,
        string $code,
        int $grossAmountCents,
        int $balanceUsedCents,
        int $cashbackCents,
        int $currentBalanceCents
    ): string {
        $customer = $this->firstName($customerName);
        $store = $this->plainText($storeName, 'Loja parceira');
        $transactionCode = $this->plainText($code, 'Não informado');
        $grossAmountCents = max(0, $grossAmountCents);
        $balanceUsedCents = min($grossAmountCents, max(0, $balanceUsedCents));
        $paidAmountCents = max(0, $grossAmountCents - $balanceUsedCents);
        $cashbackCents = max(0, $cashbackCents);
        $currentBalanceCents = max(0, $currentBalanceCents);

        $lines = [
            '✅ *Compra aprovada!*',
            '',
            "Olá, {$customer}! 👋",
            "Sua compra na loja *{$store}* foi aprovada com sucesso.",
            '',
            '🧾 *Resumo da compra*',
            "Código: {$transactionCode}",
            'Valor da compra: ' . $this->money($grossAmountCents),
        ];

        if ($balanceUsedCents > 0) {
            $lines[] = 'Saldo utilizado: ' . $this->money($balanceUsedCents);
            if ($balanceUsedCents === $grossAmountCents && $grossAmountCents > 0) {
                $lines[] = 'Pagamento: realizado integralmente com seu saldo Klube Cash';
            }
        }

        $lines[] = 'Valor pago: ' . $this->money($paidAmountCents);
        $lines[] = $cashbackCents > 0
            ? 'Cashback recebido: ' . $this->money($cashbackCents)
            : 'Cashback recebido: esta compra não gerou novo cashback';
        $lines[] = '';
        $lines[] = "💰 *Seu saldo atual na loja {$store}*";
        $lines[] = $this->money($currentBalanceCents);
        $lines[] = '';
        $lines[] = 'Obrigado por usar o *Klube Cash*! 💚';

        return implode("\n", $lines);
    }

    private function firstName(string $name): string
    {
        $name = $this->plainText($name, 'Cliente');
        $parts = preg_split('/\s+/u', $name, 2);

        return trim((string) ($parts[0] ?? '')) ?: 'Cliente';
    }

    private function plainText(string $value, string $fallback): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($value)) ?? '';
        $value = str_replace(['*', '_', '~', '`'], '', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return $value !== '' ? $value : $fallback;
    }

    private function money(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}
