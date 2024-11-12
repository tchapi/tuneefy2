<?php

namespace App\EventSubscriber;

use App\Services\StatsService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiStatsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private StatsService $statsService,
    ) {
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            // don't do anything if it's not the main request
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getRequestUri(), '/api/v2/')) {
            // Only api requests
            return;
        }

        $token = $this->security->getToken();

        if (!$token) {
            return;
        }

        $method = $request->attributes->get('api_method');

        $clientId = $token->getAttribute('oauth_client_id');

        if ($method && $clientId) {
            $this->statsService->addApiCallingStat($method, $clientId);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }
}
