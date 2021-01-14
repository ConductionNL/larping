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

        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['features'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'features'])['hydra:member'];
        $variables['search'] = $request->get('search', false);
        $variables['categories'] = $request->get('categories', []);
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];

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
            $variables['contact'] = $commonGroundService->getResource($variables['organization']['contact']);
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
