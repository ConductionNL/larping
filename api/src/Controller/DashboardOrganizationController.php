<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['organization' => $variables['organization']['id']]);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/events")
     * @Template
     */
    public function eventsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'], ['organization' => $variables['organization']['@id']])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $event = $request->request->all();
            // Set the current organization as owner
            $event['organization'] = $variables['organization']['@id'];
            $event['status'] = 'pending';

            $categories = $event['resource_categories'];
            if (!$categories) {
                $categories = [];
            }
            unset($event['resource_categories']);

            // Save the resource
            $event = $commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);

            // Setting the categories
            /*@todo  This should go to a wrc service */
            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource'=>$event['id']])['hydra:member'];
            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource'=>$event['@id'], 'catagories'=>[]];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
        }

        return $variables;
    }

    /**
     * @Route("/event/{id}")
     * @Template
     */
    public function eventAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id], ['organization' => $variables['organization']['@id']]);

        //Delete event
        if ($request->isMethod('POST') && $request->request->get('DeleteEvent') == 'true') {
            $del = $commonGroundService->deleteResource($variables['event'], $variables['event']['@id']);

            return $this->redirect($this->generateUrl('app_dashboardorganization_events'));
        }

        return $variables;
    }

    /**
     * @Route("/event/{id}/tickets")
     * @Template
     */
    public function eventTicketsAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id], ['organization' => $variables['organization']['@id']]);
        $variables['products'] = [];
        $variables['offers'] = [];

        return $variables;
    }

    /**
     * @Route("/event/{id}/checkin")
     * @Template
     */
    public function eventCheckinAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['type' => 'ticket'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/products")
     * @Template
     */
    public function productsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['offers'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'])['hydra:member'];

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
     * @Route("/orders")
     * @Template
     */
    public function ordersAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['orders'] = $commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['organization' => $variables['organization']['@id']])['hydra:member'];

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
     * @Route("/offers")
     * @Template
     */
    public function offersAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['offers'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/customers")
     * @Template
     */
    public function customersAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['customers'] = [];

        return $variables;
    }

    /**
     * @Route("/members")
     * @Template
     */
    public function membersAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService, ParameterBagInterface $params)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $variables['organization']['id']]);
        $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];

        $variables['groups'] = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl)['groups'];

        if (count($variables['groups']) == 0) {
            $idVaultService->createGroup($provider['configuration']['app_id'], 'root', "Root group for {$variables['organization']['name']}", $organizationUrl);
            $result = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl);
            $idVaultService->inviteUser($provider['configuration']['app_id'], $result['groups'][0]['id'], $this->getUser()->getUsername(), true);
            $variables['groups'] = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl)['groups'];
        }

        $users = [];
        foreach ($variables['groups'] as $group) {
            foreach ($group['users'] as $user) {
                if (in_array($user, $users)) {
                    $users[$user]['groups'][] = $group['name'];
                } else {
                    $users[$user]['name'] = $user;
                    $users[$user]['groups'][] = $group['name'];
                }
            }
        }
        $variables['users'] = $users;
        return $variables;
    }

    /**
     * @Route("/mailinglists")
     * @Template
     */
    public function mailinglistsAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['mailingLists'] = [];
//        $variables['mailingLists'] = $idVaultService->getSendLists();

        if ($request->isMethod('POST') && $request->request->get('MailingEvent') == 'true') {
            // Send email to all subscribers of this mailing list.
        } elseif ($request->isMethod('POST')) {
            // Get the resource
            $sendList = $request->request->all();
            // Set Organization and email sendList type
            $sendList['organization'] = $variables['organization']['@id'];
            $sendList['email'] = true;

            // Save the mailing list resource
            //$commonGroundService->saveResource($sendList, ['component' => 'bs', 'type' => 'send_lists']);
        }

        return $variables;
    }

    /**
     * @Route("/reviews")
     * @Template
     */
    public function reviewsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['reviews'] = [];

        return $variables;
    }

    /**
     * @Route("/balance")
     * @Template
     */
    public function balanceAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['acounts'] = [];

        return $variables;
    }

    /**
     * @Route("/reservations")
     * @Template
     */
    public function reservationsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['reservations'] = [];

        return $variables;
    }

    /**
     * @Route("/earnings")
     * @Template
     */
    public function earningsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['earnings'] = [];

        return $variables;
    }

    /**
     * @Route("/characters")
     * @Template
     */
    public function charactersAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['characters'] = [];

        return $variables;
    }

    /**
     * @Route("/skills")
     * @Template
     */
    public function skillsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['skills'] = [];

        return $variables;
    }

    /**
     * @Route("/items")
     * @Template
     */
    public function itemsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['items'] = [];

        return $variables;
    }

    /**
     * @Route("/conditions")
     * @Template
     */
    public function conditionsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['conditions'] = [];

        return $variables;
    }

    /**
     * @Route("/storylines")
     * @Template
     */
    public function storylinesAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['storylines'] = [];

        return $variables;
    }

    /**
     * @Route("/locations")
     * @Template
     */
    public function locationsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'features'])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $location = $request->request->all();
            // Set the current organization as owner
            $location['organization'] = $variables['organization']['@id'];

            $categories = $location['categories'];
            if (!$categories) {
                $categories = [];
            }
            unset($location['categories']);

            // Save the resource
            $commonGroundService->saveResource($location, ['component' => 'lc', 'type' => 'places']);

            // Setting the categories
            /*@todo  This should go to a wrc service */
            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource'=>$location['id']])['hydra:member'];

            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource'=>$event['@id'], 'catagories'=>[]];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
        }

        return $variables;
    }
}
