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
        $variables['items'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'])['hydra:member'];
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
        $variables['item'] = $commonGroundService->getResource(['component' => 'pdc', 'type' => 'products', 'id'=>$id]);
        $variables['sourceOrganization'] = $commonGroundService->getResource($variables['item']['sourceOrganization']);
        $variables['contact'] = $commonGroundService->getResource($variables['sourceOrganization']['contact']);

        //get reviews of this event
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews', 'resource' => $variables['item']['@id']])['hydra:member'];

        //add review
        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['organization'] = $variables['item']['sourceOrganization'];
            $resource['resource'] = $variables['item']['@id'];
            $resource['author'] = $this->getUser()->getPerson();
            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);
        }

        return $variables;
    }
}
