/**
 * Valida CPF
 */
export const validateCPF = (cpf) => {
  if (!cpf) return false;

  const cleaned = cpf.replace(/\D/g, '');

  if (cleaned.length !== 11) return false;

  // Verifica se todos os dígitos são iguais
  if (/^(\d)\1+$/.test(cleaned)) return false;

  // Valida primeiro dígito verificador
  let sum = 0;
  for (let i = 0; i < 9; i++) {
    sum += parseInt(cleaned.charAt(i)) * (10 - i);
  }
  let digit = 11 - (sum % 11);
  if (digit >= 10) digit = 0;
  if (digit !== parseInt(cleaned.charAt(9))) return false;

  // Valida segundo dígito verificador
  sum = 0;
  for (let i = 0; i < 10; i++) {
    sum += parseInt(cleaned.charAt(i)) * (11 - i);
  }
  digit = 11 - (sum % 11);
  if (digit >= 10) digit = 0;
  if (digit !== parseInt(cleaned.charAt(10))) return false;

  return true;
};

/**
 * Valida CNPJ
 */
export const validateCNPJ = (cnpj) => {
  if (!cnpj) return false;

  const cleaned = cnpj.replace(/\D/g, '');

  if (cleaned.length !== 14) return false;

  // Verifica se todos os dígitos são iguais
  if (/^(\d)\1+$/.test(cleaned)) return false;

  // Valida primeiro dígito verificador
  let length = cleaned.length - 2;
  let numbers = cleaned.substring(0, length);
  const digits = cleaned.substring(length);
  let sum = 0;
  let pos = length - 7;

  for (let i = length; i >= 1; i--) {
    sum += parseInt(numbers.charAt(length - i)) * pos--;
    if (pos < 2) pos = 9;
  }

  let result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
  if (result !== parseInt(digits.charAt(0))) return false;

  // Valida segundo dígito verificador
  length = length + 1;
  numbers = cleaned.substring(0, length);
  sum = 0;
  pos = length - 7;

  for (let i = length; i >= 1; i--) {
    sum += parseInt(numbers.charAt(length - i)) * pos--;
    if (pos < 2) pos = 9;
  }

  result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
  if (result !== parseInt(digits.charAt(1))) return false;

  return true;
};

/**
 * Valida email
 */
export const validateEmail = (email) => {
  if (!email) return false;

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

/**
 * Valida telefone brasileiro
 */
export const validatePhone = (phone) => {
  if (!phone) return false;

  const cleaned = phone.replace(/\D/g, '');

  // Aceita telefone com 10 ou 11 dígitos
  return cleaned.length === 10 || cleaned.length === 11;
};

/**
 * Valida URL
 */
export const validateURL = (url) => {
  if (!url) return false;

  try {
    new URL(url);
    return true;
  } catch {
    return false;
  }
};

/**
 * Valida senha forte
 */
export const validateStrongPassword = (password) => {
  if (!password) return false;

  // Mínimo 8 caracteres, pelo menos uma letra maiúscula, uma minúscula, um número
  const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
  return strongPasswordRegex.test(password);
};

/**
 * Valida valor monetário
 */
export const validateCurrency = (value, min = 0, max = Infinity) => {
  if (value === null || value === undefined) return false;

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  return !isNaN(numValue) && numValue >= min && numValue <= max;
};

/**
 * Valida porcentagem
 */
export const validatePercentage = (value, min = 0, max = 100) => {
  if (value === null || value === undefined) return false;

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  return !isNaN(numValue) && numValue >= min && numValue <= max;
};

/**
 * Valida arquivo
 */
export const validateFile = (file, maxSize, allowedTypes) => {
  if (!file) return { valid: false, error: 'Nenhum arquivo selecionado' };

  // Valida tamanho
  if (file.size > maxSize) {
    return {
      valid: false,
      error: `Arquivo muito grande. Tamanho máximo: ${(maxSize / 1024 / 1024).toFixed(2)}MB`,
    };
  }

  // Valida tipo
  if (!allowedTypes.includes(file.type)) {
    return {
      valid: false,
      error: `Tipo de arquivo não permitido. Tipos aceitos: ${allowedTypes.join(', ')}`,
    };
  }

  return { valid: true };
};

/**
 * Valida campo obrigatório
 */
export const validateRequired = (value) => {
  if (value === null || value === undefined) return false;

  if (typeof value === 'string') {
    return value.trim().length > 0;
  }

  if (Array.isArray(value)) {
    return value.length > 0;
  }

  return true;
};

/**
 * Valida comprimento mínimo
 */
export const validateMinLength = (value, minLength) => {
  if (!value) return false;

  const strValue = value.toString();
  return strValue.length >= minLength;
};

/**
 * Valida comprimento máximo
 */
export const validateMaxLength = (value, maxLength) => {
  if (!value) return true; // Empty is valid

  const strValue = value.toString();
  return strValue.length <= maxLength;
};
