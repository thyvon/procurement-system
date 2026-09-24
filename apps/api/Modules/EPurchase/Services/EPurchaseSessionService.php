<?php

namespace Modules\EPurchase\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed store for the company (E-Purchase) session, keyed by local user id.
 * TTL mirrors the upstream session (~30 minutes by default).
 */
class EPurchaseSessionService
{
    private function store(): CacheRepository
    {
        return Cache::store(config('cache.default', 'database'));
    }

    private function key(int|string $userId): string
    {
        return 'epurchase.session.'.$userId;
    }

    public function put(int|string $userId, EPurchaseSession $session): void
    {
        $ttl = max(60, (int) config('epurchase.session_ttl', 1800));

        $this->store()->put($this->key($userId), [
            'jwt' => $session->jwt,
            'formToken' => $session->formToken,
            'cookieHeader' => $session->cookieHeader,
            'expiresAt' => time() + $ttl,
        ], $ttl);
    }

    public function get(int|string $userId): ?EPurchaseSession
    {
        $payload = $this->store()->get($this->key($userId));

        if (! is_array($payload) || ! isset($payload['jwt'], $payload['expiresAt'])) {
            return null;
        }

        $session = new EPurchaseSession(
            jwt: (string) $payload['jwt'],
            formToken: isset($payload['formToken']) && is_string($payload['formToken'])
                ? $payload['formToken']
                : null,
            cookieHeader: isset($payload['cookieHeader']) && is_string($payload['cookieHeader'])
                ? $payload['cookieHeader']
                : '',
            expiresAt: (int) $payload['expiresAt'],
        );

        if ($session->isExpired()) {
            $this->forget($userId);

            return null;
        }

        return $session;
    }

    public function forget(int|string $userId): void
    {
        $this->store()->forget($this->key($userId));
    }
}
