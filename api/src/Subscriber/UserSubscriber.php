<?php

namespace App\Subscriber;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Event\IdVaultEvents;
use Conduction\IdVaultBundle\Event\LoggedInEvent;
use Conduction\IdVaultBundle\Event\NewUserEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{

    private $mailingService;
    private $commonGroundService;
    public function __construct(MailingService $mailingService, CommonGroundService $commonGroundService)
    {
        $this->mailingService = $mailingService;
        $this->commonGroundService = $commonGroundService;
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

        //send mail to new user
        $data = [];
        $data['username'] = $object['username'];
        $data['person'] = $this->commonGroundService->getResource($object['person']);
        $this->mailingService->sendMail('emails/new_user.html.twig', 'no-reply@conduction.nl', $data['username'], 'welcome', $data);
    }

    public function loggedIn(LoggedInEvent $event)
    {
        $object = $event->getResource();
        //login actions

    }

}
