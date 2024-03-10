<?php

namespace App\EventSubscriber;

use App\Services\StatsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiStatsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private StatsService $statsService,
    ) {
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $method = $event->getRequest()->attributes->get('api_method');
        // TODO get client id
        $clientId = null;

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
