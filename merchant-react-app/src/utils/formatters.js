import { format, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';

/**
 * Formata valor monetário para BRL
 */
export const formatCurrency = (value) => {
  if (value === null || value === undefined) return 'R$ 0,00';

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(numValue);
};

/**
 * Formata data para formato brasileiro
 */
export const formatDate = (date, formatString = 'dd/MM/yyyy') => {
  if (!date) return '-';

  try {
    const dateObj = typeof date === 'string' ? parseISO(date) : date;
    return format(dateObj, formatString, { locale: ptBR });
  } catch (error) {
    return '-';
  }
};

/**
 * Formata data e hora
 */
export const formatDateTime = (date) => {
  return formatDate(date, 'dd/MM/yyyy HH:mm');
};

/**
 * Formata telefone brasileiro
 */
export const formatPhone = (phone) => {
  if (!phone) return '';

  const cleaned = phone.replace(/\D/g, '');

  if (cleaned.length === 11) {
    return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 7)}-${cleaned.slice(7)}`;
  }

  if (cleaned.length === 10) {
    return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 6)}-${cleaned.slice(6)}`;
  }

  return phone;
};

/**
 * Formata CPF
 */
export const formatCPF = (cpf) => {
  if (!cpf) return '';

  const cleaned = cpf.replace(/\D/g, '');

  if (cleaned.length === 11) {
    return `${cleaned.slice(0, 3)}.${cleaned.slice(3, 6)}.${cleaned.slice(6, 9)}-${cleaned.slice(9)}`;
  }

  return cpf;
};

/**
 * Formata CNPJ
 */
export const formatCNPJ = (cnpj) => {
  if (!cnpj) return '';

  const cleaned = cnpj.replace(/\D/g, '');

  if (cleaned.length === 14) {
    return `${cleaned.slice(0, 2)}.${cleaned.slice(2, 5)}.${cleaned.slice(5, 8)}/${cleaned.slice(8, 12)}-${cleaned.slice(12)}`;
  }

  return cnpj;
};

/**
 * Formata porcentagem
 */
export const formatPercentage = (value, decimals = 2) => {
  if (value === null || value === undefined) return '0%';

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  return `${numValue.toFixed(decimals)}%`;
};

/**
 * Formata número
 */
export const formatNumber = (value, decimals = 0) => {
  if (value === null || value === undefined) return '0';

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(numValue);
};

/**
 * Trunca texto
 */
export const truncateText = (text, maxLength = 50) => {
  if (!text) return '';

  if (text.length <= maxLength) return text;

  return `${text.slice(0, maxLength)}...`;
};

/**
 * Remove formatação de valor monetário
 */
export const parseCurrency = (value) => {
  if (!value) return 0;

  const cleaned = value.toString().replace(/[^\d,]/g, '');
  const normalized = cleaned.replace(',', '.');

  return parseFloat(normalized) || 0;
};

/**
 * Formata tamanho de arquivo
 */
export const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';

  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
};

/**
 * Capitaliza primeira letra
 */
export const capitalize = (text) => {
  if (!text) return '';
  return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
};

/**
 * Formata nome completo (primeira letra de cada palavra maiúscula)
 */
export const formatName = (name) => {
  if (!name) return '';

  return name
    .toLowerCase()
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};
