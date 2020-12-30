<?php

namespace App\Subscriber;

use Conduction\IdVaultBundle\Event\IdVaultEvents;
use Conduction\IdVaultBundle\Event\LoggedInEvent;
use Conduction\IdVaultBundle\Event\NewUserEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{

    public function __construct()
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            IdVaultEvents::NEWUSER  => 'newUser',
            IdVaultEvents::LOGGEDIN => 'loggedIn'
        ];
    }

    public function newUser(NewUserEvent $event)
    {
        $object = $event->getResource();
        // new user magic comes here
    }

    public function loggedIn(LoggedInEvent $event)
    {
        $object = $event->getResource();
        //login actions
    }

}
