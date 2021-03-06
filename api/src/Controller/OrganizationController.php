<?php

// src/Controller/OrganizationController.php

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

/**
 * The OrganizationController handles any calls about organizations.
 *
 * Class OrganizationController
 *
 * @Route("/organizations")
 */
class OrganizationController extends AbstractController
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

        // Processing form input
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

        if ($variables['sorting']) {
            $sorting = explode('-', $variables['sorting']);
            $query[] = ['order['.$sorting[0].']' => $sorting[1]];
        }

        if ($variables['search']) {
            $query['name'] = $variables['search'];
        }

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'], $query)['hydra:member'];

        // Lets sort (we do this post query so that we might filter on ratins
        $sorting = explode('-', $variables['sorting']);

        // if logged in set the author for checking if this user liked an organization
        $author = false;
        if ($this->getUser()) {
            $author = $this->getUser()->getPerson();
        }

        foreach ($variables['organizations'] as $key => $organization) {
            // if we are sorting by rating lets get the rating
            if ($sorting[0] == 'rating' || $sorting[0] == 'likes') {
                $variables['organizations'][$key]['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['organization'=>$organization['@id'], 'resource'=>$organization['@id'], 'author'=>$author]);
                $variables['organizations'][$key]['rating'] = $variables['organizations'][$key]['totals']['rating'];
                $variables['organizations'][$key]['likes'] = $variables['organizations'][$key]['totals']['likes'];
                $variables['organizations'][$key]['liked'] = $variables['organizations'][$key]['totals']['liked'];
            }
            // hotfix -> remove unwanted organizations
            if (!empty($resourceIds) && !in_array($organization['id'], $resourceIds)) {
                unset($variables['organizations'][$key]);
            }
        }

        $columns = array_column($variables['organizations'], $sorting[0]);
        if ($sorting[1] == 'asc') {
            array_multisort($columns, SORT_ASC, $variables['organizations']);
        } else {
            array_multisort($columns, SORT_DESC, $variables['organizations']);
        }

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function organizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $variables = [];
        $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);
        $variables['organization'] = $commonGroundService->getResource($organizationUrl);
        if (array_key_exists('contact', $variables['organization']) && $variables['organization']['contact']) {
            $variables['contact'] = $commonGroundService->getResource($variables['organization']['contact']);
        }

        // if logged in set the author for checking if this user liked this organization or any of it's events
        $author = false;
        if ($this->getUser()) {
            $author = $this->getUser()->getPerson();
        }
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['resource' => $variables['organization']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id'], 'status' => 'published'])['hydra:member'];
        foreach ($variables['events'] as $key => $event) {
            $variables['events'][$key]['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['organization' => $event['organization'], 'resource'=>$event['@id'], 'author'=>$author]);
        }
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['organization' => $variables['organization']['@id'], 'resource' => $variables['organization']['@id'], 'author' => $author]);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $variables['organization']['id']])['hydra:member'];

        // Getting the offers
        // Only get these of events that are published TODO: this might need some cleaner code:
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['offeredBy' => $variables['organization']['@id'], 'products.type' => 'simple'])['hydra:member'];
        foreach ($variables['products'] as $key => $offer) {
            if (isset($offer['products'])) {
                foreach ($offer['products'] as $product) {
                    if (isset($product['event']) and $commonGroundService->isResource($product['event'])) {
                        $event = $commonGroundService->getResource($product['event']);
                        break;
                    }
                }
            }
            if ((isset($event) and $event['status'] != 'published') or !isset($event)) {
                unset($variables['products'][$key]);
            }
        }
        $variables['tickets'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['offeredBy' => $variables['organization']['@id'], 'products.type' => 'ticket'])['hydra:member'];
        foreach ($variables['tickets'] as $key => $offer) {
            if (isset($offer['products'])) {
                foreach ($offer['products'] as $product) {
                    if (isset($product['event']) and $commonGroundService->isResource($product['event'])) {
                        $event = $commonGroundService->getResource($product['event']);
                        break;
                    }
                }
            }
            if ((isset($event) and $event['status'] != 'published') or !isset($event)) {
                unset($variables['tickets'][$key]);
            }
        }
        $variables['subscriptions'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['offeredBy' => $variables['organization']['@id'], 'products.type' => 'subscription'])['hydra:member'];
        foreach ($variables['subscriptions'] as $key => $offer) {
            if (isset($offer['products'])) {
                foreach ($offer['products'] as $product) {
                    if (isset($product['event']) and $commonGroundService->isResource($product['event'])) {
                        $event = $commonGroundService->getResource($product['event']);
                        break;
                    }
                }
            }
            if ((isset($event) and $event['status'] != 'published') or !isset($event)) {
                unset($variables['subscriptions'][$key]);
            }
        }

        return $variables;
    }
}
