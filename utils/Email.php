<?php
// utils/Email.php - VERSÃO CORRIGIDA FINAL
require_once __DIR__ . '/../config/constants.php';

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Caminho para as classes do PHPMailer (já funcionando)
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';

/**
 * Classe Email - VERSÃO FINAL CORRIGIDA
 */
class Email {
    private static $host;
    private static $port;
    private static $username;
    private static $password;
    private static $fromEmail;
    private static $fromName;
    private static $encryption;
    
    /**
     * Inicializa as configurações de SMTP
     */
    private static function init() {
        // Usar as constantes que já estão funcionando
        self::$host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
        self::$port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        self::$username = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'klubecash@klubecash.com';
        self::$password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : 'Aaku_2004@';
        self::$fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@klubecash.com';
        self::$fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Klube Cash';
        self::$encryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
        
        error_log("Email configurado - Host: " . self::$host . ", Port: " . self::$port);
    }
    
    public static function queueEmail($to, $subject, $message, $toName = '') {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO email_queue (to_email, to_name, subject, message, status)
                VALUES (:to_email, :to_name, :subject, :message, 'pending')
            ");
            $stmt->bindParam(':to_email', $to);
            $stmt->bindParam(':to_name', $toName);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('Erro ao enfileirar email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia um email - MÉTODO PRINCIPAL CORRIGIDO
     */
    public static function send($to, $subject, $message, $toName = '', $attachments = []) {
        self::init();
        
        try {
            error_log("🚀 Tentando enviar email para: $to - Assunto: $subject");
            
            $mail = new PHPMailer(true);
            
            // Configurações de servidor (EXATAMENTE como no teste que funcionou)
            $mail->isSMTP();
            $mail->Host = self::$host;
            $mail->SMTPAuth = true;
            $mail->Username = self::$username;
            $mail->Password = self::$password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS explícito
            $mail->Port = self::$port;
            $mail->CharSet = 'UTF-8';
            
            // Configurações SSL (igual ao teste)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Timeout
            $mail->Timeout = 30;
            
            // CORREÇÃO CRÍTICA: Usar sempre klubecash@klubecash.com como remetente
            $mail->setFrom('klubecash@klubecash.com', self::$fromName);
            $mail->addReplyTo('klubecash@klubecash.com', self::$fromName);
            
            // Destinatário
            $mail->addAddress($to, $toName);
            
            // Anexos
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                }
            }
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = self::applyTemplate($message);
            $mail->AltBody = self::stripHtml($message);
            
            // Tentar enviar
            $result = $mail->send();
            
            if ($result) {
                error_log("✅ EMAIL ENVIADO COM SUCESSO para: $to");
                return true;
            } else {
                error_log("❌ FALHA ao enviar email para: $to - ErrorInfo: " . $mail->ErrorInfo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("🚨 EXCEÇÃO ao enviar email: " . $e->getMessage());
            error_log("📍 Arquivo: " . $e->getFile() . " - Linha: " . $e->getLine());
            return false;
        }
    }
    
    /**
     * Envia email de recuperação de senha - MÉTODO ESPECÍFICO
     */
    public static function sendPasswordRecovery($to, $name, $token) {
        error_log("🔐 Iniciando envio de email de recuperação para: $to");
        
        $subject = 'Recuperação de Senha - Klube Cash';
        
        $resetLink = SITE_URL . '/recuperar-senha?token=' . urlencode($token);
        
        $message = "
        <h2>Olá, " . htmlspecialchars($name) . "!</h2>
        <p>Recebemos uma solicitação para redefinir sua senha no Klube Cash.</p>
        <p>Para redefinir sua senha, clique no botão abaixo:</p>
        <p>
            <a href='$resetLink' style='background-color: #FF7A00; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                Redefinir Minha Senha
            </a>
        </p>
        <p><strong>Este link é válido por 2 horas.</strong></p>
        <p>Se você não solicitou esta alteração, por favor ignore este email.</p>
        <p>Atenciosamente,<br>Equipe Klube Cash</p>
        ";
        
        $result = self::send($to, $subject, $message, $name);
        
        if ($result) {
            error_log("✅ Email de recuperação ENVIADO para: $to");
        } else {
            error_log("❌ Email de recuperação FALHOU para: $to");
        }
        
        return $result;
    }
    
    /**
     * Aplica template HTML
     */
    private static function applyTemplate($content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Klube Cash</title>
            <style>
                body { 
                    font-family: Arial, Helvetica, sans-serif; 
                    line-height: 1.6; 
                    color: #333333; 
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .container {
                    max-width: 600px; 
                    margin: 0 auto; 
                    background-color: #ffffff; 
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                }
                .header { 
                    background-color: #FF7A00; 
                    color: white; 
                    padding: 20px; 
                    text-align: center; 
                    border-radius: 5px 5px 0 0;
                }
                .content { 
                    padding: 20px; 
                }
                .footer { 
                    background-color: #f9f9f9; 
                    padding: 15px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #999999; 
                    border-radius: 0 0 5px 5px;
                }
                .btn { 
                    display: inline-block; 
                    background-color: #FF7A00; 
                    color: white; 
                    padding: 10px 20px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 15px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                table th, table td {
                    padding: 10px;
                    border: 1px solid #ddd;
                }
                table th {
                    background-color: #f2f2f2;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Klube Cash</h1>
                </div>
                <div class='content'>
                    $content
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Klube Cash. Todos os direitos reservados.</p>
                    <p>Este é um email automático, por favor não responda.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Remove tags HTML
     */
    private static function stripHtml($html) {
        $text = str_replace(['<br>', '<br/>', '<br />', '<p>', '</p>', '<div>', '</div>'], "\n", $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    /**
     * Email de boas-vindas
     */
    public static function sendWelcome($to, $name) {
        $subject = 'Bem-vindo ao Klube Cash';
        
        $message = '
        <h2>Olá, ' . htmlspecialchars($name) . '!</h2>
        <p>Seja bem-vindo ao Klube Cash! Estamos felizes em tê-lo conosco.</p>
        <p>Com o Klube Cash, você pode ganhar cashback em suas compras nas lojas parceiras.</p>
        <p>Para começar, acesse sua conta e explore as lojas parceiras disponíveis.</p>
        <p><a href="' . SITE_URL . '/views/auth/login.php" class="btn">Acessar Minha Conta</a></p>
        <p>Se você tiver alguma dúvida, não hesite em entrar em contato conosco.</p>
        <p>Atenciosamente,<br>Equipe Klube Cash</p>';
        
        return self::send($to, $subject, $message, $name);
    }
    
    /**
     * Notificação de transação
     */
    public static function sendTransactionNotification($to, $name, $transaction) {
        $subject = 'Nova Transação - Klube Cash';
        
        $message = '
        <h2>Olá, ' . htmlspecialchars($name) . '!</h2>
        <p>Você acabou de receber cashback de uma compra:</p>
        <table>
            <tr>
                <th>Loja</th>
                <td>' . htmlspecialchars($transaction['nome_loja']) . '</td>
            </tr>
            <tr>
                <th>Valor da Compra</th>
                <td>R$ ' . number_format($transaction['valor_total'], 2, ',', '.') . '</td>
            </tr>
            <tr>
                <th>Cashback</th>
                <td>R$ ' . number_format($transaction['valor_cashback'], 2, ',', '.') . '</td>
            </tr>
            <tr>
                <th>Data</th>
                <td>' . date('d/m/Y H:i', strtotime($transaction['data_transacao'])) . '</td>
            </tr>
        </table>
        <p>Para ver todos os detalhes, acesse seu extrato:</p>
        <p><a href="' . SITE_URL . '/views/client/statement.php" class="btn">Ver Meu Extrato</a></p>
        <p>Atenciosamente,<br>Equipe Klube Cash</p>';
        
        return self::queueEmail($to, $subject, $message, $name);
    }
    
    /**
     * Aprovação de loja
     */
    public static function sendStoreApproval($to, $storeName) {
        $subject = 'Loja Aprovada - Klube Cash';
        
        $message = '
        <h2>Parabéns!</h2>
        <p>Sua loja <strong>' . htmlspecialchars($storeName) . '</strong> foi aprovada no Klube Cash!</p>
        <p>Agora seus clientes podem começar a receber cashback em suas compras.</p>
        <p>Para acessar seu painel e gerenciar suas transações, clique no botão abaixo:</p>
        <p><a href="' . SITE_URL . '/views/auth/login.php" class="btn">Acessar Meu Painel</a></p>
        <p>Atenciosamente,<br>Equipe Klube Cash</p>';
        
        return self::send($to, $subject, $message, $storeName);
    }
    
    /**
     * Rejeição de loja
     */
    public static function sendStoreRejection($to, $storeName, $reason = '') {
        $subject = 'Loja Não Aprovada - Klube Cash';
        
        $message = '
        <h2>Olá!</h2>
        <p>Infelizmente, sua loja <strong>' . htmlspecialchars($storeName) . '</strong> não foi aprovada no Klube Cash.</p>';
        
        if (!empty($reason)) {
            $message .= '<p><strong>Motivo:</strong> ' . htmlspecialchars($reason) . '</p>';
        }
        
        $message .= '
        <p>Se tiver dúvidas ou quiser mais informações, entre em contato conosco.</p>
        <p>Atenciosamente,<br>Equipe Klube Cash</p>';
        
        return self::send($to, $subject, $message, $storeName);
    }
    
    /**
     * Email para administrador
     */
    public static function sendToAdmin($subject, $message) {
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@klubecash.com';
        return self::send($adminEmail, $subject, $message, 'Administrador');
    }
    
    /**
     * Teste de conexão SMTP
     */
    public static function testConnection() {
        self::init();
        
        try {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = self::$host;
            $mail->SMTPAuth = true;
            $mail->Username = self::$username;
            $mail->Password = self::$password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = self::$port;
            
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            $mail->Timeout = 30;
            $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
            
            ob_start();
            $result = $mail->smtpConnect();
            $debugInfo = ob_get_clean();
            
            if ($result) {
                $mail->smtpClose();
                return [
                    'status' => true,
                    'message' => 'Conexão com servidor SMTP estabelecida com sucesso!',
                    'debug' => $debugInfo,
                    'config' => [
                        'host' => self::$host,
                        'port' => self::$port,
                        'encryption' => self::$encryption,
                        'username' => self::$username
                    ]
                ];
            } else {
                return [
                    'status' => false,
                    'message' => 'Não foi possível conectar ao servidor SMTP.',
                    'debug' => $debugInfo
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Erro: ' . $e->getMessage(),
                'debug' => $mail->ErrorInfo ?? ''
            ];
        }
    }
    
    /**
     * Teste específico de email de recuperação
     */
    public static function testRecoveryEmail($email = null) {
        $testEmail = $email ?: 'teste@example.com';
        $testToken = 'test_token_' . time();
        
        error_log("🧪 Testando email de recuperação para: $testEmail");
        
        $result = self::sendPasswordRecovery($testEmail, 'Usuário Teste', $testToken);
        
        if ($result) {
            error_log("✅ Teste de email de recuperação: SUCESSO");
        } else {
            error_log("❌ Teste de email de recuperação: FALHOU");
        }
        
        return $result;
    }
}
?>