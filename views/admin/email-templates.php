<?php
// admin/email-templates.php
require_once '../config/database.php';

// Alguns templates prontos para diferentes ocasiões
$templates = [
    'lancamento_proximo' => [
        'nome' => '🚀 Lançamento se Aproxima',
        'assunto' => '⏰ Últimos dias antes do lançamento da Klube Cash!',
        'html' => getTemplateLancamentoProximo()
    ],
    'novidades_desenvolvimento' => [
        'nome' => '🔧 Novidades do Desenvolvimento', 
        'assunto' => '✨ Veja o que estamos preparando para você!',
        'html' => getTemplateDesenvolvimento()
    ],
    'dicas_cashback' => [
        'nome' => '💡 Dicas de Cashback',
        'assunto' => '💰 Como maximizar seu cashback - Dicas exclusivas!',
        'html' => getTemplateDicas()
    ]
];

function getTemplateLancamentoProximo() {
    return '
    <div style="max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif;">
        <div style="background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;">
            <h1 style="margin: 0; font-size: 2rem;">⏰ ÚLTIMA SEMANA!</h1>
            <p style="margin: 1rem 0 0; font-size: 1.2rem;">O lançamento da Klube Cash está chegando!</p>
        </div>
        
        <div style="background: white; padding: 2rem;">
            <h2 style="color: #FF7A00; text-align: center; margin-bottom: 1.5rem;">🎯 Faltam apenas alguns dias!</h2>
            
            <div style="background: #FFF5E6; border-left: 4px solid #FF7A00; padding: 1.5rem; margin: 1.5rem 0;">
                <h3 style="color: #333; margin: 0 0 1rem;">📅 Data de Lançamento:</h3>
                <p style="font-size: 1.5rem; font-weight: bold; color: #FF7A00; margin: 0;">9 de Junho, 18:00</p>
            </div>
            
            <h3 style="color: #333; margin: 1.5rem 0 1rem;">🎁 Benefícios exclusivos para você:</h3>
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                <ul style="color: #666; line-height: 2; margin: 0; padding-left: 1.5rem;">
                    <li><strong>Bônus de R$ 10</strong> para suas primeiras compras</li>
                    <li><strong>Cashback dobrado</strong> na primeira semana</li>
                    <li><strong>Acesso antecipado</strong> às melhores ofertas</li>
                    <li><strong>Suporte premium</strong> por 30 dias</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 2rem 0;">
                <a href="https://klubecash.com" style="background: #FF7A00; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block; font-size: 1.1rem;">
                    🚀 Estar Pronto no Lançamento
                </a>
            </div>
        </div>
    </div>';
}

// Função para exibir interface de seleção de templates
function mostrarSeletorTemplates() {
    global $templates;
    echo '<div class="templates-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin: 2rem 0;">';
    
    foreach ($templates as $key => $template) {
        echo '<div class="template-card" style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; cursor: pointer;" onclick="selecionarTemplate(\'' . $key . '\')">';
        echo '<h4 style="color: #FF7A00; margin-bottom: 0.5rem;">' . $template['nome'] . '</h4>';
        echo '<p style="color: #666; font-size: 0.9rem;">' . $template['assunto'] . '</p>';
        echo '</div>';
    }
    
    echo '</div>';
    
    echo '<script>
    function selecionarTemplate(templateKey) {
        const templates = ' . json_encode($templates) . ';
        const template = templates[templateKey];
        
        document.getElementById("assunto").value = template.assunto;
        document.getElementById("conteudo_html").value = template.html;
        
        // Highlight visual
        document.querySelectorAll(".template-card").forEach(card => {
            card.style.borderColor = "#e2e8f0";
        });
        event.target.closest(".template-card").style.borderColor = "#FF7A00";
    }
    </script>';
}
?>