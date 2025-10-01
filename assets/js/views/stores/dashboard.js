// Dashboard JavaScript for Store
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar functionality
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    // Create overlay element if it doesn't exist
    if (!sidebarOverlay && window.innerWidth <= 991.98) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Toggle sidebar function
    function toggleSidebar() {
        if (sidebar) {
            sidebar.classList.toggle('open');
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.classList.toggle('active');
            }
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
    }
    
    // Close sidebar function
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('open');
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        }
    }
    
    // Sidebar toggle event
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 991.98 && sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                closeSidebar();
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98) {
            closeSidebar();
        }
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    
    // Get chart labels and data from PHP (passed via data attributes)
    const chartContainer = document.getElementById('salesChart');
    if (chartContainer) {
        const chartLabels = JSON.parse(chartContainer.dataset.labels || '[]');
        const chartData = JSON.parse(chartContainer.dataset.data || '[]');
        
        // Configuração do gráfico de vendas mensais
        const salesCtx = chartContainer.getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Valor Total (R$)',
                    data: chartData,
                    backgroundColor: 'rgba(255, 122, 0, 0.7)',
                    borderColor: 'rgba(255, 122, 0, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }

    // Value visibility toggle functionality
    testLocalStorage();
    loadValueVisibilityState();
});

// Function to toggle value visibility
function toggleValueVisibility(targetId) {
    console.log('toggleValueVisibility called with:', targetId);

    try {
        const hiddenValues = getHiddenValues();
        const valueElement = document.querySelector(`[data-id="${targetId}"]`);
        const button = document.querySelector(`[data-target="${targetId}"]`);

        console.log('Found elements - Value:', !!valueElement, 'Button:', !!button);

        if (!valueElement || !button) {
            console.error('Required elements not found for:', targetId);
            return false;
        }

        const eyeOpen = button.querySelector('.eye-open');
        const eyeClosed = button.querySelector('.eye-closed');

        if (!eyeOpen || !eyeClosed) {
            console.error('Eye icons not found in button for:', targetId);
            return false;
        }

        const isHidden = hiddenValues.includes(targetId);
        console.log('Is currently hidden:', isHidden);

        if (isHidden) {
            // Show value
            const originalValue = valueElement.getAttribute('data-original');
            if (originalValue) {
                valueElement.textContent = originalValue;
                eyeOpen.style.display = '';
                eyeClosed.style.display = 'none';
                removeFromHiddenValues(targetId);
                console.log('Value shown:', targetId);
            }
        } else {
            // Hide value
            valueElement.textContent = '••••••';
            eyeOpen.style.display = 'none';
            eyeClosed.style.display = '';
            addToHiddenValues(targetId);
            console.log('Value hidden:', targetId);
        }

        return true;
    } catch (error) {
        console.error('Error in toggleValueVisibility:', error);
        return false;
    }
}

// Function to toggle table column visibility
function toggleTableColumnVisibility(targetId) {
    console.log('toggleTableColumnVisibility called with:', targetId);

    try {
        const hiddenValues = getHiddenValues();
        const columns = document.querySelectorAll(`[data-column="${targetId}"]`);
        const button = document.querySelector(`[data-target="${targetId}"]`);

        console.log('Table toggle - Columns:', columns.length, 'Button:', !!button);

        if (!button || columns.length === 0) {
            console.error('Required elements not found for table:', targetId);
            return false;
        }

        const eyeOpen = button.querySelector('.eye-open');
        const eyeClosed = button.querySelector('.eye-closed');

        if (!eyeOpen || !eyeClosed) {
            console.error('Eye icons not found in table button for:', targetId);
            return false;
        }

        const isHidden = hiddenValues.includes(targetId);
        console.log('Table column is currently hidden:', isHidden);

        if (isHidden) {
            // Show values
            columns.forEach(column => {
                const valueElement = column.querySelector('.hideable-value');
                if (valueElement) {
                    const originalValue = valueElement.getAttribute('data-original');
                    if (originalValue) {
                        valueElement.textContent = originalValue;
                    }
                }
            });
            eyeOpen.style.display = '';
            eyeClosed.style.display = 'none';
            removeFromHiddenValues(targetId);
            console.log('Table column shown:', targetId);
        } else {
            // Hide values
            columns.forEach(column => {
                const valueElement = column.querySelector('.hideable-value');
                if (valueElement) {
                    valueElement.textContent = '••••••';
                }
            });
            eyeOpen.style.display = 'none';
            eyeClosed.style.display = '';
            addToHiddenValues(targetId);
            console.log('Table column hidden:', targetId);
        }

        return true;
    } catch (error) {
        console.error('Error in toggleTableColumnVisibility:', error);
        return false;
    }
}

// LocalStorage management functions
function getHiddenValues() {
    try {
        const stored = localStorage.getItem('klubecash_hidden_values');
        return stored ? JSON.parse(stored) : [];
    } catch (error) {
        console.error('Error reading from localStorage:', error);
        return [];
    }
}

function addToHiddenValues(valueId) {
    try {
        const hiddenValues = getHiddenValues();
        if (!hiddenValues.includes(valueId)) {
            hiddenValues.push(valueId);
            localStorage.setItem('klubecash_hidden_values', JSON.stringify(hiddenValues));
        }
    } catch (error) {
        console.error('Error adding to localStorage:', error);
    }
}

function removeFromHiddenValues(valueId) {
    try {
        const hiddenValues = getHiddenValues();
        const filteredValues = hiddenValues.filter(id => id !== valueId);
        localStorage.setItem('klubecash_hidden_values', JSON.stringify(filteredValues));
    } catch (error) {
        console.error('Error removing from localStorage:', error);
    }
}

// Test function to verify localStorage is working
function testLocalStorage() {
    try {
        localStorage.setItem('klubecash_test', 'test_value');
        const testValue = localStorage.getItem('klubecash_test');
        localStorage.removeItem('klubecash_test');
        console.log('localStorage test passed:', testValue === 'test_value');
        return testValue === 'test_value';
    } catch (error) {
        console.error('localStorage test failed:', error);
        return false;
    }
}

// Load initial visibility state from localStorage
function loadValueVisibilityState() {
    // Wait for DOM to be fully ready
    const loadState = () => {
        try {
            const hiddenValues = getHiddenValues();
            console.log('Loading hidden values:', hiddenValues);

            if (hiddenValues.length === 0) {
                console.log('No hidden values found');
                return;
            }

            hiddenValues.forEach(valueId => {
                console.log('Processing valueId:', valueId);

                if (valueId.startsWith('table-')) {
                    // Handle table columns
                    const columns = document.querySelectorAll(`[data-column="${valueId}"]`);
                    const button = document.querySelector(`[data-target="${valueId}"]`);

                    console.log(`Table ${valueId} - Columns: ${columns.length}, Button: ${!!button}`);

                    if (button && columns.length > 0) {
                        // Update button icons
                        const eyeOpen = button.querySelector('.eye-open');
                        const eyeClosed = button.querySelector('.eye-closed');

                        if (eyeOpen && eyeClosed) {
                            eyeOpen.style.display = 'none';
                            eyeClosed.style.display = '';
                        }

                        // Hide column values
                        columns.forEach(column => {
                            const valueElement = column.querySelector('.hideable-value');
                            if (valueElement) {
                                valueElement.textContent = '••••••';
                            }
                        });
                    }
                } else {
                    // Handle individual values
                    const valueElement = document.querySelector(`[data-id="${valueId}"]`);
                    const button = document.querySelector(`[data-target="${valueId}"]`);

                    console.log(`Individual ${valueId} - Value: ${!!valueElement}, Button: ${!!button}`);

                    if (valueElement && button) {
                        // Hide value
                        valueElement.textContent = '••••••';

                        // Update button icons
                        const eyeOpen = button.querySelector('.eye-open');
                        const eyeClosed = button.querySelector('.eye-closed');

                        if (eyeOpen && eyeClosed) {
                            eyeOpen.style.display = 'none';
                            eyeClosed.style.display = '';
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error in loadValueVisibilityState:', error);
        }
    };

    // Try multiple times to ensure DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadState);
    } else {
        // DOM is already ready, try immediately and then with a small delay
        loadState();
        setTimeout(loadState, 50);
        setTimeout(loadState, 200);
    }
}