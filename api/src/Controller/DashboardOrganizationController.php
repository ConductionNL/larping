<?php

// src/Controller/DefaultController.php

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
 * The DashboardController handles any calls about administration and dashboard pages.
 *
 * Class DashboardController
 *
 * @Route("/dashboard/organization")
 */
class DashboardOrganizationController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/{organization}/organization")
     * @Template
     */
    public function organizationAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);

        return $variables;
    }

    /**
     * @Route("/{organization}/events")
     * @Template
     */
    public function eventsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $organization])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $event = $request->request->all();
            // Set the current organization as owner
            $event['organization'] = $variables['organization']['@id'];
            // Save the resource
            $commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);
        }

        return $variables;
    }

    /**
     * @Route("/{organization}/products")
     * @Template
     */
    public function productsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['organization' => $organization])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $product = $request->request->all();
            // Set the current organization as owner
            $product['organization'] = $variables['organization']['@id'];
            // Save the resource
            $commonGroundService->saveResource($product, ['component' => 'pdc', 'type' => 'products']);
        }

        return $variables;
    }

    /**
     * @Route("/{organization}/orders")
     * @Template
     */
    public function ordersAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['orders'] = $commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['organization' => $organization])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $order = $request->request->all();
            // Set the current organization as owner
            $order['organization'] = $variables['organization']['@id'];
            // Save the resource
            $commonGroundService->saveResource($order, ['component' => 'orc', 'type' => 'orders', 'id' => false]);
        }

        return $variables;
    }

    /**
     * @Route("/{organization}/customers")
     * @Template
     */
    public function customersAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['customers'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/members")
     * @Template
     */
    public function membersAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['members'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/mailings")
     * @Template
     */
    public function mailingsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['mailings'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/reviews")
     * @Template
     */
    public function reviewsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['reviews'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/balance")
     * @Template
     */
    public function balanceAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['acounts'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/reservations")
     * @Template
     */
    public function reservationsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['reservations'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/earnings")
     * @Template
     */
    public function earningsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['earnings'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/characters")
     * @Template
     */
    public function charactersAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['characters'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/skills")
     * @Template
     */
    public function skillsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['skills'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/items")
     * @Template
     */
    public function itemsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['items'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/conditions")
     * @Template
     */
    public function conditionsAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['conditions'] = [];

        return $variables;
    }

    /**
     * @Route("/{organization}/storylines")
     * @Template
     */
    public function storylinesAction(CommonGroundService $commonGroundService, Request $request, $organization)
    {
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $variables['storylines'] = [];

        return $variables;
    }
}
