<?php

namespace App\Security;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants the ROLE_OAUTH2_<SCOPE> roles to a token that carries no scope, from the scopes of its
 * client's record.
 *
 * The long-lived tokens issued to the radio clients before the clients had any scope carry none,
 * and so get no role at all from the OAuth bundle, which only derives roles from the token's own
 * scopes. Those tokens cannot be reissued on the clients' side, so their scopes are read from the
 * client instead: what the client is allowed to do today is what its token may do. A token that
 * does carry scopes is not affected: it is judged on them, by the role voter.
 *
 * @extends Voter<string, mixed>
 */
final class ClientScopesVoter extends Voter
{
    private const string ROLE_PREFIX = 'ROLE_OAUTH2_';

    public function __construct(
        private ClientManagerInterface $clientManager,
    ) {
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::ROLE_PREFIX);
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$token instanceof OAuth2Token || [] !== $token->getScopes()) {
            return false;
        }

        $client = $this->clientManager->find($token->getOAuthClientId());

        if (null === $client || !$client->isActive()) {
            return false;
        }

        foreach ($client->getScopes() as $scope) {
            if ($attribute === self::ROLE_PREFIX.strtoupper((string) $scope)) {
                return true;
            }
        }

        return false;
    }
}
