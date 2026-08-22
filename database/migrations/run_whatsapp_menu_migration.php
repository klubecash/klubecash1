<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$apply = in_array('--apply', $argv ?? [], true);
$db = Database::getConnection();
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$sql = (string) file_get_contents(__DIR__ . '/create_whatsapp_menu.sql');
$statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
$tables = [
    'whatsapp_conversations',
    'whatsapp_auth_challenges',
    'whatsapp_bot_messages',
    'whatsapp_action_audit',
];

$existing = [];
$lookup = $db->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table'
);
foreach ($tables as $table) {
    $lookup->execute([':schema' => $databaseName, ':table' => $table]);
    if ((int) $lookup->fetchColumn() > 0) {
        $existing[] = $table;
    }
}

$columnLookup = $db->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS '
    . 'WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND COLUMN_NAME=:column'
);
$columnLookup->execute([
    ':schema' => $databaseName,
    ':table' => 'whatsapp_bot_messages',
    ':column' => 'delivery_payload',
]);
$deliveryPayloadPresent = (int) $columnLookup->fetchColumn() > 0;

if ($apply) {
    foreach ($statements as $statement) {
        $db->exec($statement);
    }
    if (!$deliveryPayloadPresent) {
        $db->exec('ALTER TABLE whatsapp_bot_messages ADD COLUMN delivery_payload LONGTEXT NULL AFTER sender_key');
        $deliveryPayloadPresent = true;
    }
}

echo json_encode([
    'mode' => $apply ? 'applied' : 'dry-run',
    'database' => $databaseName,
    'tables' => $tables,
    'alreadyPresent' => $existing,
    'pending' => array_values(array_diff($tables, $existing)),
    'deliveryPayloadPresent' => $deliveryPayloadPresent,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
