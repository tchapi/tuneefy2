<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['setLocaleFromCookie'],
            ],
        ];
    }

    public function setLocaleFromCookie(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            // don't do anything if it's not the main request
            return;
        }

        $locale = $event->getRequest()->cookies->get('tuneefyLocale');
        if ($locale) {
            $this->localeSwitcher->setLocale($locale);
        }
    }
}
