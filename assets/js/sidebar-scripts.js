/**
 * Scripts para o controle da sidebar responsiva
 * Este script deve ser incluído em todas as páginas que utilizam o componente sidebar
 */

// Elementos da DOM
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const mainContent = document.getElementById('mainContent');

// Evento para mostrar/ocultar a sidebar em dispositivos móveis
sidebarToggle.addEventListener('click', toggleSidebar);
overlay.addEventListener('click', toggleSidebar);

/**
 * Alterna a visibilidade da sidebar em dispositivos móveis
 */
function toggleSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

/**
 * Verifica o tamanho da tela e ajusta a sidebar conforme necessário
 */
function checkScreenSize() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }
}

// Verificar o tamanho da tela ao carregar e redimensionar
window.addEventListener('resize', checkScreenSize);

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    checkScreenSize();
});