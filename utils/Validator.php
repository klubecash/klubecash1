<?php
/**
 * Classe de validação para o sistema Klube Cash
 */
class Validator {
    /**
     * Valida um endereço de email
     * 
     * @param string $email O email a ser validado
     * @return bool Retorna true se o email for válido
     */
    public static function validaEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida um número de telefone (apenas dígitos)
     * 
     * @param string $telefone O telefone a ser validado
     * @return bool Retorna true se o telefone for válido
     */
    public static function validaTelefone($telefone) {
        // Remove caracteres não numéricos
        $telefone = preg_replace('/\D/', '', $telefone);
        // Verifica se tem pelo menos 10 dígitos (DDD + número)
        return strlen($telefone) >= 10 && strlen($telefone) <= 11;
    }
    
    /**
     * Valida uma senha
     * 
     * @param string $senha A senha a ser validada
     * @param int $minLength Comprimento mínimo da senha
     * @return bool Retorna true se a senha for válida
     */
    public static function validaSenha($senha, $minLength = 8) {
        return strlen($senha) >= $minLength;
    }
    
    /**
     * Valida um nome
     * 
     * @param string $nome O nome a ser validado
     * @param int $minLength Comprimento mínimo do nome
     * @return bool Retorna true se o nome for válido
     */
    public static function validaNome($nome, $minLength = 3) {
        // Remove espaços extras no início e fim
        $nome = trim($nome);
        return strlen($nome) >= $minLength;
    }
    
    /**
     * Valida um CPF
     * 
     * @param string $cpf O CPF a ser validado
     * @return bool Retorna true se o CPF for válido
     */
    public static function validaCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/\D/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais (CPF inválido)
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Validação do primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $digito1 = ($resto < 2) ? 0 : 11 - $resto;
        
        if ($cpf[9] != $digito1) {
            return false;
        }
        
        // Validação do segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $cpf[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $digito2 = ($resto < 2) ? 0 : 11 - $resto;
        
        return $cpf[10] == $digito2;
    }
    
    /**
     * Sanitiza uma string para evitar injeção de código
     * 
     * @param string $string A string a ser sanitizada
     * @return string A string sanitizada
     */
    public static function sanitizaString($string) {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitiza um email
     * 
     * @param string $email O email a ser sanitizado
     * @return string O email sanitizado
     */
    public static function sanitizaEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Formata um CPF para exibição
     * 
     * @param string $cpf O CPF a ser formatado
     * @return string O CPF formatado (000.000.000-00)
     */
    public static function formataCPF($cpf) {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) == 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
        return $cpf;
    }
}