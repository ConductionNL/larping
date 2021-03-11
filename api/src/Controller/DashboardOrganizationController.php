<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

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
    public function indexAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService, ParameterBagInterface $params)
    {
        // Make sure the user is logged in
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault'));
        }

        $organizationUrl = $this->getUser()->getOrganization();
        $variables['organization'] = $commonGroundService->getResource($organizationUrl);
        // Get review component totals for this organization
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['organization' => $organizationUrl]);
        // Get all orders of this organization to get amount of sold tickets and calculate revenue
        $orders = $commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['organization' => $organizationUrl])['hydra:member'];
        // Get all events for this organization (order is important for getting the next upcoming event!) (adding order to the query will result in a 502 error for some weird reason:)
        $events = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $organizationUrl])['hydra:member']; // 'order[startDate]' => 'asc'

        // Hotfix for sorting events because adding query paramater to getResourceList results in a weird 502 error.
        array_multisort(array_column($events, 'startDate'), $events);

        // Get upcoming events only
        $today = new \DateTime('now');
        $events = array_filter($events, function ($event) use ($today) {
            // use endDate so you still count/see events that are currently ongoing
            $eventEndDate = new \DateTime($event['endDate']);

            return $eventEndDate->format('Y-m-d') > $today->format('Y-m-d');
        });
        $variables['upcomingEventsCount'] = count($events);

        // get next event from arc (if it exists) (what to do if none exists?)
        if ($variables['upcomingEventsCount'] > 0) {
            // The first one should be the next one because of order in the getResourceList for events above^
            $variables['upcomingEvent'] = $events[array_key_first($events)];

            // Get bought tickets/max tickets for upcoming event
            // First get all tickets of this upcoming event
            $tickets = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['products.event' => $variables['upcomingEvent']['@id'], 'products.type' => 'ticket'])['hydra:member'];
            $ticketUrls = array_column($tickets, '@id');

            // Calculate the sold tickets
            $ticketsSold = $ticketsSoldLastWeek = 0;
            if (count($orders) > 0) {
                foreach ($orders as $order) {
                    // Now get all orderItems where item.offer == one of the tickets^
                    $items = array_filter($order['items'], function ($item) use ($ticketUrls) {
                        return in_array($item['offer'], $ticketUrls);
                    });
                    // Add amount of tickets sold in this order to the counter
                    if (count($items) > 0) {
                        $ticketsInOrder = array_sum(array_column($items, 'quantity'));
                        $ticketsSold += $ticketsInOrder;

                        // Check if these tickets were sold last week
                        $dateCreated = new \DateTime($order['dateCreated']);
                        $dateCreated->format('Y-m-d');
                        $lastWeekMonday = new\DateTime('last week monday');
                        $lastWeekMonday->format('Y-m-d');
                        $lastWeekSunday = new\DateTime('last week sunday');
                        $lastWeekSunday->format('Y-m-d');
                        if ($dateCreated >= $lastWeekMonday && $dateCreated <= $lastWeekSunday) {
                            $ticketsSoldLastWeek += $ticketsInOrder;
                        }
                    }
                }
            }
            $variables['ticketsSold'] = $ticketsSold;
            $variables['ticketsSoldLastWeek'] = $ticketsSoldLastWeek;
        }

        $variables['revenue']['thisMonth'] = $variables['revenue']['lastMonth'] = '€ 0,00';
        if (count($orders) > 0) {
            // Filter out the orders of this month
            $today = new \DateTime('now');
            $ordersThisMonth = array_filter($orders, function ($order) use ($today) {
                $dateCreated = new \DateTime($order['dateCreated']);

                return $dateCreated->format('Y-m') == $today->format('Y-m');
            });
            // ...and last month
            $today->sub(new \DateInterval('P1M'));
            $ordersLastMonth = array_filter($orders, function ($order) use ($today) {
                $dateCreated = new \DateTime($order['dateCreated']);

                return $dateCreated->format('Y-m') == $today->format('Y-m');
            });

            // Calculate revenue
            if (count($ordersThisMonth) > 0) {
                // Calculate revenue of this organization, this month
                $prices = array_column($ordersThisMonth, 'price');
                $variables['revenue']['thisMonth'] = '€ '.number_format(array_sum($prices), 2, ',', '.');
            }
            if (count($ordersLastMonth) > 0) {
                // Calculate revenue of this organization, last month
                $prices = array_column($ordersLastMonth, 'price');
                $variables['revenue']['lastMonth'] = '€ '.number_format(array_sum($prices), 2, ',', '.');
            }
        }

        // Get all members from id-vault
        $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];
        $groups = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl)['groups'];

        // Count users
        $users = [];
        foreach ($groups as $group) {
            foreach ($group['users'] as $user) {
                if (!in_array($user['username'], $users)) {
                    $users[$user['username']]['dateAccepted'] = $user['dateAccepted'];
                }
            }
        }
        $variables['totalUsers'] = count($users);

        // Now get the total new users sinds last month.
        $variables['newUsersThisMonth'] = count(array_filter($users, function ($user) {
            $dateAccepted = new \DateTime($user['dateAccepted']);
            $today = new \DateTime('now');

            return $dateAccepted->format('Y-m') == $today->format('Y-m');
        }));

        return $variables;
    }

    /**
     * @Route("/events")
     * @Template
     */
    public function eventsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $event = $request->request->all();
            // Set the current organization as owner
            $event['organization'] = $variables['organization']['@id'];
            $event['status'] = 'private';

            // Save the resource
            $event = $commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);

            // redirects externally
            if (array_key_exists('id', $event) && $event['id']) {
                return $this->redirectToRoute('app_dashboardorganization_event', ['id' => $event['id']]);
            }
        }

        return $variables;
    }

    /**
     * @Route("/events/{id}")
     * @Template
     */
    public function eventAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'features'])['hydra:member'];
        $variables['activeCategories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'];
        $variables['activeCategories'] = array_column($variables['activeCategories'], 'id');

        if ($id != 'add') {
            $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);
            $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['event' => $variables['event']['@id']])['hydra:member'];
        } else {
            $variables['event'] = [];
            $variables['products'] = [];
        }
        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'settings'])['hydra:member'];
        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'], ['organization' => $variables['organization']['@id']])['hydra:member'];

        // Update event
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Event') {
            // Get the current resource
            $event = $request->request->all();
            // Set the current organization as owner
            $event['organization'] = $variables['organization']['@id'];
            if ($id == 'add') {
                $event['status'] = 'private';
            }

            if (isset($event['categories'])) {
                $categories = $event['categories'];
                if (!$categories) {
                    $categories = [];
                }
                unset($event['categories']);
            }

            // Save the resource
            $event = $commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);

            // Only do categories stuff when aplicable
            if (!array_key_exists('categories', $event)) {
                return $this->redirectToRoute('app_dashboardorganization_event', ['id' => $event['id']]);
            }

            // Setting the categories
            /*@todo  This should go to a wrc service */
            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource' => $event['id']])['hydra:member'];
            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource' => $event['@id'], 'catagories' => []];
            }

            if (isset($categories)) {
                $resourceCategory['categories'] = $categories;

                $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
            }

            return $this->redirectToRoute('app_dashboardorganization_event', ['id' => $event['id']]);
        }

        // Add product
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Product') {
            $product = $request->request->all();
            unset($product['price']);
            $product['requiresAppointment'] = false;
            $product['event'] = $variables['event']['@id'];
            $product['type'] = 'ticket';
            $product['sourceOrganization'] = $variables['organization']['@id'];
            $product = $commonGroundService->saveResource($product, ['component' => 'pdc', 'type' => 'products']);

            $offer = [];
            $offer['price'] = (string) ((float) $request->get('price') * 100);
            $offer['name'] = $product['name'];
            $offer['description'] = $product['description'];
            $offer['products'] = ['/products/'.$product['id']];
            $offer['offeredBy'] = $variables['organization']['@id'];
            $offer['audience'] = 'public';

            $product['offers'][] = $commonGroundService->saveResource($offer, ['component' => 'pdc', 'type' => 'offers']);

            $variables['products'][] = $product;
        }

//        $variables['categories'] = [];
//        foreach ($commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'] as $category) {
//            $variables['categories'][] = $category['id'];
//        }

        return $variables;
    }

    /**
     * @Route("/event/{id}/tickets")
     * @Template
     */
    public function eventTicketsAction(CommonGroundService $commonGroundService, Request $request, SerializerInterface $serializer, $id)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id], ['organization' => $variables['organization']['@id']]);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'];

        $variables['ticket'] = $commonGroundService->getResource(['component' => 'pdc', 'type' => 'offers'], ['products.event' => $variables['event']['@id'], 'products.type' => 'ticket'])['hydra:member'];
        if (count($variables['ticket']) > 0) {
            $variables['ticket'] = $variables['ticket'][0];
        }
        $variables['orders'] = $commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['organization' => $variables['organization']['@id']])['hydra:member'];

        //downloads tickets
        if ($request->query->has('action') && $request->query->get('action') == 'download') {
            $results = [];
            $responseData = $serializer->serialize(
                $results,
                'csv'
            );

            return new Response($responseData, Response::HTTP_OK, ['content-type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=tickets.csv']);
        }

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
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);

        return $variables;
    }

    /**
     * @Route("/products")
     * @Template
     */
    public function productsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['sourceOrganization' => $variables['organization']['@id']])['hydra:member'];
        $variables['offers'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['id']])['hydra:member'];
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'])['hydra:member'];

        if ($request->isMethod('POST')) {
            // Get the current resource
            $product = $request->request->all();
            // Set the current organization as owner
            $product['requiresAppointment'] = false;
            $product['sourceOrganization'] = $variables['organization']['@id'];
            // Save the resource
            $product = $commonGroundService->saveResource($product, ['component' => 'pdc', 'type' => 'products']);

            // redirects externally
            if ($product['id']) {
                return $this->redirectToRoute('app_dashboardorganization_editproduct', ['id' => $product['id']]);
            }
        }

        return $variables;
    }

    /**
     * @Route("/products/{id}")
     * @Template
     */
    public function editProductAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService, ParameterBagInterface $params, $id)
    {
        if ($id != 'add') {
            $variables['product'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products', 'id' => $id]);
        } else {
            $variables['product'] = [];
        }

        if ($request->get('action') == 'delete') {
            $commonGroundService->deleteResource($variables['product']);

            return $this->redirectToRoute('app_dashboardorganization_products');
        }

        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['offers'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'])['hydra:member'];

        $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];
        $groups = $idVaultService->getGroups($provider['configuration']['app_id'], $variables['organization']['@id'])['groups'];
        $variables['groups'] = array_filter($groups, function ($group) {
            return $group['name'] != 'root' && $group['name'] != 'clients';
        });

        if ($request->isMethod('POST') && $request->request->get('@type') == 'Product') {
            // Get the current resource
            $product = array_merge($variables['product'], $request->request->all());
            $product['sourceOrganization'] = $variables['organization']['@id'];
            // Remove offers (wont do any harm with an updateResource)
            unset($product['offers']);
            // Option for cascade updating offers without unset product.offers ^:
//            // Symfony doesn't like it if we do a cascade put with id's (update product with product.offers with id's)
//            foreach ($product['offers'] as $key => &$offer) {
//                if (isset($offer['id'])){
//                    unset($product['offers'][$key]['id']);
//                }
//                // ...and prices should be a string
//                if (isset($offer['price'])){
//                    $offer['price'] = (string)$offer['price'];
//                }
//                // Still need to make sure none of the offers have a variable that is null
//            }
            // Save the resource
            $variables['product'] = $commonGroundService->updateResource($product, ['component' => 'pdc', 'type' => 'products', 'id' => $id]);
        }

        if ($request->isMethod('POST') && $request->request->get('@type') == 'Offer') {
            $offer = $request->request->all();
            // Add the current product to het offer
            $offer['products'] = ['/products/'.$id];
            $offer['offeredBy'] = $variables['organization']['@id'];
            $offer['price'] = (string) ((float) $offer['price'] * 100);
            if (isset($offer['options'])) {
                foreach ($offer['options'] as &$option) {
                    $option['price'] = (string) ((float) $option['price'] * 100);
                }
            }

            if (!array_key_exists('audience', $offer) || !$offer['audience']) {
                $offer['audience'] = 'audience';
            }

            if (!array_key_exists('offers', $variables['product'])) {
                $variables['product']['offers'] = [];
            }
            $commonGroundService->saveResource($offer, ['component' => 'pdc', 'type' => 'offers']);

            return $this->redirectToRoute('app_dashboardorganization_editproduct', ['id' => $id]);
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
        } elseif (count($variables['groups']) == 1) {
            $idVaultService->createGroup($provider['configuration']['app_id'], 'clients', "Clients group for {$variables['organization']['name']}", $organizationUrl);
            $idVaultService->createGroup($provider['configuration']['app_id'], 'members', "Members group for {$variables['organization']['name']}", $organizationUrl);
            $idVaultService->createGroup($provider['configuration']['app_id'], 'administrators', "Administrators group for {$variables['organization']['name']}", $organizationUrl);
            $variables['groups'] = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl)['groups'];
        }

        $users = [];
        foreach ($variables['groups'] as $group) {
            foreach ($group['users'] as $user) {
                if (in_array($user['username'], $users)) {
                    $users[$user['username']]['groups'][] = $group['name'];
                } else {
                    $users[$user['username']]['name'] = $user['username'];
                    $users[$user['username']]['groups'][] = $group['name'];
                }
            }
        }
        $variables['users'] = $users;

        if ($request->isMethod('POST') && $request->get('newGroup')) {
            $result = $idVaultService->createGroup($provider['configuration']['app_id'], $request->get('name'), $request->get('description'), $organizationUrl);
            if (isset($result['id'])) {
                $this->addFlash('success', 'Groep is aangemaakt');
            } else {
                $this->addFlash('error', 'Er is een fout opgetreden');
            }

            return $this->redirect($this->generateUrl('app_dashboardorganization_members'));
        } elseif ($request->isMethod('POST') && $request->get('inviteUser')) {
            $email = $request->get('email');
            $selectedGroup = $request->get('group');

            foreach ($variables['groups'] as $group) {
                if ($group['name'] == 'root' && !in_array($email, array_column($group['users'], 'username'))) {
                    $idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $email, true);
                }
                if ($group['id'] == $selectedGroup && !in_array($email, array_column($group['users'], 'username'))) {
                    $idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $email, true);
                    $this->addFlash('success', 'gebruiker is toegevoegd aan groep');
                } elseif ($group['id'] == $selectedGroup && in_array($email, array_column($group['users'], 'username')) && $group['name'] !== 'root') {
                    $this->addFlash('error', 'Gebruiker zit al in de gekozen groep');
                }
            }

            return $this->redirect($this->generateUrl('app_dashboardorganization_members'));
        }

        return $variables;
    }

    /**
     * @Route("/mailinglists")
     * @Template
     */
    public function mailinglistsAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService, ParameterBagInterface $params)
    {
        // Make sure the user is logged in
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault'));
        }

        // Get the organization
        $organizationUrl = $this->getUser()->getOrganization();
        $variables['organization'] = $commonGroundService->getResource($organizationUrl);

        // Get clientSecret of larping application
        $providers = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'];
        $clientSecret = $providers[0]['configuration']['secret'];
        $clientId = $providers[0]['configuration']['app_id'];

        // Get mailingLists from id-vault with filters: larping application secret and this users organization url.
        $variables['mailingLists'] = $idVaultService->getSendLists($clientSecret, $organizationUrl);

        // Get UserGroups from id-vault with filters: larping application id and this users organization url.
        $variables['groups'] = $idVaultService->getGroups($clientId, $organizationUrl)['groups'];

        // Get for all mailinglist the subscribers that have a resource set that is an wac/group with id of an group in $variables['group']
        foreach ($variables['mailingLists'] as &$mailingList) {
            $groups = [];
            foreach ($mailingList['subscribers'] as $subscriber) {
                if (isset($subscriber['resource'])) {
                    if (strpos($subscriber['resource'], '/wac/groups/')) {
                        foreach ($variables['groups'] as $group) {
                            if (strpos($subscriber['resource'], $group['id'])) {
                                array_push($groups, $group);
                            }
                        }
                    }
                }
            }
            $mailingList['groups'] = $groups;
        }

        if ($request->isMethod('POST') && $request->request->get('DeleteList') == 'true') {
            // Get the correct sendList to delete
            $sendListId = $request->get('id');

            // Delete the sendList
            $idVaultService->deleteSendList($sendListId);

            return $this->redirect($this->generateUrl('app_dashboardorganization_mailinglists'));
        } elseif ($request->isMethod('POST') && $request->request->get('MailToList') == 'true') {
            // Get the correct sendList to send this mail to
            $sendListId = $request->get('id');

            // Setup the mail to be send
            $mail = [];
            $mail['title'] = $request->get('title');
            $mail['html'] = $request->get('html');
            $mail['sender'] = preg_replace('/\s+/', '', $variables['organization']['name']).'@larping.eu';

            // Send email to all subscribers of this mailing list.
            $idVaultService->sendToSendList($sendListId, $mail);

            return $this->redirect($this->generateUrl('app_dashboardorganization_mailinglists'));
        } elseif ($request->isMethod('POST')) {
            // Get the resource
            $sendList = $request->request->all();
            // Set Organization and email sendList type
            $sendList['resource'] = $organizationUrl;
            $sendList['email'] = true;

            // Save the mailing list resource on id-vault
            $idVaultService->saveSendList($clientSecret, $sendList);

            return $this->redirect($this->generateUrl('app_dashboardorganization_mailinglists'));
        }

        return $variables;
    }

    /**
     * @Route("/reviews")
     * @Template
     */
    public function reviewsAction(CommonGroundService $commonGroundService, Request $request)
    {
        // If we are showing reviews of a specific resource, do so:
        if ($request->get('resource')) {
            $variables['resource'] = $commonGroundService->getResource($request->get('resource'));
            $reviews = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['resource' => $request->get('resource'), 'order[dateCreated]' => 'desc'])['hydra:member'];
        }
        // If we are not showing reviews of a specific resource or the given resource has no reviews:
        if (!isset($reviews) || (isset($reviews) && count($reviews) == 0)) {
            $organizationUrl = $this->getUser()->getOrganization();

            // Get all reviews for this organization
            $reviews = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['organization' => $organizationUrl, 'order[dateCreated]' => 'desc'])['hydra:member'];

            // Get all unique reviewed resources
            $reviewedResources = array_unique(array_column($reviews, 'resource'));

            // Make sure these resources do actually exist
            // maybe just still show the reviews but with a warning that the resource no longer exists? and a option to delete these reviews.
            foreach ($reviewedResources as $key => $reviewedResource) {
                if (!$commonGroundService->isResource($reviewedResource)) {
                    $reviews = array_filter($reviews, function ($review) use ($reviewedResource) {
                        return $review['resource'] != $reviewedResource;
                    });
                    unset($reviewedResources[$key]);
                }
            }

            // Check if more than 1 resources has been reviewed for this organization (can include the organization resource itself)
            if (count($reviewedResources) > 1) {
                // Show the resources and info about their total reviews with links to specific pages.
                foreach ($reviewedResources as $key => &$reviewedResource) {
                    // Check if the organization (resource!) has been reviewed.
                    if ($reviewedResource == $organizationUrl) {
                        $variables['organization'] = $commonGroundService->getResource($organizationUrl);
                        // Get totals for this organization (resource!)
                        $variables['organization']['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['resource' => $organizationUrl]);
                        unset($reviewedResources[$key]);
                    } else {
                        $reviewedResource = $commonGroundService->getResource($reviewedResource);
                        $reviewedResource['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['resource' => $reviewedResource['@id']]);
                    }
                }
                $variables['reviewedResources'] = $reviewedResources;
            } elseif ($reviewedResources == 1) {
                $variables['resource'] = $commonGroundService->getResource($reviewedResources[0]);
            }
            // If only 1 resource has been reviewed for this organization, then all reviews for this organization are on that resource... and in $reviews
        }

        $variables['reviews'] = $reviews;

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

        return $variables;
    }

    /**
     * @Route("/locations/{id}")
     * @Template
     */
    public function locationAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService, ParameterBagInterface $params, $id)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'features'])['hydra:member'];
        $variables['activeCategories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'];
        $variables['activeCategories'] = array_column($variables['activeCategories'], 'id');

        if ($id != 'add') {
            $variables['location'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places', 'id' => $id]);
        } else {
            $variables['location'] = [];
        }

        if ($request->isMethod('POST')) {
            $location = $request->request->all();
            $location['organization'] = $variables['organization']['@id'];

            // Setting the categories
            /*@todo  This should go to a wrc service */
            if (isset($location['categories'])) {
                $categories = $location['categories'];
                unset($location['categories']);
            }
            // Lets save the address
            if (isset($location['address'])) {
                $contact = $location['address'];
                $contact['name'] = $location['name'];
                $contact['description'] = $location['description'];
                $contact = $commonGroundService->saveResource($contact, ['component' => 'lc', 'type' => 'addresses']);
                $location['address'] = '/addresses/'.$contact['id'];
            }

            // Lets save the location
            $variables['location'] = $commonGroundService->saveResource($location, ['component' => 'lc', 'type' => 'places']);

            if (isset($categories)) {
                /*@todo  This should go to a wrc service */
                $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource' => $variables['location']['id']])['hydra:member'];
                if (count($resourceCategories) > 0) {
                    $resourceCategory = $resourceCategories[0];
                } else {
                    $resourceCategory = ['resource' => $variables['location']['@id'], 'catagories' => []];
                }

                $resourceCategory['categories'] = $categories;

                $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
            }

            return $this->redirectToRoute('app_dashboardorganization_location', ['id' => $variables['location']['id']]);
        }

        return $variables;
    }

    /**
     * @Route("/edit/{id}")
     * @Template
     */
    public function editAction(CommonGroundService $commonGroundService, Request $request, ParameterBagInterface $params, IdVaultService $idVaultService, $id)
    {
        if ($id != 'add') {
            $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);
        } else {
            $variables['organization'] = ['id' => 'add', '@type' => 'Organization'];
        }

        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'settings'])['hydra:member'];
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $organization = $request->request->all();

            // New org switch
            if ($id == 'add') {
                $new = true;
            } else {
                $new = false;
            }

            if ($organization['template']) {
                unset($organization['template']);
            }

            // Let set some aditional data
            $organization['rsin'] = '';
            $organization['chamberOfComerce'] = '';

            // lets clear up the non used example forms
            unset($organization['contact']['addresses']['uuid']);
            unset($organization['contact']['socials']['uuid']);
            unset($organization['contact']['emails']['uuid']);
            unset($organization['contact']['telephones']['uuid']);

            // Setting the categories
            /*@todo  This should go to a wrc service */
            if (array_key_exists('categories', $organization)) {
                $categories = $organization['categories'];
                unset($organization['categories']);
            } else {
                $categories = [];
            }

            // removing UUID's from contact data
            $subobjects = ['emails' => [], 'adresses' => [], 'telephones' => [], 'socials' => []];
            foreach ($subobjects as $subobject => $subobjectArray) {
                // let see if we have values
                if (array_key_exists($subobject, $organization['contact'])) {
                    // transefer the vlaues to holder array without the uuuid as an index
                    foreach ($organization['contact'][$subobject] as $key => $tocopy) {
                        if ($key != 'uuid') {
                            $subobjects[$subobject][] = $tocopy;
                        }
                    }
                }
                // replace the values in the original array
                $organization['contact'][$subobject] = $subobjects[$subobject];
            }

            if (array_key_exists('contact', $organization)) {
                $contact = $organization['contact'];
                unset($organization['contact']);
            } else {
                $contact = [];
            }
            $contact['name'] = $organization['name'];
            $contact['description'] = $organization['description'];

            $organization = $commonGroundService->saveResource($organization, ['component' => 'wrc', 'type' => 'organizations']);

            // Lets save the contact
            $contact['sourceOrganization'] = $organization['@id'];
            $contact = $commonGroundService->saveResource($contact, ['component' => 'cc', 'type' => 'organizations']);
            // If the current contact is difrend then the one saved in the organisation we need to save that
            if ($organization['contact'] != $contact['@id']) {
                $organization['contact'] = $contact['@id'];
                $organization = $commonGroundService->saveResource($organization, ['component' => 'wrc', 'type' => 'organizations']);
            }

            // Lets save te organization
            if ($new) {
                $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization['id']]);
                $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];

                $idVaultService->createGroup($provider['configuration']['app_id'], 'root', "Root group for {$organization['name']}", $organizationUrl);
                $result = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl);
                $idVaultService->inviteUser($provider['configuration']['app_id'], $result['groups'][0]['id'], $this->getUser()->getUsername(), true);

                //create the groups clients, members, administrators
                $idVaultService->createGroup($provider['configuration']['app_id'], 'clients', "Clients group for {$organization['name']}", $organizationUrl);
                $idVaultService->createGroup($provider['configuration']['app_id'], 'members', "Members group for {$organization['name']}", $organizationUrl);
                $idVaultService->createGroup($provider['configuration']['app_id'], 'administrators', "Administrators group for {$organization['name']}", $organizationUrl);
            }

            // Setting the categories

            /*@todo  This should go to a wrc service */
            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource' => $organization['id']])['hydra:member'];
            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource' => $organization['@id'], 'catagories' => []];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);

            $template = $request->get('template');

            if (isset($variables['organization']['termsAndConditions'])) {
                $template['@id'] = $variables['organization']['termsAndConditions']['@id'];
            }

            if ($new) {
                $template['name'] = 'Terms and conditions for '.$organization['name'];
                $template['templateEngine'] = 'twig';
                $template['organization'] = '/organizations/'.$organization['id'];

                $template = $commonGroundService->saveResource($template, ['component' => 'wrc', 'type' => 'templates']);

                $organization['termsAndConditions'] = '/templates/'.$template['id'];
                $organization = $commonGroundService->saveResource($organization, ['component' => 'wrc', 'type' => 'organizations']);
            }

            return $this->redirectToRoute('app_dashboardorganization_edit', ['id' => $organization['id']]);
        }

        $variables['categories'] = [];
        foreach ($commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'] as $category) {
            $variables['categories'][] = $category['id'];
        }

        return $variables;
    }

    /**
     * @Route("/make-order-for-member")
     * @Template
     */
    public function makeOrderForMemberAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());

        return $variables;
    }

    /**
     * @Route("/payment-providers")
     * @Template
     */
    public function paymentProvidersAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());

//        $variables['providers'] = $commonGroundService->getResourceList(['component' => 'bc', 'type' => 'services'], ['organization' => $variables['organization']['id']])['hydra:member'];
        $variables['providers'] = $commonGroundService->getResourceList(['component' => 'bc', 'type' => 'services'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/payment-providers/{id}")
     * @Template
     */
    public function paymentProviderAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());

        if ($id != 'add') {
            $variables['provider'] = $commonGroundService->getResource(['component' => 'bc', 'type' => 'services', 'id' => $id]);
        } else {
            $variables['provider'] = [];
        }

        // Update provider
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Provider') {
            // Get the current resource
            $provider = $request->request->all();
            // Set the current organization as owner
            $provider['organization'] = $variables['organization']['@id'];

            // Save the resource
            $provider = $commonGroundService->saveResource($provider, ['component' => 'bc', 'type' => 'services']);

            return $this->redirectToRoute('app_dashboardorganization_event', ['id' => $provider['id']]);
        }

        return $variables;
    }
}
