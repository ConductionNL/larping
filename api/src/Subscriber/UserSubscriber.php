<?php

namespace App\Subscriber;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Event\IdVaultEvents;
use Conduction\IdVaultBundle\Event\LoggedInEvent;
use Conduction\IdVaultBundle\Event\NewUserEvent;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    private $idVaultService;
    private $commonGroundService;
    private $params;

    public function __construct(IdVaultService $idVaultService, CommonGroundService $commonGroundService, ParameterBagInterface $params)
    {
        $this->idVaultService = $idVaultService;
        $this->commonGroundService = $commonGroundService;
        $this->params = $params;
    }

    public static function getSubscribedEvents()
    {
        return [
            IdVaultEvents::NEWUSER  => 'newUser',
            IdVaultEvents::LOGGEDIN => 'loggedIn',
        ];
    }

    public function newUser(NewUserEvent $event)
    {
        $object = $event->getResource();
        // new user magic comes here

        // Get app_id of larping application
        $providers = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $this->params->get('app_id')])['hydra:member'];
        $appId = $providers[0]['configuration']['app_id'];

        //send mail to new user
        $data = [];
        $data['username'] = $object['username'];
        $data['person'] = $this->commonGroundService->getResource($object['person']);
        $this->idVaultService->sendMail($appId, 'emails/new_user.html.twig', 'welcome', $data['username'], 'no-reply@conduction.nl', $data);
    }

    public function loggedIn(LoggedInEvent $event)
    {
        $object = $event->getResource();
        //login actions
    }
}
