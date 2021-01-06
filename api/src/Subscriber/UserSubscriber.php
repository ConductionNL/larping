<?php

namespace App\Subscriber;

use App\Service\MailingService;
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

    public function newUser(NewUserEvent $event, MailingService $mailingService)
    {
        $object = $event->getResource();
        // new user magic comes here

        //send mail to new user
        $data = [];
        $data['username'] = $object['username'];
        $mailingService->sendMail('emails/new_user.html.twig', 'no-reply@conduction.nl', $object['username'], 'welcome', $data);
    }

    public function loggedIn(LoggedInEvent $event)
    {
        $object = $event->getResource();
        //login actions
    }

}
