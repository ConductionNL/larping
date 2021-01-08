<?php

// src/Controller/EventController.php

namespace App\Controller;

use App\Service\MailingService;
use App\Service\ShoppingService;
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

        if ($request->isMethod('POST')) {
            $variables['filters'] = $request->request->get('filters');

            if (!$request->request->get('resetFilters')) {

                // We do 3 calls because we need to filter separately because if not it gives a empty list back if one filter doesn't find any results
                $events = [];
                if (isset($variables['filters']['keywordsInput']) && !empty($variables['filters']['keywordsInput'])) {
                    $events[] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['name' => $variables['filters']['keywordsInput']])['hydra:member'];
                    $events[] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['description' => $variables['filters']['keywordsInput']])['hydra:member'];
                }
                if (isset($variables['filters']['locationInput']) && !empty($variables['filters']['locationInput'])) {
                    $events[] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['location' => $variables['filters']['locationInput']])['hydra:member'];
                }

                // Looping through events to remove duplicates
                if (count($events) > 0) {
                    $eventIds = [];
                    $variables['events'] = [];
                    foreach ($events as $key => $event) {
                        if (empty($event)) {
                            unset($events[$key]);
                        }
                        // Because of previous array merging there gets an array in a array or multiple arrays in which we need to find the actual events..
                        if (is_array($event) && !isset($event['id'])) {
                            foreach ($event as $item) {
                                if (isset($item['id']) && !in_array($item['id'], $eventIds)) {
                                    $variables['events'][] = $item;
                                    $eventIds[] = $item['id'];
                                }
                            }
                        } elseif (isset($event['id']) && !in_array($event['id'], $eventIds)) {
                            $variables['events'][] = $event;
                            $eventIds[] = $event['id'];
                        }
                    }
                }
            }
        }

        // Shitty code but it works
        // If filter is not set or reset filters has been clicked fetch all events
        if (((!isset($variables['filters']['locationInput']) or $variables['filters']['locationInput'] == '') &&
                (!isset($variables['filters']['keywordsInput'])) or $variables['filters']['keywordsInput'] == '') or
            $request->request->get('resetFilters')) {
            $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];
        }

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function eventAction(Session $session, Request $request, CommonGroundService $commonGroundService, ShoppingService $ss, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $variables = [];
        $variables['path'] = 'app_event_event';
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews', 'resource' => $variables['event']['@id']])['hydra:member'];
        $variables['groups'] = $commonGroundService->getResource(['component' => 'pdc', 'type' => 'groups']);
        $variables['stats'] = ['likes' => 100, 'reviews' => 5];

        /* deze is wat wierd */
        if (isset($variables['event']['resource']) && strpos($variables['event']['resource'], '/pdc/products/')) {
            $variables['product'] = $commonGroundService->getResource($variables['event']['resource']);
        }

        // Add review
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Review') {
            $resource = $request->request->all();


            $resource['organization'] = $variables['event']['organization'];
            $resource['resource'] = $variables['event']['@id'];
            $resource['author'] = $this->getUser()->getPerson();

            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);

        }

        // Make order in session
        if ($request->isMethod('POST') && $request->request->get('makeOrder') == 'true' &&
            $request->request->get('offers')) {

            $resource = $request->request->all();

            // Add offers to session
            $order = $ss->addItemsToCart($resource['offers']);

            return $this->redirectToRoute('app_order_index');
        }
        return $variables;
    }
}
