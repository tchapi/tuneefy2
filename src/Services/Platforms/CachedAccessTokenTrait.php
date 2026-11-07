<?php

namespace App\Services\Platforms;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * For platforms using the OAuth2 client_credentials flow: access tokens last about an hour
 * and are meant to be reused, some platforms even limit how many may be requested. The token
 * is therefore cached for its lifetime instead of being requested on every single API call.
 */
trait CachedAccessTokenTrait
{
    private ?CacheInterface $cache = null;

    #[Required]
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Requests a new access token from the platform.
     *
     * @return array{access_token?: string, expires_in?: int}
     */
    abstract protected function requestAccessToken(): array;

    protected function getAccessToken(): ?string
    {
        if (null === $this->cache) {
            return $this->requestAccessToken()['access_token'] ?? null;
        }

        return $this->cache->get(static::TAG.'_access_token_'.md5((string) $this->key), function (ItemInterface $item): ?string {
            $result = $this->requestAccessToken();
            $token = $result['access_token'] ?? null;

            // A failed request is remembered for a minute only, so as not to burn any quota
            $item->expiresAfter(null === $token ? 60 : max(60, (int) ($result['expires_in'] ?? 3600) - 300));

            return $token;
        });
    }
}
