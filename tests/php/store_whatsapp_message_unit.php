<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/services/store/StoreWhatsAppMessageFormatter.php';

use App\Services\Store\StoreWhatsAppMessageFormatter;

function expectMessage(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$formatter = new StoreWhatsAppMessageFormatter();

$normal = $formatter->format('Kaua Matheus da Silva Lopes', 'Grupo Kore', 'KC123456', 10000, 0, 500, 2500);
expectMessage(str_contains($normal, "Olá, Kaua! 👋"), 'A saudação não usa somente o primeiro nome.');
expectMessage(str_contains($normal, 'Sua compra na loja *Grupo Kore*'), 'A loja não foi apresentada corretamente.');
expectMessage(!str_contains($normal, 'Saldo utilizado:'), 'Venda sem uso de saldo exibiu a linha indevida.');
expectMessage(str_contains($normal, 'Valor pago: R$ 100,00'), 'Valor pago da venda normal incorreto.');
expectMessage(str_contains($normal, 'Cashback recebido: R$ 5,00'), 'Cashback da venda normal incorreto.');
expectMessage(str_contains($normal, "*Seu saldo atual na loja Grupo Kore*\nR$ 25,00"), 'Saldo atual incorreto.');

$partial = $formatter->format('João da Silva', 'Loja Árvore', 'KC-PARCIAL', 10000, 2000, 400, 3400);
expectMessage(str_contains($partial, 'Olá, João!'), 'Nome com acento foi alterado.');
expectMessage(str_contains($partial, 'Saldo utilizado: R$ 20,00'), 'Saldo parcial não foi exibido.');
expectMessage(str_contains($partial, 'Valor pago: R$ 80,00'), 'Valor pago após saldo parcial incorreto.');
expectMessage(!str_contains($partial, 'integralmente'), 'Saldo parcial foi marcado como integral.');

$full = $formatter->format('Maria', 'Grupo Kore', 'KC-INTEGRAL', 5000, 5000, 0, 0);
expectMessage(str_contains($full, 'Saldo utilizado: R$ 50,00'), 'Saldo integral não foi exibido.');
expectMessage(str_contains($full, 'realizado integralmente com seu saldo Klube Cash'), 'Pagamento integral não foi explicado.');
expectMessage(str_contains($full, 'Valor pago: R$ 0,00'), 'Valor pago da compra integral deveria ser zero.');
expectMessage(str_contains($full, 'esta compra não gerou novo cashback'), 'Cashback zero não recebeu mensagem específica.');
expectMessage(str_contains($full, "*Seu saldo atual na loja Grupo Kore*\nR$ 0,00"), 'Saldo final zero não foi exibido.');

$fallback = $formatter->format("  \n ", '*Loja_Teste*', '', 100, 0, 0, 0);
expectMessage(str_contains($fallback, 'Olá, Cliente!'), 'Fallback do nome não foi aplicado.');
expectMessage(str_contains($fallback, '*LojaTeste*'), 'Caracteres de formatação da loja não foram higienizados.');
expectMessage(str_contains($fallback, 'Código: Não informado'), 'Fallback do código não foi aplicado.');

echo "OK: mensagens de venda do WhatsApp validadas.\n";
