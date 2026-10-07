<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Feature flags for the new billing flow.
 *
 * Mutations are deliberately disabled unless explicitly enabled in the
 * environment. This lets the new read model be deployed before charging
 * anyone and provides an immediate rollback switch.
 */
final class BillingFeatureFlags
{
    public static function provider(): string
    {
        $provider = strtolower(trim((string) (getenv('BILLING_PROVIDER') ?: 'legacy')));
        return in_array($provider, ['mercadopago', 'legacy'], true) ? $provider : 'legacy';
    }

    public static function writesEnabled(): bool
    {
        return self::flag('BILLING_WRITES_ENABLED', false);
    }

    public static function commercialSalesGateEnabled(): bool
    {
        return self::flag('COMMERCIAL_SALES_GATE_ENABLED', false);
    }

    public static function mpEnabled(): bool
    {
        return self::provider() === 'mercadopago' && self::writesEnabled();
    }

    /**
     * Checkout Transparente is intentionally opt-in.  Keeping this separate
     * from the provider flag lets operators deploy the UI and API without
     * accepting card/Pix payments until the Mercado Pago credentials and
     * webhook are verified.
     */
    public static function checkoutMode(): string
    {
        $mode = strtolower(trim((string) (getenv('CHECKOUT_MODE') ?: 'legacy')));
        return in_array($mode, ['transparent', 'legacy'], true) ? $mode : 'legacy';
    }

    public static function transparentCheckoutEnabled(): bool
    {
        return self::checkoutMode() === 'transparent' && self::mpEnabled()
            && self::flag('MANUAL_BILLING_ENABLED', false);
    }

    public static function flag(string $name, bool $default): bool
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
