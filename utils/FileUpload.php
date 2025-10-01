<?php
// utils/FileUpload.php
/**
 * Classe para gerenciar uploads de arquivos no sistema Klube Cash
 * Oferece funcionalidades para validação, processamento e armazenamento seguro de arquivos
 */
class FileUpload {
    // Diretório base para uploads
    private $baseDir;
    
    // Tipos de arquivos permitidos
    private $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'receipt' => ['jpg', 'jpeg', 'png', 'pdf']
    ];
    
    // Tamanhos máximos por tipo (em bytes)
    private $maxSizes = [
        'image' => 5242880,    // 5MB
        'document' => 10485760, // 10MB
        'receipt' => 3145728    // 3MB
    ];
    
    // Configurações de upload
    private $config = [
        'rename' => true,      // Renomear arquivos para evitar conflitos
        'create_dirs' => true, // Criar diretórios se não existirem
        'validate_mime' => true, // Validar tipo MIME
        'log_errors' => true   // Registrar erros em log
    ];
    

    /**
    * Processa o upload de um arquivo
    * 
    * @param array $file Dados do arquivo ($_FILES['input_name'])
    * @param string $uploadDir Diretório para salvar
    * @param string $fileName Nome do arquivo base (sem extensão)
    * @param array $allowedExtensions Extensões permitidas
    * @return array Resultado do upload com status e mensagem
    */
    public static function processUpload($file, $uploadDir, $fileName, $allowedExtensions) {
        // Verificar se o upload é válido
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'status' => false,
                'message' => 'Arquivo não enviado ou erro no upload: ' . $file['error']
            ];
        }
        
        // Verificar extensão
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'status' => false,
                'message' => 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', $allowedExtensions)
            ];
        }
        
        // Criar nome completo do arquivo
        $finalFileName = $fileName . '.' . $extension;
        $targetPath = $uploadDir . '/' . $finalFileName;
        
        // Mover arquivo
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'status' => true,
                'message' => 'Arquivo enviado com sucesso',
                'filename' => $finalFileName,
                'path' => $targetPath
            ];
        } else {
            return [
                'status' => false,
                'message' => 'Falha ao mover o arquivo enviado para o destino'
            ];
        }
    }
    
    /**
     * Construtor
     * 
     * @param string $baseDir Diretório base para uploads (opcional)
     * @param array $config Configurações customizadas (opcional)
     */
    public function __construct($baseDir = null, $config = []) {
        // Definir diretório base
        if ($baseDir) {
            $this->baseDir = rtrim($baseDir, '/');
        } else {
            $this->baseDir = rtrim(dirname(__DIR__), '/') . '/uploads';
        }
        
        // Mesclar configurações customizadas
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }
    
    /**
     * Processa um arquivo enviado via formulário
     * 
     * @param array $file Arquivo enviado ($_FILES['campo'])
     * @param string $type Tipo de arquivo (image, document, receipt)
     * @param string $subDir Subdiretório para armazenar o arquivo (opcional)
     * @return array Resultado do upload com status e informações
     */
    public function process($file, $type, $subDir = '') {
        // Verificar se o arquivo foi enviado corretamente
        if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->handleError('Erro no envio do arquivo: ' . $this->getUploadErrorMessage($file['error'] ?? 0));
        }
        
        // Validar tipo de arquivo
        if (!isset($this->allowedTypes[$type])) {
            return $this->handleError("Tipo de arquivo '$type' não configurado");
        }
        
        // Obter informações do arquivo
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmpPath = $file['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validar extensão
        if (!in_array($fileExtension, $this->allowedTypes[$type])) {
            $allowedExtensions = implode(', ', $this->allowedTypes[$type]);
            return $this->handleError("Extensão de arquivo não permitida. Extensões aceitas: $allowedExtensions");
        }
        
        // Validar tamanho
        if ($fileSize > $this->maxSizes[$type]) {
            $maxSizeMB = $this->maxSizes[$type] / 1048576; // Converter para MB
            return $this->handleError("Arquivo excede o tamanho máximo permitido de {$maxSizeMB}MB");
        }
        
        // Validar tipo MIME se configurado
        if ($this->config['validate_mime']) {
            if (!$this->validateMimeType($fileTmpPath, $type, $fileExtension)) {
                return $this->handleError("Tipo de arquivo inválido");
            }
        }
        
        // Preparar diretório de destino
        $targetDir = $this->baseDir;
        
        // Adicionar tipo como subdiretório se não fornecido explicitamente
        if (empty($subDir)) {
            $subDir = $type . 's'; // Ex: images, documents, receipts
        }
        
        $targetDir .= '/' . trim($subDir, '/');
        
        // Criar diretório se não existir e configurado para criar
        if (!is_dir($targetDir) && $this->config['create_dirs']) {
            if (!mkdir($targetDir, 0755, true)) {
                return $this->handleError("Não foi possível criar o diretório para upload");
            }
        }
        
        // Gerar nome de arquivo único se configurado para renomear
        if ($this->config['rename']) {
            $newFileName = $this->generateUniqueFileName($fileExtension, $type);
        } else {
            // Sanitizar nome do arquivo original
            $newFileName = $this->sanitizeFileName($fileName);
        }
        
        // Caminho completo do arquivo de destino
        $targetFilePath = $targetDir . '/' . $newFileName;
        
        // Tentar mover o arquivo para o destino
        if (!move_uploaded_file($fileTmpPath, $targetFilePath)) {
            return $this->handleError("Falha ao mover o arquivo para o destino");
        }
        
        // Retornar informações do upload bem-sucedido
        return [
            'status' => true,
            'message' => 'Arquivo enviado com sucesso',
            'data' => [
                'original_name' => $fileName,
                'saved_name' => $newFileName,
                'extension' => $fileExtension,
                'size' => $fileSize,
                'path' => $targetFilePath,
                'relative_path' => $subDir . '/' . $newFileName,
                'url' => $this->getFileUrl($subDir, $newFileName)
            ]
        ];
    }
    
    /**
     * Processa múltiplos arquivos enviados via formulário
     * 
     * @param array $files Array de arquivos enviados
     * @param string $type Tipo de arquivo
     * @param string $subDir Subdiretório (opcional)
     * @return array Resultados dos uploads
     */
    public function processMultiple($files, $type, $subDir = '') {
        $results = [];
        
        foreach ($files as $index => $file) {
            $results[$index] = $this->process($file, $type, $subDir);
        }
        
        return $results;
    }
    
    /**
     * Processa arquivos enviados como array múltiplo via formulário
     * Ex: <input type="file" name="files[]" multiple>
     * 
     * @param array $filesArray Array $_FILES['campo']
     * @param string $type Tipo de arquivo
     * @param string $subDir Subdiretório (opcional)
     * @return array Resultados dos uploads
     */
    public function processArrayFiles($filesArray, $type, $subDir = '') {
        $results = [];
        
        // Verificar se é um upload múltiplo
        if (!isset($filesArray['tmp_name']) || !is_array($filesArray['tmp_name'])) {
            return [$this->handleError("Formato inválido de arquivos")];
        }
        
        // Reorganizar o array de arquivos
        $fileCount = count($filesArray['tmp_name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($filesArray['error'][$i] !== UPLOAD_ERR_OK) {
                $results[$i] = $this->handleError($this->getUploadErrorMessage($filesArray['error'][$i]));
                continue;
            }
            
            $file = [
                'name' => $filesArray['name'][$i],
                'type' => $filesArray['type'][$i],
                'tmp_name' => $filesArray['tmp_name'][$i],
                'error' => $filesArray['error'][$i],
                'size' => $filesArray['size'][$i]
            ];
            
            $results[$i] = $this->process($file, $type, $subDir);
        }
        
        return $results;
    }
    
    /**
     * Valida o tipo MIME de um arquivo
     * 
     * @param string $filePath Caminho temporário do arquivo
     * @param string $type Tipo esperado
     * @param string $extension Extensão do arquivo
     * @return bool Verdadeiro se o tipo MIME for válido
     */
    private function validateMimeType($filePath, $type, $extension) {
        // Obter tipo MIME
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $filePath);
        finfo_close($fileInfo);
        
        // Mapear extensões para tipos MIME aceitos
        $validMimes = [
            // Imagens
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            // Documentos
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ];
        
        // Verificar se a extensão tem tipos MIME válidos configurados
        if (!isset($validMimes[$extension])) {
            return false;
        }
        
        // Verificar se o tipo MIME do arquivo corresponde aos aceitos para a extensão
        return in_array($mimeType, $validMimes[$extension]);
    }
    
    /**
     * Gera um nome de arquivo único
     * 
     * @param string $extension Extensão do arquivo
     * @param string $prefix Prefixo para o nome (opcional)
     * @return string Nome de arquivo único
     */
    private function generateUniqueFileName($extension, $prefix = '') {
        // Gerar base do nome do arquivo
        if (!empty($prefix)) {
            $prefix = $this->sanitizeFileName($prefix) . '_';
        }
        
        $uniqueName = $prefix . uniqid() . '_' . date('YmdHis');
        
        // Adicionar hash aleatório para maior segurança
        $uniqueName .= '_' . substr(md5(mt_rand()), 0, 8);
        
        // Retornar com extensão
        return $uniqueName . '.' . $extension;
    }
    
    /**
     * Sanitiza um nome de arquivo removendo caracteres inválidos
     * 
     * @param string $fileName Nome do arquivo
     * @return string Nome sanitizado
     */
    private function sanitizeFileName($fileName) {
        // Remover caracteres que não são letras, números, traços ou sublinhados
        $sanitized = preg_replace('/[^\w\-\.]/', '_', $fileName);
        
        // Converter para minúsculas (opcional)
        $sanitized = strtolower($sanitized);
        
        return $sanitized;
    }
    
    /**
     * Obtém a URL pública para um arquivo
     * 
     * @param string $subDir Subdiretório do arquivo
     * @param string $fileName Nome do arquivo
     * @return string URL do arquivo
     */
    private function getFileUrl($subDir, $fileName) {
        // Obter URL base do site
        $baseUrl = $this->getBaseUrl();
        
        // Caminho relativo para uploads
        $uploadsPath = 'uploads/' . trim($subDir, '/') . '/' . $fileName;
        
        return $baseUrl . '/' . $uploadsPath;
    }
    
    /**
     * Obtém a URL base do site
     * 
     * @return string URL base
     */
    private function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // Obter diretório base da aplicação
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        $baseDir = ($baseDir === '/' || $baseDir === '\\') ? '' : $baseDir;
        
        return $protocol . $host . $baseDir;
    }
    
    /**
     * Trata erros de upload
     * 
     * @param string $message Mensagem de erro
     * @return array Resultado de erro
     */
    private function handleError($message) {
        // Registrar erro no log se configurado
        if ($this->config['log_errors']) {
            $this->logError($message);
        }
        
        // Retornar resultado de erro
        return [
            'status' => false,
            'message' => $message,
            'data' => null
        ];
    }
    
    /**
     * Registra erros no arquivo de log
     * 
     * @param string $message Mensagem de erro
     */
    private function logError($message) {
        $logDir = dirname(__DIR__) . '/logs';
        
        // Criar diretório de logs se não existir
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/upload_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Adicionar ao arquivo de log
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Obtém mensagem descritiva para códigos de erro de upload
     * 
     * @param int $errorCode Código de erro
     * @return string Mensagem descritiva
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo no disco',
            UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload',
        ];
        
        return $errors[$errorCode] ?? 'Erro desconhecido no upload';
    }
    
    /**
     * Remove um arquivo enviado
     * 
     * @param string $path Caminho relativo do arquivo
     * @return bool Verdadeiro se o arquivo foi removido
     */
    public function removeFile($path) {
        $fullPath = $this->baseDir . '/' . ltrim($path, '/');
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
    
    /**
     * Define tipos de arquivos permitidos
     * 
     * @param string $type Tipo de arquivo
     * @param array $extensions Array de extensões permitidas
     */
    public function setAllowedTypes($type, $extensions) {
        $this->allowedTypes[$type] = $extensions;
    }
    
    /**
     * Define tamanho máximo para um tipo de arquivo
     * 
     * @param string $type Tipo de arquivo
     * @param int $sizeInBytes Tamanho máximo em bytes
     */
    public function setMaxSize($type, $sizeInBytes) {
        $this->maxSizes[$type] = $sizeInBytes;
    }
    
    /**
     * Obtém o caminho para o arquivo
     * 
     * @param string $relativePath Caminho relativo do arquivo
     * @return string Caminho absoluto
     */
    public function getFilePath($relativePath) {
        return $this->baseDir . '/' . ltrim($relativePath, '/');
    }
}