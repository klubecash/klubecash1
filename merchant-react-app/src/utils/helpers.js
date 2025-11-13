/**
 * Debounce function
 */
export const debounce = (func, wait) => {
  let timeout;

  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };

    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
};

/**
 * Throttle function
 */
export const throttle = (func, limit) => {
  let inThrottle;

  return function(...args) {
    if (!inThrottle) {
      func.apply(this, args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  };
};

/**
 * Deep clone object
 */
export const deepClone = (obj) => {
  return JSON.parse(JSON.stringify(obj));
};

/**
 * Generate random ID
 */
export const generateId = () => {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
};

/**
 * Get initials from name
 */
export const getInitials = (name) => {
  if (!name) return '';

  const parts = name.trim().split(' ');

  if (parts.length === 1) {
    return parts[0].charAt(0).toUpperCase();
  }

  return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
};

/**
 * Download file
 */
export const downloadFile = (data, filename, mimeType) => {
  const blob = new Blob([data], { type: mimeType });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
};

/**
 * Copy to clipboard
 */
export const copyToClipboard = async (text) => {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (error) {
    console.error('Failed to copy:', error);
    return false;
  }
};

/**
 * Get query params from URL
 */
export const getQueryParams = (url = window.location.search) => {
  const params = new URLSearchParams(url);
  const result = {};

  for (const [key, value] of params) {
    result[key] = value;
  }

  return result;
};

/**
 * Build query string from object
 */
export const buildQueryString = (params) => {
  const searchParams = new URLSearchParams();

  Object.keys(params).forEach(key => {
    if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
      searchParams.append(key, params[key]);
    }
  });

  return searchParams.toString();
};

/**
 * Sleep/delay function
 */
export const sleep = (ms) => {
  return new Promise(resolve => setTimeout(resolve, ms));
};

/**
 * Format file name
 */
export const formatFileName = (fileName) => {
  if (!fileName) return '';

  const extension = fileName.split('.').pop();
  const nameWithoutExt = fileName.slice(0, -(extension.length + 1));
  const timestamp = Date.now();

  return `${nameWithoutExt}-${timestamp}.${extension}`;
};

/**
 * Get status color
 */
export const getStatusColor = (status) => {
  const colorMap = {
    // Transaction status
    'pendente': 'yellow',
    'aprovado': 'green',
    'cancelado': 'red',
    'pagamento_pendente': 'orange',

    // Payment status
    'rejeitado': 'red',
    'pix_aguardando': 'blue',
    'pix_expirado': 'gray',

    // Subscription status
    'trial': 'blue',
    'ativa': 'green',
    'inadimplente': 'red',
    'cancelada': 'gray',
    'suspensa': 'orange',

    // Store status
    'aprovado': 'green',
  };

  return colorMap[status] || 'gray';
};

/**
 * Calculate percentage
 */
export const calculatePercentage = (value, total) => {
  if (!total || total === 0) return 0;
  return (value / total) * 100;
};

/**
 * Group array by key
 */
export const groupBy = (array, key) => {
  return array.reduce((result, item) => {
    const group = item[key];

    if (!result[group]) {
      result[group] = [];
    }

    result[group].push(item);
    return result;
  }, {});
};

/**
 * Sort array by key
 */
export const sortBy = (array, key, order = 'asc') => {
  return [...array].sort((a, b) => {
    const aValue = a[key];
    const bValue = b[key];

    if (aValue < bValue) return order === 'asc' ? -1 : 1;
    if (aValue > bValue) return order === 'asc' ? 1 : -1;
    return 0;
  });
};

/**
 * Check if object is empty
 */
export const isEmpty = (obj) => {
  if (!obj) return true;
  return Object.keys(obj).length === 0;
};

/**
 * Remove duplicates from array
 */
export const removeDuplicates = (array, key) => {
  if (!key) {
    return [...new Set(array)];
  }

  const seen = new Set();
  return array.filter(item => {
    const value = item[key];
    if (seen.has(value)) return false;
    seen.add(value);
    return true;
  });
};

/**
 * Calculate cashback
 */
export const calculateCashback = (amount, percentage, clientPercent, adminPercent) => {
  const totalCashback = (amount * percentage) / 100;
  const clientValue = (totalCashback * clientPercent) / 100;
  const adminValue = (totalCashback * adminPercent) / 100;
  const storeValue = totalCashback - clientValue - adminValue;

  return {
    totalCashback,
    clientValue,
    adminValue,
    storeValue,
  };
};
