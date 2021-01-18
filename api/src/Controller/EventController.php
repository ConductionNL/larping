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
        $variables['sorting'] = $request->get('sorting_order', 'rating-desc');
        $variables['search'] = $request->get('search', false);
        $variables['categories'] = $request->get('categories', []);

        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['regions'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'regions'])['hydra:member'];
        $variables['features'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'features'])['hydra:member'];

        // Processing form input by building our search query
        $query = [];
        $resourceIds = [];
        if (!empty($variables['categories'])) {
            $categoryQuery['categories.id'] = $variables['categories'];
            $categoryQuery['filter'] = 'id';

            $resourcecategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], $categoryQuery)['hydra:member'];

            $resourceIds = [];
            foreach ($resourcecategories as $resourcecategory) {
                $resourceIds[] = $commonGroundService->getUuidFromUrl($resourcecategory['resource']);
            }
            $query['id'] = $resourceIds;
        }

        if ($variables['search']) {
            $query['name'] =  $variables['search'];
        }

        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], $query)['hydra:member'];

        // Lets sort (we do this post query so that we might filter on ratins
        $sorting = explode('-', $variables['sorting']);

        // hotfix -> remove unwanted evenst
        foreach ($variables['events'] as $key => $event) {
            // if we are sorting by rating lets get the rating
            if($sorting[0] == 'rating' || $sorting[0] == 'likes'){
                $variables['events'][$key]['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['resource'=>$event['@id']]);
                $variables['events'][$key]['rating'] =  $variables['events'][$key]['totals']['rating'];
                $variables['events'][$key]['likes'] =  $variables['events'][$key]['totals']['likes'];
            }
            // hotfix -> remove unwanted evenst
            if (!empty($resourceIds) && !in_array($event['id'], $resourceIds)) {
                unset($variables['events'][$key]);
            }
        }

        $columns = array_column($variables['events'], $sorting[0]);
        if($sorting[1] == 'asc'){
            array_multisort($columns, SORT_ASC, $variables['events']);
        }
        else {
            array_multisort($columns, SORT_DESC, $variables['events']);
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
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['resource' => $variables['event']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['resource' => $variables['event']['@id']]);

        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'];

        // Getting the offers
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['products.event' =>  $variables['event']['@id'], 'products.type' => 'simple'])['hydra:member']; // The product array is PUPRUSLY filled with offers instead of products
        $variables['tickets'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['products.event' =>  $variables['event']['@id'], 'products.type' => 'ticket'])['hydra:member'];

        return $variables;
    }
}
