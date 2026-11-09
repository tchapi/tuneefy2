<?php

namespace App\Services\Platforms;

/**
 * For platforms using the OAuth2 client_credentials flow: access tokens last about an hour
 * and are meant to be reused, some platforms even limit how many may be requested. The token
 * is therefore cached for its lifetime instead of being requested on every single API call.
 */
trait CachedAccessTokenTrait
{
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

        $item = $this->cache->getItem(static::TAG.'_access_token_'.md5($this->key));

        if ($item->isHit()) {
            return $item->get();
        }

        $result = $this->requestAccessToken();
        $token = $result['access_token'] ?? null;

        if (null === $token) {
            $encoded = json_encode($result);
            $this->logFailure('access token request', false === $encoded ? null : $encoded);
        }

        // A failed request is remembered for a minute only, so as not to burn any quota
        $item->set($token)->expiresAfter(null === $token ? 60 : max(60, ($result['expires_in'] ?? 3600) - 300));
        $this->cache->save($item);

        return $token;
    }
}
