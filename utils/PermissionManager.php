<?php
/**
 * PermissionManager Simplificado - Klube Cash v2.1
 * DESCONTINUADO: Mantido apenas para compatibilidade durante migração
 * USE: StoreHelper::requireStoreAccess() para novas implementações
 */

class PermissionManager {
    
    /**
     * DESCONTINUADO: Use StoreHelper::requireStoreAccess()
     * Mantido apenas para compatibilidade durante migração
     */
    public static function checkAccess($modulo, $acao) {
        // Sistema simplificado: todos funcionários têm acesso igual
        $userType = $_SESSION['user_type'] ?? '';
        return in_array($userType, [USER_TYPE_STORE, USER_TYPE_EMPLOYEE]);
    }
    
    /**
     * DESCONTINUADO: Use StoreHelper::requireStoreAccess()
     */
    public static function hasPermission($funcionarioId, $modulo, $acao) {
        return true; // Sistema simplificado: todos têm permissão
    }
    
    /**
     * DESCONTINUADO: Não há mais permissões específicas
     */
    public static function getUserPermissions($funcionarioId) {
        return ['permissions' => [], 'subtipo' => '', 'loja_id' => null];
    }
    
    /**
     * DESCONTINUADO: Não há mais permissões padrão
     */
    public static function applyDefaultPermissions($funcionarioId, $lojaId, $subtipo) {
        return true; // Sempre sucesso - não há permissões para aplicar
    }
}