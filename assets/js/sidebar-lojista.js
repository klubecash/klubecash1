/**
 * Sidebar Lojista Responsiva - Klube Cash
 * JavaScript Ultra Responsivo e Profissional
 */

class SidebarLojistaResponsiva {
    constructor() {
        this.sidebar = document.getElementById('sidebarLojistaResponsiva');
        this.botaoColapsar = document.getElementById('botaoColapsarSidebar');
        this.botaoToggleMobile = document.getElementById('botaoToggleMobile');
        this.overlayMobile = document.getElementById('overlaySidebarMobile');
        this.body = document.body;
        this.isColapsada = false;
        this.isMobileAberta = false;
        this.timeoutMobileToggle = null;
        
        this.inicializar();
    }

    inicializar() {
        this.verificarEstadoInicial();
        this.configurarEventos();
        this.ajustarConteudoPrincipal();
        this.adicionarAtalhosTeeclado();
        
        // Ajustar layout inicial após carregamento
        window.addEventListener('load', () => {
            this.ajustarConteudoPrincipal();
        });
        
        console.log('Sidebar Lojista Responsiva inicializada com sucesso');
    }

    verificarEstadoInicial() {
        // Recuperar estado da sidebar do localStorage
        const estadoSalvo = localStorage.getItem('klube-sidebar-lojista-colapsada');
        
        if (estadoSalvo === 'true' && !this.isMobile()) {
            this.isColapsada = true;
            this.sidebar.classList.add('colapsada');
        }
    }

    configurarEventos() {
        // Eventos para desktop
        if (this.botaoColapsar) {
            this.botaoColapsar.addEventListener('click', () => {
                this.alternarColapsarDesktop();
            });
        }

        // Eventos para mobile
        if (this.botaoToggleMobile) {
            this.botaoToggleMobile.addEventListener('click', (e) => {
                e.stopPropagation();
                this.alternarMobile();
            });
        }

        if (this.overlayMobile) {
            this.overlayMobile.addEventListener('click', () => {
                this.fecharMobile();
            });
        }

        // Eventos de redimensionamento
        window.addEventListener('resize', () => {
            this.gerenciarResponsividade();
        });

        // Eventos de navegação
        this.configurarEventosNavegacao();

        // Evento para fechar sidebar mobile com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile() && this.isMobileAberta) {
                this.fecharMobile();
            }
        });
    }

    configurarEventosNavegacao() {
        const linksMenu = this.sidebar.querySelectorAll('.link-menu-sidebar');
        
        linksMenu.forEach(link => {
            link.addEventListener('click', (e) => {
                // Adicionar efeito de carregamento
                this.adicionarEfeitoCarregamento(link);
                
                // Fechar sidebar mobile após clique
                if (this.isMobile() && this.isMobileAberta) {
                    setTimeout(() => {
                        this.fecharMobile();
                    }, 300);
                }
                
                // Rastrear navegação
                this.rastrearNavegacao(link.dataset.menu);
            });
        });
    }

    adicionarAtalhosTeeclado() {
        document.addEventListener('keydown', (e) => {
            // Ctrl + B para alternar sidebar
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                
                if (this.isMobile()) {
                    this.alternarMobile();
                } else {
                    this.alternarColapsarDesktop();
                }
                
                this.mostrarFeedbackAtalho();
            }
        });
    }

    alternarColapsarDesktop() {
        if (this.isMobile()) return;
        
        this.isColapsada = !this.isColapsada;
        this.sidebar.classList.toggle('colapsada', this.isColapsada);
        
        // Salvar estado no localStorage
        localStorage.setItem('klube-sidebar-lojista-colapsada', this.isColapsada);
        
        // Ajustar conteúdo principal
        this.ajustarConteudoPrincipal();
        
        // Disparar evento customizado
        this.dispararEventoToggle();
        
        // Feedback visual
        this.mostrarFeedback(
            this.isColapsada ? 'Menu minimizado' : 'Menu expandido'
        );
    }

    alternarMobile() {
        if (!this.isMobile()) return;
        
        if (this.isMobileAberta) {
            this.fecharMobile();
        } else {
            this.abrirMobile();
        }
    }

    abrirMobile() {
        this.isMobileAberta = true;
        this.sidebar.classList.add('aberta');
        this.overlayMobile.classList.add('ativo');
        this.body.classList.add('sidebar-mobile-aberta');
        
        // Esconder botão mobile temporariamente
        this.esconderBotaoMobileTemporario();
        
        // Focar no primeiro link
        const primeiroLink = this.sidebar.querySelector('.link-menu-sidebar');
        if (primeiroLink) {
            setTimeout(() => primeiroLink.focus(), 300);
        }
    }

    fecharMobile() {
        this.isMobileAberta = false;
        this.sidebar.classList.remove('aberta');
        this.overlayMobile.classList.remove('ativo');
        this.body.classList.remove('sidebar-mobile-aberta');
        
        // Mostrar botão mobile novamente
        this.mostrarBotaoMobile();
    }

    esconderBotaoMobileTemporario() {
        if (this.botaoToggleMobile) {
            this.botaoToggleMobile.style.opacity = '0';
            this.botaoToggleMobile.style.pointerEvents = 'none';
        }
    }

    mostrarBotaoMobile() {
        if (this.botaoToggleMobile) {
            this.botaoToggleMobile.style.opacity = '1';
            this.botaoToggleMobile.style.pointerEvents = 'auto';
        }
    }

    ajustarConteudoPrincipal() {
        const seletoresConteudo = [
            '.main-content',
            '.content',
            '.page-content',
            'main',
            '.conteudo-principal',
            '.container-principal'
        ];
        
        let conteudoPrincipal = null;
        
        for (const seletor of seletoresConteudo) {
            conteudoPrincipal = document.querySelector(seletor);
            if (conteudoPrincipal) break;
        }
        
        if (conteudoPrincipal) {
            // Remover classes existentes
            conteudoPrincipal.classList.remove('conteudo-principal-ajustado', 'sidebar-colapsada');
            
            if (!this.isMobile()) {
                // Aplicar ajuste para desktop
                conteudoPrincipal.classList.add('conteudo-principal-ajustado');
                
                if (this.isColapsada) {
                    conteudoPrincipal.classList.add('sidebar-colapsada');
                }
            }
        }
    }

    gerenciarResponsividade() {
        const eraDesktop = !this.isMobile();
        
        // Delay para evitar múltiplas execuções durante redimensionamento
        clearTimeout(this.timeoutResponsividade);
        this.timeoutResponsividade = setTimeout(() => {
            if (this.isMobile()) {
                // Mudou para mobile
                this.sidebar.classList.remove('colapsada');
                this.fecharMobile();
            } else {
                // Mudou para desktop
                this.sidebar.classList.remove('aberta');
                this.overlayMobile.classList.remove('ativo');
                this.body.classList.remove('sidebar-mobile-aberta');
                
                // Restaurar estado colapsado se estava definido
                if (this.isColapsada) {
                    this.sidebar.classList.add('colapsada');
                }
            }
            
            this.ajustarConteudoPrincipal();
        }, 150);
    }

    adicionarEfeitoCarregamento(elemento) {
        elemento.classList.add('carregando');
        
        setTimeout(() => {
            elemento.classList.remove('carregando');
        }, 1000);
    }

    dispararEventoToggle() {
        window.dispatchEvent(new CustomEvent('sidebarLojistaToggle', {
            detail: {
                colapsada: this.isColapsada,
                largura: this.isColapsada ? 80 : 280,
                mobile: this.isMobile()
            }
        }));
    }

    rastrearNavegacao(pagina) {
        console.log('Navegação para:', pagina);
        
        // Integração com Google Analytics se disponível
        if (typeof gtag !== 'undefined') {
            gtag('event', 'navigation', {
                'page': pagina,
                'source': 'sidebar-lojista'
            });
        }
    }

    mostrarFeedback(mensagem, tipo = 'info', duracao = 2000) {
        const feedback = document.createElement('div');
        feedback.className = `feedback-sidebar-lojista feedback-${tipo}`;
        feedback.textContent = mensagem;
        feedback.setAttribute('role', 'alert');
        feedback.setAttribute('aria-live', 'polite');
        
        // Estilos inline para garantir funcionamento
        Object.assign(feedback.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: tipo === 'success' ? '#10B981' : '#F1780C',
            color: 'white',
            padding: '12px 20px',
            borderRadius: '8px',
            fontSize: '14px',
            fontWeight: '500',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            zIndex: '10000',
            animation: 'deslizarEntradaDireita 0.3s ease-out',
            maxWidth: '300px',
            wordWrap: 'break-word'
        });
        
        document.body.appendChild(feedback);
        
        setTimeout(() => {
            feedback.style.animation = 'deslizarSaidaDireita 0.3s ease-in forwards';
            setTimeout(() => feedback.remove(), 300);
        }, duracao);
    }

    mostrarFeedbackAtalho() {
        this.mostrarFeedback('Atalho Ctrl+B utilizado!', 'info', 1500);
    }

    isMobile() {
        return window.innerWidth <= 768;
    }

    // Métodos públicos para controle externo
    colapsar() {
        if (!this.isMobile() && !this.isColapsada) {
            this.alternarColapsarDesktop();
        }
    }

    expandir() {
        if (!this.isMobile() && this.isColapsada) {
            this.alternarColapsarDesktop();
        }
    }

    abrirSidebar() {
        if (this.isMobile() && !this.isMobileAberta) {
            this.abrirMobile();
        }
    }

    fecharSidebar() {
        if (this.isMobile() && this.isMobileAberta) {
            this.fecharMobile();
        }
    }

    definirMenuAtivo(menuId) {
        // Remover ativo de todos
        const links = this.sidebar.querySelectorAll('.link-menu-sidebar');
        links.forEach(link => link.classList.remove('menu-ativo'));
        
        // Adicionar ativo ao específico
        const linkAtivo = this.sidebar.querySelector(`[data-menu="${menuId}"]`);
        if (linkAtivo) {
            linkAtivo.classList.add('menu-ativo');
            linkAtivo.setAttribute('aria-current', 'page');
        }
    }

    atualizarContador(menuId, quantidade) {
        const link = this.sidebar.querySelector(`[data-menu="${menuId}"]`);
        if (!link) return;
        
        let contador = link.querySelector('.contador-menu-sidebar');
        
        if (quantidade > 0) {
            if (!contador) {
                contador = document.createElement('span');
                contador.className = 'contador-menu-sidebar';
                link.appendChild(contador);
            }
            
            contador.textContent = quantidade > 99 ? '99+' : quantidade;
            contador.setAttribute('aria-label', `${quantidade} itens pendentes`);
        } else if (contador) {
            contador.remove();
        }
    }
}

// Adicionar animações CSS dinamicamente
const estilosAnimacoes = `
@keyframes deslizarEntradaDireita {
    from { opacity: 0; transform: translateX(100px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes deslizarSaidaDireita {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(100px); }
}
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = estilosAnimacoes;
document.head.appendChild(styleSheet);

// Inicializar quando DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.sidebarLojistaResponsiva = new SidebarLojistaResponsiva();
});

// Exportar para uso global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SidebarLojistaResponsiva;
}