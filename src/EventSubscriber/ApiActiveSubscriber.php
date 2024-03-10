<?php

namespace App\EventSubscriber;

use App\Services\StatsService;
use App\Utils\ApiUtils;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiActiveSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private StatsService $statsService,
        private ApiUtils $apiUtils
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        // TODO get client id
        $clientId = null;

        // Only for API requests

        if (null !== $clientId) {
            $active = $this->statsService->isClientActive($clientId);
            if (false === $active) {
                $event->stopPropagation();

                $response = $this->apiUtils->createGenericErrorResponse($event->getRequest(), 'NOT_ACTIVE', 401);
                $event->setResponse($response);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
        ];
    }
}
