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

        if ($request->isMethod('POST')) {
            // Get the current resource
            $event = $request->request->all();
            // Set the current organization as owner
            $event['organization'] = $variables['organization']['@id'];
            $event['status'] = 'pending';

            // Save the resource
            $event = $commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);

            // redirects externally
            if (array_key_exists('id', $event) && $event['id']) {
                return $this->redirectToRoute('app_dashboardorganization_event', ['id'=> $event['id']]);
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
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());

        if ($id != 'add') {
            $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);
            $variables['products'] = $commonGroundService->getResource(['component' => 'pdc', 'type' => 'products'], ['event' => $variables['event']['id']])['hydra:member'];
        } else {
            $variables['event'] = [];
            $variables['products'] = [];
        }
        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'])['hydra:member'];

        // Update event
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Event') {
            // Get the current resource
            $event = $request->request->all();
            // Set the current organization as owner
            $event['organization'] = $variables['organization']['@id'];
            $event['status'] = 'pending';

            // Only do categories stuff when aplicable
            if (!array_key_exists('categories', $event)) {
                return $this->redirectToRoute('app_dashboardorganization_event', ['id'=> $event['id']]);
            }

            $categories = $event['categories'];
            if (!$categories) {
                $categories = [];
            }
            unset($event['categories']);

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

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);

            return $this->redirectToRoute('app_dashboardorganization_event', ['id'=> $event['id']]);
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
            $offer['price'] = $request->get('price');
            $offer['name'] = $product['name'];
            $offer['description'] = $product['description'];
            $offer['products'] = ['/products/'.$product['id']];
            $offer['offeredBy'] = $variables['organization']['@id'];
            $offer['audience'] = 'public';

            $product['offers'][] = $commonGroundService->saveResource($offer, ['component' => 'pdc', 'type' => 'offers']);

            $variables['products'][] = $product;
        }

        $variables['categories'] = [];
        foreach ($commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $id])['hydra:member'] as $category) {
            $variables['categories'][] = $category['id'];
        }

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
        $variables['offers'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['offeredBy' => $variables['organization']['@id']])['hydra:member'];
        $variables['products'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'products'], ['type' => 'ticket', 'event' => $variables['event']['@id']])['hydra:member'];

        //downloads tickets
        if ($request->query->has('action') && $request->query->get('action') == 'download') {
            $results = $variables['products'];

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
            $product['organization'] = $variables['organization']['@id'];
            $product['sourceOrganization'] = $variables['organization']['@id'];
            // Save the resource
            $product = $commonGroundService->saveResource($product, ['component' => 'pdc', 'type' => 'products']);

            // redirects externally
            if ($product['id']) {
                return $this->redirectToRoute('app_dashboardorganization_editproduct', ['id'=>$product['id']]);
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
        $variables['groups'] = $idVaultService->getGroups($provider['configuration']['app_id'], $variables['organization']['@id'])['groups'];

        if ($request->isMethod('POST') && $request->request->get('@type') == 'Product') {
            // Get the current resource
            //$product = array_merge($variables['product'],$request->request->all()) ;
            $product = $request->request->all();
            // Set the current organization as owner equiresAppointment
            //$product['id'] =  $id;
            //$product['requiresAppointment'] = false;
            //$product['organization'] = $variables['organization']['@id'];
            //$product['sourceOrganization'] = $variables['organization']['@id'];
            // Save the resource
            $variables['product'] = $commonGroundService->updateResource($product, ['component' => 'pdc', 'type' => 'products', 'id' => $id]);
        }

        if ($request->isMethod('POST') && $request->request->get('@type') == 'Offer') {
            $offer = $request->request->all();
            // Add the current product to het offer
            $offer['products'] = ['/products/'.$id];
            $offer['offeredBy'] = $variables['organization']['@id'];

            if (!array_key_exists('audience', $offer) || !$offer['audience']) {
                $offer['audience'] = 'audience';
            }

            if (!array_key_exists('offers', $variables['product'])) {
                $variables['product']['offers'] = [];
            }
            $variables['product']['offers'][] = $commonGroundService->saveResource($offer, ['component' => 'pdc', 'type' => 'offers']);
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
                if (in_array($user, $users)) {
                    $users[$user]['groups'][] = $group['name'];
                } else {
                    $users[$user]['name'] = $user;
                    $users[$user]['groups'][] = $group['name'];
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
                if ($group['name'] == 'root' && !in_array($email, $group['users'])) {
                    $idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $email, true);
                }
                if ($group['id'] == $selectedGroup && !in_array($email, $group['users'])) {
                    $idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $email, true);
                    $this->addFlash('success', 'gebruiker is toegevoegd aan groep');
                } elseif ($group['id'] == $selectedGroup && in_array($email, $group['users']) && $group['name'] !== 'root') {
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
            $location = $request->get('location');
            $address = $request->get('address');
            $categories = $request->get('categories');
            // Set the current organization as owner
            $location['organization'] = $variables['organization']['@id'];

            if (!$categories) {
                $categories = [];
            }

            if (!empty($address)) {
                if (!isset($address['name'])) {
                    $address['name'] = $location['name'];
                }
                $address = $commonGroundService->saveResource($address, ['component' => 'lc', 'type' => 'addresses']);
                $location['address'] = '/addresses/'.$address['id'];
            }
            // Save the resource
            $commonGroundService->saveResource($location, ['component' => 'lc', 'type' => 'places']);

            // Setting the categories
            /*@todo  This should go to a wrc service */
            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource'=>$location['id']])['hydra:member'];

            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource'=>$location['@id'], 'catagories'=>[]];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
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
            $variables['organization'] = ['id'=>'add', '@type'=>'Organization'];
        }

        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'])['hydra:member'];
        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $organization = $request->request->all();
            $organization['rsin'] = '';
            $organization['chamberOfComerce'] = '';
            $person = $commonGroundService->getResource($this->getUser()->getPerson());
//            $categories = $organization['categories'];

            $email = [];
            $email['name'] = 'email for '.$person['name'];
            $email['email'] = $request->get('email');
            if (isset($email['id'])) {
                $commonGroundService->saveResource($email, ['component' => 'cc', 'type' => 'emails']);
                $organization['emails'][] = '/emails/'.$email['id'];
            } elseif (isset($email['email'])) {
                $organization['emails'][] = $email;
            }

            $telephone = [];
            $telephone['name'] = 'telephone for '.$person['name'];
            $telephone['telephone'] = $request->get('telephone');
            if (isset($telephone['id'])) {
                $commonGroundService->saveResource($telephone, ['component' => 'cc', 'type' => 'telephones']);
                $organization['telephones'][] = '/telephones/'.$telephone['id'];
            } elseif (isset($telephone['telephone'])) {
                $organization['telephones'][] = $telephone;
            }

            $address = [];
            $address['name'] = 'address for '.$person['name'];
            $address['street'] = $request->get('street');
            $address['houseNumber'] = $request->get('houseNumber');
            $address['houseNumberSuffix'] = $request->get('houseNumberSuffix');
            $address['postalCode'] = $request->get('postalCode');
            $address['locality'] = $request->get('locality');
            if (isset($address['id'])) {
                $commonGroundService->saveResource($address, ['component' => 'cc', 'type' => 'addresses']);
                $organization['adresses'][] = '/addresses/'.$address['id'];
            } else {
                $organization['adresses'][] = $address;
            }

            $socials = [];
            $socials['name'] = $request->get('type').' of '.$person['name'];
            $socials['description'] = $request->get('type').' of '.$person['name'];
            $socials['type'] = $request->get('type');
            $socials['url'] = $request->get('url');
            if (isset($twitter['id'])) {
                $commonGroundService->saveResource($socials, ['component' => 'cc', 'type' => 'socials']);
                $organization['socials'][] = '/socials/'.$socials['id'];
            } else {
                $organization['socials'][] = $socials;
            }

            // Setting the categories
            /*@todo  This should go to a wrc service */
            if (isset($organization['categories'])) {
                $categories = $organization['categories'];
            }
            if (!isset($categories)) {
                $categories = [];
            }
            unset($organization['categories']);

            $organization = $commonGroundService->saveResource($organization, ['component' => 'wrc', 'type' => 'organizations']);
            $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization['id']]);
            $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];

            $idVaultService->createGroup($provider['configuration']['app_id'], 'root', "Root group for {$organization['name']}", $organizationUrl);
            $result = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl);
            $idVaultService->inviteUser($provider['configuration']['app_id'], $result['groups'][0]['id'], $this->getUser()->getUsername(), true);

            //create the groups clients, members, administrators
            $idVaultService->createGroup($provider['configuration']['app_id'], 'clients', "Clients group for {$organization['name']}", $organizationUrl);
            $idVaultService->createGroup($provider['configuration']['app_id'], 'members', "Members group for {$organization['name']}", $organizationUrl);
            $idVaultService->createGroup($provider['configuration']['app_id'], 'administrators', "Administrators group for {$organization['name']}", $organizationUrl);

            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource' => $organization['id']])['hydra:member'];

            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource' => $organization['@id'], 'catagories' => []];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
            $commonGroundService->saveResource($organization, ['component' => 'wrc', 'type' => 'organizations']);
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
}
