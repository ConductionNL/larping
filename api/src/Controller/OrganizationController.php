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
        $variables['sorting'] = $request->get('sorting_order', false);
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
            $query[] = ['name'=> $variables['search']];
        }

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'], $query)['hydra:member'];

        // hotfix -> remove unwanted evenst
        foreach ($variables['organizations'] as $key => $organization) {
            if (!empty($resourceIds) && !in_array($organization['id'], $resourceIds)) {
                unset($variables['organizations'][$key]);
            }
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
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id'=>$id]);
        if (array_key_exists('contact', $variables['organization']) && $variables['organization']['contact']) {
            $variables['organization']['contact'] = $commonGroundService->getResource($variables['organization']['contact']);
        }

        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['resource' => $variables['organization']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['resource' => $variables['organization']['@id']]);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $variables['organization']['id']])['hydra:member'];

        // Getting the offers
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id'], 'products.type' => 'simple'])['hydra:member'];
        $variables['tickets'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id'], 'products.type' => 'ticket'])['hydra:member'];
        $variables['subscriptions'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id'], 'products.type' => 'subscription'])['hydra:member'];

        return $variables;
    }
}
