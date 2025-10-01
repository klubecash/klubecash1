import React, { useState, useEffect, useCallback } from 'react';
import './Sidebar.css';

const Sidebar = ({ activeMenu = 'dashboard', userName = 'Lojista' }) => {
  const [isCollapsed, setIsCollapsed] = useState(() => 
    localStorage.getItem('klubeSidebarCollapsed') === 'true'
  );
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  // Calcular iniciais do usuário
  const getInitials = (name) => {
    const nameParts = name.split(' ');
    if (nameParts.length >= 2) {
      return (nameParts[0][0] + nameParts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
  };

  // Menu items
  const menuItems = [
    {
      id: 'dashboard',
      title: 'Dashboard',
      url: '/store/dashboard',
      icon: (
        <>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z"/>
        </>
      )
    },
    {
      id: 'register-transaction',
      title: 'Nova Venda',
      url: '/store/register-transaction',
      icon: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
    },
    {
      id: 'funcionarios',
      title: 'Funcionários',
      url: '/store/employees',
      icon: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
    },
    {
      id: 'payment-history',
      title: 'Pagamentos',
      url: '/store/payment-history',
      badge: 3,
      icon: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
    },
    {
      id: 'saldos',
      title: 'Pendentes de Pagamento',
      url: '/store/pending-commissions',
      icon: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
    },
    {
      id: 'profile',
      title: 'Perfil',
      url: '/store/profile',
      icon: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
    }
  ];

  const isMobile = useCallback(() => window.innerWidth <= 768, []);

  const adjustMainContent = useCallback(() => {
    setTimeout(() => {
      const mainContent = document.querySelector('.main-content, .content, .page-content, main');
      if (mainContent) {
        if (isMobile()) {
          mainContent.style.marginLeft = '0';
          mainContent.style.paddingLeft = '0';
        } else {
          const sidebarWidth = isCollapsed ? '80px' : '280px';
          mainContent.style.marginLeft = sidebarWidth;
          mainContent.style.paddingLeft = '0';
          mainContent.style.transition = 'margin-left 0.3s ease';
        }
        mainContent.classList.add('klube-main-adjusted');
      }
    }, 50);
  }, [isCollapsed, isMobile]);

  const toggleDesktop = () => {
    if (isMobile()) return;
    const newCollapsed = !isCollapsed;
    setIsCollapsed(newCollapsed);
    localStorage.setItem('klubeSidebarCollapsed', newCollapsed);
  };

  const toggleMobile = () => {
    if (!isMobile()) return;
    setIsMobileOpen(!isMobileOpen);
  };

  const closeMobile = () => {
    if (!isMobile()) return;
    setIsMobileOpen(false);
  };

  const handleLogout = () => {
    if (confirm('Tem certeza que deseja sair?')) {
      // Handle logout logic here
      window.location.href = '/logout';
    }
  };

  // Effects
  useEffect(() => {
    adjustMainContent();
  }, [adjustMainContent]);

  useEffect(() => {
    const handleResize = () => {
      if (isMobile()) {
        setIsMobileOpen(false);
      }
      adjustMainContent();
    };

    const handleKeyDown = (e) => {
      if (e.ctrlKey && e.key === 'b' && !isMobile()) {
        e.preventDefault();
        toggleDesktop();
      }
      if (e.key === 'Escape' && isMobile() && isMobileOpen) {
        closeMobile();
      }
    };

    const handleClickOutside = (e) => {
      const sidebar = document.getElementById('klubeSidebar');
      const mobileToggle = document.getElementById('klubeMobileToggle');
      
      if (isMobile() && isMobileOpen && 
          sidebar && !sidebar.contains(e.target) && 
          mobileToggle && !mobileToggle.contains(e.target)) {
        closeMobile();
      }
    };

    window.addEventListener('resize', handleResize);
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('click', handleClickOutside);

    return () => {
      window.removeEventListener('resize', handleResize);
      document.removeEventListener('keydown', handleKeyDown);
      document.removeEventListener('click', handleClickOutside);
    };
  }, [isMobileOpen, toggleDesktop]);

  useEffect(() => {
    if (isMobileOpen) {
      document.body.classList.add('klube-mobile-menu-open');
    } else {
      document.body.classList.remove('klube-mobile-menu-open');
    }

    return () => {
      document.body.classList.remove('klube-mobile-menu-open');
    };
  }, [isMobileOpen]);

  return (
    <>
      {/* Mobile Toggle */}
      <button 
        className="klube-mobile-toggle" 
        id="klubeMobileToggle" 
        onClick={toggleMobile}
        aria-label="Abrir menu"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>

      {/* Overlay */}
      <div 
        className={`klube-overlay ${isMobileOpen ? 'active' : ''}`} 
        onClick={closeMobile}
      ></div>

      {/* Sidebar */}
      <aside 
        className={`klube-sidebar ${isCollapsed ? 'collapsed' : ''} ${isMobileOpen ? 'mobile-open' : ''}`} 
        id="klubeSidebar"
      >
        {/* Header */}
        <header className="klube-sidebar-header">
          <div className="klube-logo-container">
            <img src="../../assets/images/logo-icon.png" alt="Klube Cash" className="klube-logo" />
            <span className="klube-logo-text">Klube Cash</span>
          </div>
          <button 
            className="klube-collapse-btn" 
            onClick={toggleDesktop}
            aria-label="Recolher menu"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="15,18 9,12 15,6"></polyline>
            </svg>
          </button>
        </header>

        {/* User Profile */}
        <div className="klube-user-profile">
          <div className="klube-avatar">{getInitials(userName)}</div>
          <div className="klube-user-info">
            <div className="klube-user-name">{userName}</div>
            <div className="klube-user-role">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              Lojista
            </div>
          </div>
        </div>

        {/* Navigation */}
        <nav className="klube-nav" role="navigation">
          <div className="klube-nav-section">
            <h3 className="klube-section-title">Menu Principal</h3>
            <ul className="klube-menu">
              {menuItems.map((item) => (
                <li key={item.id} className="klube-menu-item">
                  <a
                    href={item.url}
                    className={`klube-menu-link ${activeMenu === item.id ? 'active' : ''}`}
                    data-page={item.id}
                    aria-current={activeMenu === item.id ? 'page' : 'false'}
                  >
                    <span className="klube-menu-icon">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        {item.icon}
                      </svg>
                    </span>
                    <span className="klube-menu-text">{item.title}</span>
                    {item.badge && item.badge > 0 && (
                      <span className="klube-badge">{item.badge}</span>
                    )}
                    <span className="klube-tooltip">{item.title}</span>
                  </a>
                </li>
              ))}
            </ul>
          </div>
        </nav>

        {/* Footer */}
        <footer className="klube-sidebar-footer">
          <button className="klube-logout-btn" onClick={handleLogout}>
            <svg className="klube-logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4m7 14l5-5-5-5m5 5H9"/>
            </svg>
            <span className="klube-logout-text">Sair</span>
          </button>
        </footer>
      </aside>
    </>
  );
};

export default Sidebar;