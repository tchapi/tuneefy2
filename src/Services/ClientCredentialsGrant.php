<?php

declare(strict_types=1);

namespace App\Services;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\ClientCredentialsGrant as BaseClientCredentialsGrant;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * This is a copy of League\OAuth2\Server\Grant\ClientCredentialsGrant to be able to tweak the behavior
 * and add a "subject" claim in the access token, with the client id in it.
 *
 * We also add an utility function to issue a token programmatically.
 */
final class ClientCredentialsGrant extends BaseClientCredentialsGrant
{
    public function getAccessTokenForClient(
        \DateInterval $accessTokenTTL,
        string $clientId
    ): AccessTokenEntityInterface {
        $client = $this->clientRepository->getClientEntity($clientId);

        return $this->issueAccessToken($accessTokenTTL, $client, $client->getIdentifier(), []);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        list($clientId) = $this->getClientCredentials($request);

        $client = $this->getClientEntityOrFail($clientId, $request);

        if (!$client->isConfidential()) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::CLIENT_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::invalidClient($request);
        }

        // Validate request
        $this->validateClient($request);

        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

        // Finalize the requested scopes
        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client);

        // Issue and persist access token
        // [CHANGED]: we use the client identifier as user identifier, instead of null
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $client->getIdentifier(), $finalizedScopes);

        // Send event to emitter
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));

        // Inject access token into response type
        $responseType->setAccessToken($accessToken);

        return $responseType;
    }
}
