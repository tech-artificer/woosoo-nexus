<?php

namespace App\Support;

final class PublicOrigin
{
    private const DEFAULT_SCHEME = 'https';

    private const DEFAULT_HTTP_PORT = 80;
    private const DEFAULT_HTTPS_PORT = 443;

    public static function scheme(): string
    {
        $scheme = trim((string) (env('PUBLIC_SCHEME') ?: parse_url((string) env('APP_URL'), PHP_URL_SCHEME)));

        return in_array($scheme, ['http', 'https'], true) ? $scheme : self::DEFAULT_SCHEME;
    }

    public static function host(): string
    {
        $host = trim((string) (env('PUBLIC_HOST') ?: parse_url((string) env('APP_URL'), PHP_URL_HOST)));

        if ($host === '') {
            throw new \RuntimeException(
                'PUBLIC_HOST or APP_URL must be set in .env. '
                . 'This value drives APP_URL, CORS, Sanctum, broadcasting, and mail config.'
            );
        }

        return $host;
    }

    public static function appUrl(): string
    {
        return self::origin(self::scheme(), self::scheme() === 'https' ? self::httpsPort() : self::httpPort());
    }

    /**
     * @return array<int, string>
     */
    public static function corsOrigins(): array
    {
        return array_values(array_unique(array_filter([
            self::origin('https', self::httpsPort()),
            self::origin('http', self::httpPort()),
        ])));
    }

    /**
     * @return array<int, string>
     */
    public static function websocketOrigins(): array
    {
        $host = self::host();
        $https = self::httpsPort();
        $http = self::httpPort();

        return array_values(array_unique(array_filter([
            // Canonical origins (without default ports)
            self::origin('https', $https),
            self::origin('http', $http),

            // Explicit port variants for strict origin checks in some clients/proxies
            sprintf('https://%s:%d', $host, $https),
            sprintf('http://%s:%d', $host, $http),
        ])));
    }

    /**
     * Reverb matches WebSocket origins by host name only.
     *
     * @return array<int, string>
     */
    public static function websocketAllowedOriginHosts(): array
    {
        return array_values(array_unique(array_filter([
            self::host(),
        ])));
    }

    /**
     * @return array<int, string>
     */
    public static function statefulDomains(): array
    {
        $host = self::host();

        return array_values(array_unique(array_filter([
            $host,
            self::withPort($host, self::httpsPort()),
            self::withPort($host, self::httpPort()),
        ])));
    }

    public static function httpPort(): int
    {
        return self::normalizePort(env('PUBLIC_HTTP_PORT'), self::DEFAULT_HTTP_PORT);
    }

    public static function httpsPort(): int
    {
        return self::normalizePort(env('PUBLIC_HTTPS_PORT'), self::DEFAULT_HTTPS_PORT);
    }

    private static function origin(string $scheme, int $port): string
    {
        $origin = sprintf('%s://%s', $scheme, self::host());

        if (($scheme === 'https' && $port === self::DEFAULT_HTTPS_PORT) || ($scheme === 'http' && $port === self::DEFAULT_HTTP_PORT)) {
            return $origin;
        }

        return sprintf('%s:%d', $origin, $port);
    }

    private static function withPort(string $host, int $port): string
    {
        return sprintf('%s:%d', $host, $port);
    }

    private static function normalizePort(mixed $port, int $default): int
    {
        return is_numeric($port) && (int) $port > 0 ? (int) $port : $default;
    }
}