/**
 * Klube Cash - JavaScript Moderno v2.0
 * Sistema completo de interações e animações
 */

// === CONFIGURAÇÃO INICIAL ===
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// === INICIALIZAÇÃO PRINCIPAL ===
function initializeApp() {
    // Inicializar componentes essenciais
    initLoadingScreen();
    initHeader();
    initMobileMenu();
    initUserMenu();
    initSmoothScroll();
    initFAQ();
    initAnimations();
    initCounters();
    initParallax();
    
    // Log para desenvolvimento
    console.log('🚀 Klube Cash v2.0 inicializado com sucesso!');
}

// === LOADING SCREEN ===
function initLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    
    if (!loadingScreen) return;
    
    // Simular tempo de carregamento mínimo para melhor UX
    const minLoadTime = 1500;
    const startTime = performance.now();
    
    window.addEventListener('load', () => {
        const elapsedTime = performance.now() - startTime;
        const remainingTime = Math.max(0, minLoadTime - elapsedTime);
        
        setTimeout(() => {
            loadingScreen.classList.add('fade-out');
            
            // Remover completamente após a animação
            setTimeout(() => {
                loadingScreen.remove();
                // Trigger evento personalizado para outros scripts
                document.dispatchEvent(new CustomEvent('appLoaded'));
            }, 500);
        }, remainingTime);
    });
}

// === HEADER INTELIGENTE ===
function initHeader() {
    const header = document.getElementById('mainHeader');
    if (!header) return;
    
    let lastScrollY = window.scrollY;
    let scrollTimer = null;
    
    function updateHeader() {
        const currentScrollY = window.scrollY;
        const isScrollingDown = currentScrollY > lastScrollY;
        const isNearTop = currentScrollY < 100;
        
        // Adicionar classe 'scrolled' quando não estiver no topo
        header.classList.toggle('scrolled', !isNearTop);
        
        // Auto-hide header em scroll para baixo (mobile)
        if (window.innerWidth <= 768) {
            header.style.transform = isScrollingDown && !isNearTop ? 
                'translateY(-100%)' : 'translateY(0)';
        }
        
        lastScrollY = currentScrollY;
    }
    
    // Throttled scroll listener para performance
    window.addEventListener('scroll', () => {
        if (scrollTimer) return;
        
        scrollTimer = setTimeout(() => {
            updateHeader();
            scrollTimer = null;
        }, 10);
    }, { passive: true });
    
    // Update inicial
    updateHeader();
}

// === MENU MOBILE ===
function initMobileMenu() {
    const menuToggle = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileLinks = document.querySelectorAll('.mobile-nav-link');
    
    if (!menuToggle || !mobileMenu) return;
    
    let isMenuOpen = false;
    
    // Toggle menu
    menuToggle.addEventListener('click', () => {
        toggleMobileMenu();
    });
    
    // Fechar menu ao clicar em links
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (isMenuOpen) {
                toggleMobileMenu();
            }
        });
    });
    
    // Fechar menu com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isMenuOpen) {
            toggleMobileMenu();
        }
    });
    
    // Fechar menu ao clicar fora
    document.addEventListener('click', (e) => {
        if (isMenuOpen && !mobileMenu.contains(e.target) && !menuToggle.contains(e.target)) {
            toggleMobileMenu();
        }
    });
    
    function toggleMobileMenu() {
        isMenuOpen = !isMenuOpen;
        
        menuToggle.classList.toggle('active', isMenuOpen);
        mobileMenu.classList.toggle('show', isMenuOpen);
        menuToggle.setAttribute('aria-expanded', isMenuOpen);
        
        // Prevenir scroll do body quando menu estiver aberto
        document.body.style.overflow = isMenuOpen ? 'hidden' : '';
        
        // Acessibilidade: foco no primeiro link quando abrir
        if (isMenuOpen) {
            const firstLink = mobileMenu.querySelector('.mobile-nav-link');
            if (firstLink) {
                setTimeout(() => firstLink.focus(), 100);
            }
        }
    }
}

// === MENU DO USUÁRIO ===
function initUserMenu() {
    const userButton = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (!userButton || !userDropdown) return;
    
    let isDropdownOpen = false;
    
    userButton.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleUserDropdown();
    });
    
    // Fechar dropdown ao clicar fora
    document.addEventListener('click', () => {
        if (isDropdownOpen) {
            toggleUserDropdown();
        }
    });
    
    // Navegação por teclado
    userDropdown.addEventListener('keydown', (e) => {
        const items = userDropdown.querySelectorAll('.dropdown-item');
        const currentIndex = Array.from(items).indexOf(document.activeElement);
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                items[nextIndex]?.focus();
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                items[prevIndex]?.focus();
                break;
                
            case 'Escape':
                toggleUserDropdown();
                userButton.focus();
                break;
        }
    });
    
    function toggleUserDropdown() {
        isDropdownOpen = !isDropdownOpen;
        
        userDropdown.classList.toggle('show', isDropdownOpen);
        userButton.setAttribute('aria-expanded', isDropdownOpen);
        
        if (isDropdownOpen) {
            const firstItem = userDropdown.querySelector('.dropdown-item');
            setTimeout(() => firstItem?.focus(), 100);
        }
    }
}

// === SMOOTH SCROLL ===
function initSmoothScroll() {
    const smoothScrollLinks = document.querySelectorAll('.smooth-scroll');
    
    smoothScrollLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            
            const targetId = link.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const headerHeight = document.getElementById('mainHeader')?.offsetHeight || 0;
                const targetPosition = targetElement.offsetTop - headerHeight - 20;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Analytics tracking (se disponível)
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'scroll_to_section', {
                        section_id: targetId.replace('#', '')
                    });
                }
            }
        });
    });
}

// === FAQ INTERATIVO ===
function initFAQ() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        
        if (!question || !answer) return;
        
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            // Fechar todos os outros itens (comportamento accordion)
            faqItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                    const otherQuestion = otherItem.querySelector('.faq-question');
                    const otherAnswer = otherItem.querySelector('.faq-answer');
                    if (otherQuestion && otherAnswer) {
                        otherQuestion.setAttribute('aria-expanded', 'false');
                        otherAnswer.style.maxHeight = '0';
                    }
                }
            });
            
            // Toggle item atual
            if (!isActive) {
                item.classList.add('active');
                question.setAttribute('aria-expanded', 'true');
                answer.style.maxHeight = answer.scrollHeight + 'px';
                
                // Scroll suave para a pergunta se necessário
                setTimeout(() => {
                    const rect = question.getBoundingClientRect();
                    const headerHeight = document.getElementById('mainHeader')?.offsetHeight || 0;
                    
                    if (rect.top < headerHeight) {
                        question.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }
                }, 300);
            } else {
                item.classList.remove('active');
                question.setAttribute('aria-expanded', 'false');
                answer.style.maxHeight = '0';
            }
        });
        
        // Configuração inicial de acessibilidade
        question.setAttribute('aria-expanded', 'false');
        answer.style.maxHeight = '0';
    });
}

// === ANIMAÇÕES AOS ===
function initAnimations() {
    // Verificar se AOS está disponível
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 100,
            delay: 0,
            disable: function() {
                // Desabilitar em dispositivos com pouca performance
                return window.innerWidth < 768 && 
                       (window.DeviceMotionEvent === undefined || 
                        navigator.hardwareConcurrency < 4);
            }
        });
        
        // Refresh AOS em mudanças de layout
        window.addEventListener('resize', debounce(() => {
            AOS.refresh();
        }, 250));
    }
    
    // Animações customizadas
    initCustomAnimations();
}

// === ANIMAÇÕES CUSTOMIZADAS ===
function initCustomAnimations() {
    // Parallax suave para elementos hero
    const heroShapes = document.querySelectorAll('.hero-shapes .shape');
    const floatingIcons = document.querySelectorAll('.floating-icons .icon-item');
    
    let ticking = false;
    
    function updateParallax() {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        heroShapes.forEach((shape, index) => {
            const speed = (index + 1) * 0.1;
            shape.style.transform = `translateY(${rate * speed}px)`;
        });
        
        floatingIcons.forEach((icon, index) => {
            const speed = (index + 1) * 0.05;
            icon.style.transform = `translateY(${rate * speed}px) rotate(${scrolled * 0.1}deg)`;
        });
        
        ticking = false;
    }
    
    window.addEventListener('scroll', () => {
        if (!ticking && window.innerWidth > 768) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }, { passive: true });
}

// === CONTADORES ANIMADOS ===
function initCounters() {
    const counters = document.querySelectorAll('[data-count]');
    const observerOptions = {
        threshold: 0.7,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
    
    function animateCounter(element) {
        const target = parseInt(element.dataset.count);
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            
            // Formatação especial para valores monetários
            if (element.textContent.includes('R$')) {
                element.textContent = `R$ ${Math.floor(current).toLocaleString('pt-BR')}`;
            } else {
                element.textContent = Math.floor(current).toLocaleString('pt-BR');
            }
        }, 16);
    }
}

// === PARALLAX AVANÇADO ===
function initParallax() {
    // Verificar se o dispositivo suporta parallax
    if (window.DeviceOrientationEvent || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        return; // Desabilitar em dispositivos móveis
    }
    
    const parallaxElements = document.querySelectorAll('[data-parallax]');
    
    if (parallaxElements.length === 0) return;
    
    let ticking = false;
    
    function updateParallax() {
        const scrollTop = window.pageYOffset;
        
        parallaxElements.forEach(element => {
            const speed = parseFloat(element.dataset.parallax) || 0.5;
            const yPos = -(scrollTop * speed);
            element.style.transform = `translate3d(0, ${yPos}px, 0)`;
        });
        
        ticking = false;
    }
    
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }, { passive: true });
}

// === UTILITÁRIOS ===

// Debounce function para performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function para scroll events
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Detecção de suporte para webp
function supportsWebP() {
    return new Promise(resolve => {
        const webP = new Image();
        webP.onload = webP.onerror = () => {
            resolve(webP.height === 2);
        };
        webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    });
}

// Analytics helpers (Google Analytics 4)
function trackEvent(eventName, parameters = {}) {
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, parameters);
    }
}

function trackPageView(page_title, page_location) {
    if (typeof gtag !== 'undefined') {
        gtag('config', 'GA_MEASUREMENT_ID', {
            page_title,
            page_location
        });
    }
}

// Performance monitoring
function measurePerformance() {
    if ('performance' in window) {
        window.addEventListener('load', () => {
            setTimeout(() => {
                const perfData = performance.getEntriesByType('navigation')[0];
                
                // Log métricas importantes
                console.group('📊 Performance Metrics');
                console.log(`DOM Content Loaded: ${perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart}ms`);
                console.log(`Load Complete: ${perfData.loadEventEnd - perfData.loadEventStart}ms`);
                console.log(`Total Load Time: ${perfData.loadEventEnd - perfData.navigationStart}ms`);
                console.groupEnd();
                
                // Enviar para analytics se configurado
                trackEvent('page_performance', {
                    load_time: perfData.loadEventEnd - perfData.navigationStart,
                    dom_content_loaded: perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart
                });
            }, 0);
        });
    }
}

// Inicializar monitoramento de performance
measurePerformance();

// === TEMA ESCURO (BONUS) ===
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    themeToggle.addEventListener('click', () => {
        const newTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        trackEvent('theme_change', { theme: newTheme });
    });
}

// === INTERSECTION OBSERVER HELPERS ===
function createObserver(callback, options = {}) {
    const defaultOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };
    
    return new IntersectionObserver(callback, { ...defaultOptions, ...options });
}

// === LAZY LOADING DE IMAGENS ===
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if (images.length === 0) return;
    
    const imageObserver = createObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// === NOTIFICAÇÕES TOAST ===
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    toastContainer.appendChild(toast);
    
    // Animação de entrada
    requestAnimationFrame(() => {
        toast.classList.add('toast-show');
    });
    
    // Auto remove
    setTimeout(() => {
        toast.classList.remove('toast-show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// === EXPORT PARA USO GLOBAL ===
window.KlubeCash = {
    showToast,
    trackEvent,
    debounce,
    throttle,
    createObserver
};

// === SERVICE WORKER (PWA) ===
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('✅ Service Worker registrado:', registration);
            })
            .catch(error => {
                console.log('❌ Falha ao registrar Service Worker:', error);
            });
    });
}

// Log final
console.log('🎉 Klube Cash v2.0 - Todos os sistemas operacionais!');