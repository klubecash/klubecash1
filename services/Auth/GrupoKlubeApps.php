<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Menu "Apps" (trocar de aplicativo e voltar ao Portal) do Grupo Klube.
 *
 * A lista vem do login único e é guardada na sessão PHP no momento do login (callback), porque o token de acesso
 * dura poucos minutos. Só aparece para quem abre 2 ou mais aplicativos; sem lista (login por senha, sessão antiga,
 * login único fora do ar) o menu simplesmente não existe.
 */
final class GrupoKlubeApps
{
    public const SESSION_KEY = 'grupoklube_apps';
    public const SLUG_DO_PORTAL = 'klube-intranet';
    public const APP_ATUAL = 'klubecash';

    /** @param list<array{slug: string, name: string, launchUrl: ?string}> $apps */
    public static function store(array $apps): void
    {
        $_SESSION[self::SESSION_KEY] = $apps;
    }

    public static function forget(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function portalUrl(): string
    {
        $configured = getenv('PORTAL_URL');
        return is_string($configured) && $configured !== '' ? $configured : 'https://intranet.grupoklube.com';
    }

    /** http(s) only: a link from the central login is never trusted to be anything else. */
    private static function safeUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        return $value;
    }

    /**
     * @param mixed $apps the list as kept in the session (anything else is ignored)
     * @return array{portalUrl: string, itens: list<array{slug: string, nome: string, url: string, atual: bool}>}|null
     */
    public static function build(mixed $apps, string $portalUrl, string $atual = self::APP_ATUAL): ?array
    {
        $portal = self::safeUrl($portalUrl);
        if ($portal === null || !is_array($apps)) {
            return null;
        }
        $seen = [];
        $list = [];
        foreach ($apps as $app) {
            if (!is_array($app) || !is_string($app['slug'] ?? null) || !is_string($app['name'] ?? null)) {
                continue;
            }
            if ($app['slug'] === self::SLUG_DO_PORTAL || isset($seen[$app['slug']])) {
                continue;
            }
            $seen[$app['slug']] = true;
            $list[] = $app;
        }
        if (count($list) < 2) {
            return null;
        }
        $items = [];
        foreach ($list as $app) {
            $isCurrent = $app['slug'] === $atual;
            $url = $isCurrent ? '/' : self::safeUrl(is_string($app['launchUrl'] ?? null) ? $app['launchUrl'] : null);
            if ($url !== null) {
                $items[] = ['slug' => $app['slug'], 'nome' => $app['name'], 'url' => $url, 'atual' => $isCurrent];
            }
        }
        return count($items) >= 2 ? ['portalUrl' => $portal, 'itens' => $items] : null;
    }

    /** Menu for the logged-in person, from the session (null when there is nothing to show). */
    public static function menu(): ?array
    {
        return self::build($_SESSION[self::SESSION_KEY] ?? null, self::portalUrl());
    }
}
