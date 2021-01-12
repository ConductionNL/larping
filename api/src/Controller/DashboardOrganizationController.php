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
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id], ['organization' => $variables['organization']['@id']]);

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

        // Get mailingLists from id-vault with filters: larping application secret and this users organization url
        $variables['mailingLists'] = $idVaultService->getSendLists($clientSecret, $organizationUrl);

        if ($request->isMethod('POST') && $request->request->get('MailToList') == 'true') {
            // Get the correct sendList to send this mail to
            $sendListId = $request->get('id');

            // Setup the mail to be send
            $mail = [];
            $mail['title'] = $request->get('title');
            $mail['html'] = '<p>HTML content of the mail</p>';//$request->get('html');
            $mail['sender'] = preg_replace('/\s+/', '', $variables['organization']['name']).'@larping.eu';

        // Send email to all subscribers of this mailing list.
            $idVaultService->sendToSendList($sendListId, $mail);
        } elseif ($request->isMethod('POST')) {
            // Get the resource
            $sendList = $request->request->all();
            // Set Organization and email sendList type
            $sendList['resource'] = $organizationUrl;
            $sendList['email'] = true;

            // Save the mailing list resource on id-vault
            $idVaultService->createSendList($clientSecret, $sendList);

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
                $resourceCategory = ['resource'=>$event['@id'], 'catagories'=>[]];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
        }

        return $variables;
    }

    /**
     * @Route("/edit_organization")
     * @Template
     */
    public function editOrganizationAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['organization'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['rsin'] = '';
            $resource['chamberOfComerce'] = '';
            $person = $commonGroundService->getResource($this->getUser()->getPerson());
            $categories = $resource['categories'];

            $email = [];
            $email['name'] = 'email for '.$person['name'];
            $email['email'] = $request->get('email');
            if (isset($email['id'])) {
                $commonGroundService->saveResource($email, ['component' => 'cc', 'type' => 'emails']);
                $resource['emails'][] = '/emails/'.$email['id'];
            } elseif (isset($email['email'])) {
                $resource['emails'][] = $email;
            }

            $telephone = [];
            $telephone['name'] = 'telephone for '.$person['name'];
            $telephone['telephone'] = $request->get('telephone');
            if (isset($telephone['id'])) {
                $commonGroundService->saveResource($telephone, ['component' => 'cc', 'type' => 'telephones']);
                $resource['telephones'][] = '/telephones/'.$telephone['id'];
            } elseif (isset($telephone['telephone'])) {
                $resource['telephones'][] = $telephone;
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
                $resource['adresses'][] = '/addresses/'.$address['id'];
            } else {
                $resource['adresses'][] = $address;
            }

            $socials = [];
            $socials['name'] = $request->get('type').' of '.$person['name'];
            $socials['description'] = $request->get('type').' of '.$person['name'];
            $socials['type'] = $request->get('type');
            $socials['url'] = $request->get('url');
            if (isset($twitter['id'])) {
                $commonGroundService->saveResource($socials, ['component' => 'cc', 'type' => 'socials']);
                $resource['socials'][] = '/socials/'.$socials['id'];
            } else {
                $resource['socials'][] = $socials;
            }

//            $facebook = [];
//            $facebook['name'] = 'facebook of '.$person['name'];
//            $facebook['description'] = 'facebook of '. $person['name'];
//            $facebook['type'] = 'facebook';
//            $facebook['url'] = $request->get('facebook');
//            if (isset($facebook['id'])) {
//                $commonGroundService->saveResource($facebook, ['component' => 'cc', 'type' => 'socials']);
//                $resource['socials'][] = '/socials/'.$facebook['id'];
//            } else {
//                $resource['socials'][] = $facebook;
//            }
//
//            $instagram = [];
//            $instagram['name'] = 'instagram of '.$person['name'];
//            $instagram['description'] = 'instagram of '. $person['name'];
//            $instagram['type'] = 'instagram';
//            $instagram['url'] = $request->get('instagram');
//            if (isset($instagram['id'])) {
//                $commonGroundService->saveResource($instagram, ['component' => 'cc', 'type' => 'socials']);
//                $resource['socials'][] = '/socials/'.$instagram['id'];
//            } else {
//                $resource['socials'][] = $instagram;
//            }
//
//            $linkedin = [];
//            $linkedin['name'] = 'Linkedin of '.$person['name'];
//            $linkedin['description'] = 'Linkedin of '. $person['name'];
//            $linkedin['type'] = 'linkedin';
//            $linkedin['url'] = $request->get('linkedin');
//            if (isset($linkedin['id'])) {
//                $commonGroundService->saveResource($linkedin, ['component' => 'cc', 'type' => 'socials']);
//                $resource['socials'][] = '/socials/'.$linkedin['id'];
//            } else {
//                $resource['socials'][] = $linkedin;
//            }
//
//            $website = [];
//            $website['name'] = 'Website of '.$person['name'];
//            $website['description'] = 'Website of '. $person['name'];
//            $website['type'] = 'website';
//            $website['url'] = $request->get('website');
//            if (isset($website['id'])) {
//                $commonGroundService->saveResource($website, ['component' => 'cc', 'type' => 'socials']);
//                $resource['socials'][] = '/socials/'.$website['id'];
//            } else {
//                $resource['socials'][] = $website;
//            }
//
//            $twitter = [];
//            $twitter['name'] = 'Twitter  of '.$person['name'];
//            $twitter['description'] = 'Twitter  of '. $person['name'];
//            $twitter['type'] = 'twitter ';
//            $twitter['url'] = $request->get('twitter ');
//            if (isset($twitter['id'])) {
//                $commonGroundService->saveResource($twitter, ['component' => 'cc', 'type' => 'socials']);
//                $resource['socials'][] = '/socials/'.$twitter['id'];
//            } else {
//                $resource['socials'][] = $twitter;
//            }

            $resource = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

            // Setting the categories
            /*@todo  This should go to a wrc service */
            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource'=>$resource['id']])['hydra:member'];

            if (count($resourceCategories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource'=>$resource['@id'], 'catagories'=>[]];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);
        }

        return $variables;
    }
}
