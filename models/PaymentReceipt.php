<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Armazena comprovantes no MySQL, sem depender do filesystem efemero.
 */
final class PaymentReceipt
{
    // Margem segura abaixo do limite de payload de 4,5 MB da Vercel Function.
    public const MAX_FILE_SIZE = 4 * 1024 * 1024;

    private const STORAGE_MARKER_PREFIX = 'db:payment-receipt:';

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf'],
    ];

    private static bool $storageReady = false;

    /**
     * Valida um item de $_FILES pelo tamanho, extensao e conteudo real.
     *
     * @throws InvalidArgumentException quando o upload nao e valido.
     */
    public static function validateUpload(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new InvalidArgumentException('Envie um comprovante valido.');
        }

        $uploadError = (int) $file['error'];
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(self::uploadErrorMessage($uploadError));
        }

        $temporaryPath = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('O upload do comprovante nao pode ser validado. Envie o arquivo novamente.');
        }

        $reportedSize = isset($file['size']) ? (int) $file['size'] : 0;
        if ($reportedSize <= 0) {
            throw new InvalidArgumentException('O comprovante esta vazio.');
        }
        if ($reportedSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('O comprovante deve ter no maximo 4 MB.');
        }

        return self::readAndValidateFile(
            $temporaryPath,
            self::sanitizeOriginalName((string) ($file['name'] ?? 'comprovante'))
        );
    }

    /**
     * Falha antes do registro do pagamento caso a tabela nao esteja acessivel.
     */
    public static function ensureStorageAvailable(): void
    {
        self::ensureTable(Database::getConnection());
    }

    /**
     * Persiste o BLOB e marca pagamentos_comissao.comprovante atomicamente.
     */
    public static function store(int $paymentId, array $receipt): void
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Pagamento invalido para o comprovante.');
        }

        self::assertReceiptData($receipt);

        $db = Database::getConnection();
        self::ensureTable($db);

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $paymentStmt = $db->prepare(
                'SELECT id FROM pagamentos_comissao WHERE id = :payment_id FOR UPDATE'
            );
            $paymentStmt->execute([':payment_id' => $paymentId]);

            if (!$paymentStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Pagamento nao encontrado para armazenar o comprovante.');
            }

            $receiptStmt = $db->prepare(
                'INSERT INTO pagamentos_comprovantes
                    (pagamento_id, nome_original, mime_type, tamanho_bytes, sha256, conteudo, data_criacao)
                 VALUES
                    (:payment_id, :original_name, :mime_type, :file_size, :sha256, :contents, NOW())
                 ON DUPLICATE KEY UPDATE
                    nome_original = VALUES(nome_original),
                    mime_type = VALUES(mime_type),
                    tamanho_bytes = VALUES(tamanho_bytes),
                    sha256 = VALUES(sha256),
                    conteudo = VALUES(conteudo),
                    data_criacao = NOW()'
            );
            $receiptStmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
            $receiptStmt->bindValue(':original_name', $receipt['original_name'], PDO::PARAM_STR);
            $receiptStmt->bindValue(':mime_type', $receipt['mime_type'], PDO::PARAM_STR);
            $receiptStmt->bindValue(':file_size', $receipt['file_size'], PDO::PARAM_INT);
            $receiptStmt->bindValue(':sha256', $receipt['sha256'], PDO::PARAM_STR);
            $receiptStmt->bindValue(':contents', $receipt['contents'], PDO::PARAM_LOB);
            $receiptStmt->execute();

            $markerStmt = $db->prepare(
                'UPDATE pagamentos_comissao SET comprovante = :marker WHERE id = :payment_id'
            );
            $markerStmt->execute([
                ':marker' => self::markerForPayment($paymentId),
                ':payment_id' => $paymentId,
            ]);

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Busca o comprovante persistido. A autorizacao deve ser verificada antes.
     */
    public static function findByPaymentId(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        $db = Database::getConnection();
        self::ensureTable($db);

        $stmt = $db->prepare(
            'SELECT nome_original, mime_type, tamanho_bytes, sha256, conteudo
             FROM pagamentos_comprovantes
             WHERE pagamento_id = :payment_id
             LIMIT 1'
        );
        $stmt->execute([':payment_id' => $paymentId]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            return null;
        }

        $contents = $receipt['conteudo'];
        if (is_resource($contents)) {
            $contents = stream_get_contents($contents);
        }
        if (!is_string($contents)) {
            throw new RuntimeException('Conteudo invalido no comprovante armazenado.');
        }

        return [
            'original_name' => (string) $receipt['nome_original'],
            'mime_type' => (string) $receipt['mime_type'],
            'file_size' => (int) $receipt['tamanho_bytes'],
            'sha256' => (string) $receipt['sha256'],
            'contents' => $contents,
        ];
    }

    /**
     * Fallback restrito para comprovantes antigos ainda presentes em uploads/.
     */
    public static function loadLegacyFile(string $uploadDirectory, string $storedName): ?array
    {
        $storedName = trim($storedName);
        if (
            $storedName === ''
            || str_starts_with($storedName, self::STORAGE_MARKER_PREFIX)
            || str_contains($storedName, '/')
            || str_contains($storedName, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $storedName)
        ) {
            return null;
        }

        $root = realpath($uploadDirectory);
        if ($root === false || !is_dir($root)) {
            return null;
        }

        $path = realpath($root . DIRECTORY_SEPARATOR . $storedName);
        if ($path === false || !is_file($path)) {
            return null;
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with(strtolower($path), strtolower($rootPrefix))) {
            return null;
        }

        try {
            return self::readAndValidateFile($path, $storedName);
        } catch (InvalidArgumentException $exception) {
            error_log('Comprovante legado rejeitado: ' . $exception->getMessage());
            return null;
        }
    }

    public static function markerForPayment(int $paymentId): string
    {
        return self::STORAGE_MARKER_PREFIX . $paymentId;
    }

    private static function ensureTable(PDO $db): void
    {
        if (self::$storageReady) {
            return;
        }

        // A migration e aplicada no deploy. O request path apenas verifica a
        // dependencia e falha fechado, sem DDL/metadata lock em producao.
        $db->query('SELECT 1 FROM pagamentos_comprovantes LIMIT 1');

        self::$storageReady = true;
    }

    private static function readAndValidateFile(string $path, string $originalName): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            throw new InvalidArgumentException('Formato nao permitido. Envie apenas JPG, PNG ou PDF.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            throw new InvalidArgumentException('O comprovante esta vazio.');
        }
        if ($size > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('O comprovante deve ter no maximo 4 MB.');
        }

        if (!class_exists('finfo')) {
            throw new RuntimeException('A extensao Fileinfo do PHP e necessaria para validar comprovantes.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);
        if (!is_string($mimeType) || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new InvalidArgumentException('O conteudo do arquivo nao e um JPG, PNG ou PDF valido.');
        }

        if (!in_array($extension, self::ALLOWED_MIME_TYPES[$mimeType], true)) {
            throw new InvalidArgumentException('A extensao do comprovante nao corresponde ao conteudo do arquivo.');
        }

        if ($mimeType === 'image/jpeg' || $mimeType === 'image/png') {
            $imageInfo = @getimagesize($path);
            $expectedType = $mimeType === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
            if ($imageInfo === false || ($imageInfo[2] ?? null) !== $expectedType) {
                throw new InvalidArgumentException('A imagem do comprovante esta corrompida ou e invalida.');
            }
        }

        $contents = file_get_contents($path);
        if ($contents === false || strlen($contents) !== $size) {
            throw new RuntimeException('Nao foi possivel ler o comprovante por completo.');
        }

        return [
            'original_name' => self::sanitizeOriginalName($originalName),
            'mime_type' => $mimeType,
            'file_size' => $size,
            'sha256' => hash('sha256', $contents),
            'contents' => $contents,
        ];
    }

    private static function sanitizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $stem = (string) pathinfo($name, PATHINFO_FILENAME);
        $stem = trim($stem, " .\t\n\r\0\x0B");

        if ($stem === '') {
            $stem = 'comprovante';
        }

        $stem = function_exists('mb_strcut')
            ? mb_strcut($stem, 0, 180, 'UTF-8')
            : substr($stem, 0, 180);

        return $extension !== '' ? $stem . '.' . $extension : $stem;
    }

    private static function assertReceiptData(array $receipt): void
    {
        foreach (['original_name', 'mime_type', 'file_size', 'sha256', 'contents'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new InvalidArgumentException('Dados incompletos do comprovante.');
            }
        }

        if (
            !isset(self::ALLOWED_MIME_TYPES[(string) $receipt['mime_type']])
            || (int) $receipt['file_size'] <= 0
            || (int) $receipt['file_size'] > self::MAX_FILE_SIZE
            || strlen((string) $receipt['contents']) !== (int) $receipt['file_size']
            || !hash_equals((string) $receipt['sha256'], hash('sha256', (string) $receipt['contents']))
        ) {
            throw new InvalidArgumentException('Dados invalidos do comprovante.');
        }
    }

    private static function uploadErrorMessage(int $error): string
    {
        if ($error === UPLOAD_ERR_NO_FILE) {
            return 'Envie o comprovante de pagamento.';
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'O comprovante deve ter no maximo 4 MB.';
        }

        if ($error === UPLOAD_ERR_PARTIAL) {
            return 'O upload do comprovante foi interrompido. Envie o arquivo novamente.';
        }

        return 'Nao foi possivel receber o comprovante. Tente novamente.';
    }
}
