<?php
// politica-de-privacidade.php - Política de Privacidade do Klube Cash
require_once './config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? ($_SESSION['user_name'] ?? '') : '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - Klube Cash</title>
    
    <!-- Meta tags -->
    <meta name="description" content="Política de Privacidade do Klube Cash - Saiba como protegemos e utilizamos seus dados pessoais em nossa plataforma">
    <meta name="robots" content="index, follow">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="assets/images/icons/KlubeCashLOGO.ico">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }

        .header {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            height: 40px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #FF7A00;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #e66a00;
        }

        .main-content {
            padding: 60px 0;
        }

        .legal-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .legal-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 15px;
        }

        .legal-header p {
            font-size: 1.1rem;
            color: #666;
        }

        .legal-content {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .legal-section {
            margin-bottom: 40px;
        }

        .legal-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #FF7A00;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .legal-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 25px 0 15px;
        }

        .legal-section p {
            margin-bottom: 15px;
            line-height: 1.7;
        }

        .legal-section ul {
            margin: 15px 0;
            padding-left: 30px;
        }

        .legal-section li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .highlight {
            background: rgba(255, 122, 0, 0.1);
            padding: 20px;
            border-left: 4px solid #FF7A00;
            border-radius: 8px;
            margin: 20px 0;
        }

        .privacy-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin: 20px 0;
        }

        .privacy-box h4 {
            color: #FF7A00;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .contact-info {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            margin-top: 40px;
        }

        .contact-info h3 {
            color: #FF7A00;
            margin-bottom: 15px;
        }

        .footer {
            background: #1a1a1a;
            color: white;
            padding: 40px 0;
            text-align: center;
        }

        @media (max-width: 768px) {
            .legal-header h1 {
                font-size: 2rem;
            }

            .legal-content {
                padding: 30px 20px;
            }

            .header-content {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <img src="assets/images/logolaranja.png" alt="Klube Cash" class="logo">
                <a href="<?php echo SITE_URL; ?>" class="back-btn">
                    ← Voltar ao Site
                </a>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="container">
            <div class="legal-header">
                <h1>Política de Privacidade</h1>
                <p>Última atualização: <?php echo date('d/m/Y'); ?></p>
            </div>

            <div class="legal-content">
                <div class="legal-section">
                    <h2>1. Introdução</h2>
                    <p>Esta Política de Privacidade descreve como o Klube Cash coleta, usa, armazena e protege suas informações pessoais quando você utiliza nossa plataforma de cashback.</p>
                    
                    <div class="highlight">
                        <strong>Compromisso com sua privacidade:</strong> Respeitamos sua privacidade e estamos comprometidos em proteger seus dados pessoais conforme a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018).
                    </div>
                </div>

                <div class="legal-section">
                    <h2>2. Informações que Coletamos</h2>
                    
                    <h3>2.1 Dados Pessoais de Identificação</h3>
                    <ul>
                        <li>Nome completo</li>
                        <li>CPF</li>
                        <li>Data de nascimento</li>
                        <li>Email</li>
                        <li>Telefone/celular</li>
                        <li>Endereço completo</li>
                    </ul>

                    <h3>2.2 Dados Financeiros</h3>
                    <ul>
                        <li>Informações de conta bancária (para estabelecimentos parceiros)</li>
                        <li>Chave PIX (quando fornecida)</li>
                        <li>Histórico de transações e cashbacks</li>
                    </ul>

                    <h3>2.3 Dados de Navegação</h3>
                    <ul>
                        <li>Endereço IP</li>
                        <li>Tipo de navegador e dispositivo</li>
                        <li>Páginas visitadas em nossa plataforma</li>
                        <li>Horários de acesso</li>
                        <li>Cookies e tecnologias similares</li>
                    </ul>

                    <h3>2.4 Dados de Localização</h3>
                    <ul>
                        <li>Localização aproximada baseada no IP</li>
                        <li>Localização precisa (apenas com sua autorização)</li>
                    </ul>
                </div>

                <div class="legal-section">
                    <h2>3. Como Coletamos suas Informações</h2>
                    
                    <div class="privacy-box">
                        <h4>Coleta Direta</h4>
                        <p>Quando você se cadastra, atualiza seu perfil, faz compras ou entra em contato conosco.</p>
                    </div>

                    <div class="privacy-box">
                        <h4>Coleta Automática</h4>
                        <p>Através de cookies, logs de servidor e outras tecnologias durante sua navegação.</p>
                    </div>

                    <div class="privacy-box">
                        <h4>Parceiros Comerciais</h4>
                        <p>Estabelecimentos parceiros compartilham dados de transações para processamento do cashback.</p>
                    </div>
                </div>

                <div class="legal-section">
                    <h2>4. Como Utilizamos suas Informações</h2>
                    
                    <h3>4.1 Prestação do Serviço</h3>
                    <ul>
                        <li>Processar seu cadastro e autenticação</li>
                        <li>Calcular e creditar cashback</li>
                        <li>Gerenciar seu saldo em cada loja parceira</li>
                        <li>Processar transações e pagamentos</li>
                        <li>Fornecer suporte ao cliente</li>
                    </ul>

                    <h3>4.2 Comunicação</h3>
                    <ul>
                        <li>Enviar notificações sobre transações</li>
                        <li>Informar sobre promoções e ofertas</li>
                        <li>Responder suas dúvidas e solicitações</li>
                        <li>Enviar atualizações importantes sobre o serviço</li>
                    </ul>

                    <h3>4.3 Melhoria dos Serviços</h3>
                    <ul>
                        <li>Analisar padrões de uso da plataforma</li>
                        <li>Desenvolver novos recursos</li>
                        <li>Personalizar sua experiência</li>
                        <li>Realizar pesquisas de satisfação</li>
                    </ul>

                    <h3>4.4 Segurança e Prevenção a Fraudes</h3>
                    <ul>
                        <li>Detectar atividades suspeitas</li>
                        <li>Prevenir fraudes e abusos</li>
                        <li>Garantir a segurança da plataforma</li>
                        <li>Cumprir obrigações legais</li>
                    </ul>
                </div>

                <div class="legal-section">
                    <h2>5. Compartilhamento de Informações</h2>
                    
                    <h3>5.1 Com Estabelecimentos Parceiros</h3>
                    <p>Compartilhamos apenas as informações necessárias para:</p>
                    <ul>
                        <li>Identificar você como membro Klube Cash</li>
                        <li>Processar o cashback da sua compra</li>
                        <li>Validar transações</li>
                    </ul>

                    <h3>5.2 Com Prestadores de Serviço</h3>
                    <p>Terceiros que nos ajudam a operar a plataforma:</p>
                    <ul>
                        <li>Processadores de pagamento</li>
                        <li>Serviços de hospedagem e armazenamento</li>
                        <li>Ferramentas de análise e marketing</li>
                        <li>Suporte técnico</li>
                    </ul>

                    <h3>5.3 Por Obrigação Legal</h3>
                    <ul>
                        <li>Quando exigido por lei ou ordem judicial</li>
                        <li>Para proteger direitos, propriedade ou segurança</li>
                        <li>Em casos de investigação de fraudes</li>
                    </ul>

                    <div class="highlight">
                        <strong>Nunca vendemos seus dados:</strong> Não comercializamos suas informações pessoais com terceiros para fins de marketing.
                    </div>
                </div>

                <div class="legal-section">
                    <h2>6. Segurança dos Dados</h2>
                    
                    <h3>6.1 Medidas de Proteção</h3>
                    <ul>
                        <li>Criptografia SSL/TLS em todas as transmissões</li>
                        <li>Armazenamento seguro com criptografia</li>
                        <li>Controle de acesso rigoroso</li>
                        <li>Monitoramento contínuo de segurança</li>
                        <li>Backups regulares e seguros</li>
                    </ul>

                    <h3>6.2 Acesso Restrito</h3>
                    <p>Apenas funcionários autorizados têm acesso aos seus dados, e somente quando necessário para prestação do serviço.</p>
                </div>

                <div class="legal-section">
                    <h2>7. Seus Direitos (LGPD)</h2>
                    
                    <p>Conforme a LGPD, você tem os seguintes direitos:</p>
                    
                    <div class="privacy-box">
                        <h4>📋 Acesso</h4>
                        <p>Confirmar a existência e acessar seus dados pessoais</p>
                    </div>

                    <div class="privacy-box">
                        <h4>✏️ Correção</h4>
                        <p>Corrigir dados incompletos, inexatos ou desatualizados</p>
                    </div>

                    <div class="privacy-box">
                        <h4>🗑️ Exclusão</h4>
                        <p>Solicitar a eliminação de dados desnecessários ou tratados incorretamente</p>
                    </div>

                    <div class="privacy-box">
                        <h4>📤 Portabilidade</h4>
                        <p>Solicitar a transferência dos seus dados para outro fornecedor</p>
                    </div>

                    <div class="privacy-box">
                        <h4>🚫 Oposição</h4>
                        <p>Opor-se ao tratamento quando desnecessário ou excessivo</p>
                    </div>

                    <p><strong>Para exercer esses direitos, entre em contato através dos canais informados no final desta política.</strong></p>
                </div>

                <div class="legal-section">
                    <h2>8. Cookies e Tecnologias Similares</h2>
                    
                    <h3>8.1 Tipos de Cookies</h3>
                    <ul>
                        <li><strong>Essenciais:</strong> Necessários para o funcionamento da plataforma</li>
                        <li><strong>Performance:</strong> Ajudam a analisar como você usa nosso site</li>
                        <li><strong>Funcionais:</strong> Lembram suas preferências</li>
                        <li><strong>Marketing:</strong> Personalizam anúncios e ofertas</li>
                    </ul>

                    <h3>8.2 Gerenciamento</h3>
                    <p>Você pode controlar cookies através das configurações do seu navegador, mas isso pode afetar algumas funcionalidades da plataforma.</p>
                </div>

                <div class="legal-section">
                    <h2>9. Retenção de Dados</h2>
                    
                    <p>Mantemos seus dados pessoais apenas pelo tempo necessário para:</p>
                    <ul>
                        <li>Cumprir as finalidades para as quais foram coletados</li>
                        <li>Atender obrigações legais e regulamentares</li>
                        <li>Exercer direitos em processos judiciais</li>
                    </ul>

                    <div class="privacy-box">
                        <h4>Período Geral de Retenção</h4>
                        <p>Dados de usuários ativos: enquanto a conta estiver ativa + 5 anos</p>
                        <p>Dados de transações: 10 anos (conforme legislação fiscal)</p>
                        <p>Logs de segurança: 6 meses</p>
                    </div>
                </div>

                <div class="legal-section">
                    <h2>10. Menores de Idade</h2>
                    <p>Nossos serviços são direcionados para pessoas maiores de 18 anos. Não coletamos intencionalmente dados pessoais de menores de idade sem o consentimento dos pais ou responsáveis legais.</p>
                </div>

                <div class="legal-section">
                    <h2>11. Transferência Internacional de Dados</h2>
                    <p>Seus dados são processados principalmente no Brasil. Quando necessário transferir dados para outros países, garantimos nível adequado de proteção conforme a LGPD.</p>
                </div>

                <div class="legal-section">
                    <h2>12. Alterações nesta Política</h2>
                    <p>Esta Política de Privacidade pode ser atualizada periodicamente. Notificaremos sobre mudanças significativas através da plataforma ou por email. A versão mais atual sempre estará disponível em nosso site.</p>
                </div>

                <div class="legal-section">
                    <h2>13. Encarregado de Proteção de Dados (DPO)</h2>
                    <p>Nosso DPO é responsável por garantir o cumprimento da LGPD e pode ser contatado para questões específicas sobre proteção de dados.</p>
                </div>

                <div class="contact-info">
                    <h3>Contato para Questões de Privacidade</h3>
                    <p>Para dúvidas, solicitações ou exercício dos seus direitos:</p>
                    <p><strong>Email:</strong> privacidade@klubecash.com</p>
                    <p><strong>Telefone:</strong> (34) 9999-9999</p>
                    <p><strong>Endereço:</strong> Patos de Minas, MG</p>
                    <p><strong>DPO:</strong> dpo@klubecash.com</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Klube Cash. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>