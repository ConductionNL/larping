<?php

// src/Controller/EventController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The EventController handles any calls about events.
 *
 * Class EventController
 *
 * @Route("/events")
 */
class EventController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
        $variables['items'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];
        $variables['pathToSingular'] = 'app_event_event';
        $variables['typePlural'] = 'events';

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function eventAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $variables = [];
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);
        if (isset($variables['event']['resource']) && strpos($variables['event']['resource'], '/pdc/products/')) {
            $variables['product'] = $commonGroundService->getResource($variables['event']['resource']);
        }
//        /*@todo remove after testing*/
//        $url = 'https://dev.larping.eu/api/v1/wrc/organizations/7b863976-0fc3-4f49-a4f7-0bf7d2f2f535';
//        $variables['sourceOrganization'] = $commonGroundService->getResource(/*$variables['item']['sourceOrganization']*/ $url);
//        $variables['contact'] = $commonGroundService->getResource($variables['sourceOrganization']['contact']);

        return $variables;
    }
}
