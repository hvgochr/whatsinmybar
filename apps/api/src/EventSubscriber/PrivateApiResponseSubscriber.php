<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PrivateApiResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['protectCache', -1024]];
    }

    public function protectCache(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
            $event->getResponse()->setVary(['Authorization', 'Cookie'], false);
        }
    }
}
