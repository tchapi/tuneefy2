<?php

namespace App\EventSubscriber;

use App\Services\ClientCredentialsGrant;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiBypassSubscriber implements EventSubscriberInterface
{
    private $generatedToken;

    public function __construct(
        private ClientCredentialsGrant $grant,
        private AuthorizationServer $server,
        private AccessTokenRepositoryInterface $repository
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            // don't do anything if it's not the main request
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession()) {
            // don't do anything if no session
            return;
        }

        if (!str_starts_with($request->getRequestUri(), '/api/v2/')) {
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

        // Construct the ClientCredentials grant server
        $this->server->enableGrantType($this->grant);

        // Request a new token
        $this->generatedToken = $this->grant->getAccessTokenForClient(new \DateInterval('PT2M'), '83a69b8b99fec3ef0da7db18af76c7d9');

        // Add the token to the request
        $request = $request->headers->set('Authorization', 'Bearer '.$this->generatedToken->__toString());
    }

    public function onFinishRequest(FinishRequestEvent $event): void
    {
        if (!$this->generatedToken) {
            return;
        }

        $this->repository->revokeAccessToken($this->generatedToken->getIdentifier());
    }

    public static function getSubscribedEvents(): array
    {
        return [
          // Just after the session listener:
          // Symfony\Component\HttpKernel\EventListener\SessionListener
          // which has a priority of 128
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::FINISH_REQUEST => 'onFinishRequest',
        ];
    }
}
