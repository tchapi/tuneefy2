<?php

namespace App\EventSubscriber;

use App\Services\ClientCredentialsGrant;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiBypassSubscriber implements EventSubscriberInterface
{
    private ?AccessTokenEntityInterface $generatedToken = null;

    public function __construct(
        private ClientCredentialsGrant $grant,
        private AuthorizationServer $server,
        private AccessTokenRepositoryInterface $repository,
        private string $bypassClientIdentifier,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            // don't do anything if it's not the main request
            return;
        }

        $request = $event->getRequest();

        // The OAuth2 bundle only recognizes "Bearer <token>" (case-sensitive), whereas the
        // scheme is case-insensitive (RFC 6750) and some clients send "bearer <token>"
        $authorization = $request->headers->get('Authorization', '');
        if (preg_match('/^\s*bearer\s+(\S+)/i', $authorization, $m) && !str_starts_with($authorization, 'Bearer ')) {
            $request->headers->set('Authorization', 'Bearer '.$m[1]);
        }

        if ($request->query->has('access_token')) {
            // We have a token in the query, pass it down in the headers
            $request->headers->set('Authorization', 'Bearer '.$request->query->get('access_token'));

            return;
        }

        if (!$request->hasSession()) {
            // don't do anything if no session
            return;
        }

        if (!str_starts_with($request->getRequestUri(), '/v2/')) {
            // Only api requests
            return;
        }

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        $session = $request->getSession();
        $shouldBypass = $session->get('shouldBypass', false);

        if (!$shouldBypass) {
            return;
        }

        if (!$this->bypassClientIdentifier) {
            error_log('Bypass client identifier is not set');

            return;
        }

        // Construct the ClientCredentials grant server
        $this->server->enableGrantType($this->grant);

        // Request a new token (valid only 2 minutes)
        $this->generatedToken = $this->grant->getAccessTokenForClient(new \DateInterval('PT2M'), $this->bypassClientIdentifier);

        if (null === $this->generatedToken) {
            return;
        }

        // Add the token to the request
        $request->headers->set('Authorization', 'Bearer '.$this->generatedToken->toString());
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$this->generatedToken) {
            return;
        }

        $this->repository->revokeAccessToken($this->generatedToken->getIdentifier());
        $this->generatedToken = null;
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            // Just after the session listener:
            // Symfony\Component\HttpKernel\EventListener\SessionListener
            // which has a priority of 128
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }
}
