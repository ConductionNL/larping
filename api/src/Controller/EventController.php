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
        $variables['path'] = 'app_event_event';
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews', 'resource' => $variables['event']['@id']])['hydra:member'];

        if (isset($variables['event']['resource']) && strpos($variables['event']['resource'], '/pdc/products/')) {
            $variables['product'] = $commonGroundService->getResource($variables['event']['resource']);
        }

        // Add review
        if ($request->isMethod('POST') && $request->request->get('addReview') == 'true') {
            $resource = $request->request->all();


            $resource['organization'] = $variables['event']['organization'];
            $resource['resource'] = $variables['event']['@id'];
            $resource['author'] = $this->getUser()->getPerson();
            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);
        }

        // Make order in session
        if ($request->isMethod('POST') && $request->request->get('makeOrder') == 'true' && $request->request->get('offer') &&
            $request->request->get('quantity') != 0) {
            $resource = $request->request->all();

            // If no quantity is given set it to 1
            if (!$resource['quantity']) {
                $resource['quantity'] = 1;
            }

            // Check if we already have an order in the session
            if ($session->get('order')) {
                $order = $session->get('order');
            } else {
                $order = [];
            }

            // Get offer object
            $offer = $commonGroundService->getResource($resource['offer']);

            // Add offer
            $order['items'][] = [
                'offer' => $resource['offer'],
                'quantity' => $resource['quantity'],
                'path' => '/events/'.$variables['event']['id'],
                'price' => $offer['price'] * $resource['quantity']
            ];

            // Set order in the session
            $session->set('order', $order);

            return $this->redirectToRoute('app_order_index');
        }
        return $variables;
    }
}
