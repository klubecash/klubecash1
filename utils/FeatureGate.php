<?php
/**
 * Feature Gate - Controla acesso a recursos baseado no plano da assinatura
 *
 * Uso:
 * if (FeatureGate::allows($lojaId, 'api_access')) { ... }
 * $limit = FeatureGate::getLimit($lojaId, 'employees_limit');
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class FeatureGate {
    private static $cache = [];

    /**
     * Verifica se a loja tem acesso a uma feature
     *
     * @param int $lojaId ID da loja
     * @param string $featureKey Chave da feature (ex: 'api_access', 'white_label')
     * @param mixed $expectedValue Valor esperado (padrão: não vazio/falso)
     * @return bool True se tem acesso
     */
    public static function allows($lojaId, $featureKey, $expectedValue = null) {
        $features = self::getFeatures($lojaId);

        if (!isset($features[$featureKey])) {
            return false; // Feature não existe no plano
        }

        $featureValue = $features[$featureKey];

        // Se não especificou valor esperado, checar se não está vazio/falso
        if ($expectedValue === null) {
            return !empty($featureValue) && $featureValue !== false && $featureValue !== 'none';
        }

        // Comparar com valor esperado
        return $featureValue === $expectedValue;
    }

    /**
     * Verifica se a loja tem acesso a uma feature (alias de allows)
     */
    public static function can($lojaId, $featureKey) {
        return self::allows($lojaId, $featureKey);
    }

    /**
     * Verifica se a loja NÃO tem acesso a uma feature
     */
    public static function denies($lojaId, $featureKey) {
        return !self::allows($lojaId, $featureKey);
    }

    /**
     * Retorna o valor/limite de uma feature
     *
     * @param int $lojaId ID da loja
     * @param string $featureKey Chave da feature
     * @param mixed $default Valor padrão se não encontrar
     * @return mixed Valor da feature
     */
    public static function getLimit($lojaId, $featureKey, $default = null) {
        $features = self::getFeatures($lojaId);
        return $features[$featureKey] ?? $default;
    }

    /**
     * Verifica se a loja está dentro do limite de uma feature
     *
     * @param int $lojaId ID da loja
     * @param string $featureKey Chave da feature
     * @param int $currentUsage Uso atual
     * @return bool True se está dentro do limite
     */
    public static function withinLimit($lojaId, $featureKey, $currentUsage) {
        $limit = self::getLimit($lojaId, $featureKey);

        // Se o limite for "unlimited", sempre retorna true
        if ($limit === 'unlimited' || $limit === null) {
            return true;
        }

        // Se o limite for numérico, comparar
        if (is_numeric($limit)) {
            return $currentUsage < intval($limit);
        }

        return false;
    }

    /**
     * Retorna todas as features do plano da loja
     *
     * @param int $lojaId ID da loja
     * @return array Features decodificadas do JSON
     */
    public static function getFeatures($lojaId) {
        // Checar cache
        if (isset(self::$cache[$lojaId])) {
            return self::$cache[$lojaId];
        }

        try {
            $db = (new Database())->getConnection();

            // Buscar assinatura ativa com features do plano
            $sql = "SELECT p.features_json
                    FROM assinaturas a
                    JOIN planos p ON a.plano_id = p.id
                    WHERE a.loja_id = ?
                    AND a.tipo = 'loja'
                    AND a.status IN ('trial', 'ativa')
                    ORDER BY a.created_at DESC
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->execute([$lojaId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || empty($result['features_json'])) {
                // Sem assinatura ativa = plano básico/restrito
                self::$cache[$lojaId] = self::getDefaultFeatures();
                return self::$cache[$lojaId];
            }

            $features = json_decode($result['features_json'], true);

            if (!is_array($features)) {
                self::$cache[$lojaId] = self::getDefaultFeatures();
                return self::$cache[$lojaId];
            }

            self::$cache[$lojaId] = $features;
            return $features;

        } catch (Exception $e) {
            error_log("Erro FeatureGate: " . $e->getMessage());
            return self::getDefaultFeatures();
        }
    }

    /**
     * Retorna features padrão (mais restritivas) para lojas sem assinatura
     */
    private static function getDefaultFeatures() {
        return [
            'customers_limit' => 50,
            'transactions_limit' => 100,
            'employees_limit' => 1,
            'analytics_level' => 'basic',
            'api_access' => 'none',
            'white_label' => false,
            'support_level' => 'community'
        ];
    }

    /**
     * Retorna informações sobre o plano atual da loja
     *
     * @param int $lojaId ID da loja
     * @return array|null Informações do plano e assinatura
     */
    public static function getPlanInfo($lojaId) {
        try {
            $db = (new Database())->getConnection();

            $sql = "SELECT
                        a.id as assinatura_id,
                        a.status,
                        a.trial_end,
                        a.current_period_end,
                        a.next_invoice_date,
                        p.id as plano_id,
                        p.nome as plano_nome,
                        p.slug as plano_slug,
                        p.preco_mensal,
                        p.features_json
                    FROM assinaturas a
                    JOIN planos p ON a.plano_id = p.id
                    WHERE a.loja_id = ?
                    AND a.tipo = 'loja'
                    AND a.status NOT IN ('cancelada')
                    ORDER BY a.created_at DESC
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->execute([$lojaId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $result['features'] = json_decode($result['features_json'], true);
            }

            return $result;

        } catch (Exception $e) {
            error_log("Erro ao buscar info do plano: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica se a assinatura está ativa (não expirada, não inadimplente)
     */
    public static function isActive($lojaId) {
        $planInfo = self::getPlanInfo($lojaId);

        if (!$planInfo) {
            return false;
        }

        $activeStatuses = ['trial', 'ativa'];
        return in_array($planInfo['status'], $activeStatuses);
    }

    /**
     * Verifica se está em período de trial
     */
    public static function isInTrial($lojaId) {
        $planInfo = self::getPlanInfo($lojaId);

        if (!$planInfo) {
            return false;
        }

        return $planInfo['status'] === 'trial'
            && !empty($planInfo['trial_end'])
            && strtotime($planInfo['trial_end']) >= time();
    }

    /**
     * Retorna mensagem de bloqueio personalizada
     */
    public static function getBlockMessage($featureKey) {
        $messages = [
            'api_access' => 'Acesso à API não disponível no seu plano. Faça upgrade para ter acesso.',
            'white_label' => 'White label disponível apenas no plano Enterprise.',
            'employees_limit' => 'Você atingiu o limite de funcionários do seu plano.',
            'analytics_level' => 'Relatórios avançados disponíveis apenas em planos superiores.',
            'support_level' => 'Suporte prioritário disponível em planos superiores.'
        ];

        return $messages[$featureKey] ?? 'Recurso não disponível no seu plano atual.';
    }

    /**
     * Limpa cache de features (útil após atualizar plano)
     */
    public static function clearCache($lojaId = null) {
        if ($lojaId) {
            unset(self::$cache[$lojaId]);
        } else {
            self::$cache = [];
        }
    }
}
