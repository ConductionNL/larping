<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use App\Service\ShoppingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The DashboardController handles any calls about administration and dashboard pages.
 *
 * Class DashboardController
 *
 * @Route("/dashboard/user")
 */
class DashboardUserController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, ShoppingService $shoppingService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        $variables['events'] = $shoppingService->getOwnedProducts($this->getUser()->getPerson());
        $variables['characters'] = []; // $commonGroundService->getResourceList(['component'=>'arc', 'type'=>'events'])['hydra:member'];
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'people'])['hydra:member'];
        $variables['likes'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['author' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/memberships")
     * @Template
     */
    public function membershipsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ShoppingService $shoppingService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $products = $shoppingService->getOwnedProducts($this->getUser()->getPerson());
        $groups = $this->getUser()->getGroups();

        $rootGroupIds = [];

        if (count($products) > 0) {
            foreach ($products as &$product) {
                $product['groups'] = [];
                if ($product['type'] == 'subscription') {
                    foreach ($groups as $group) {
                        if ($product['sourceOrganization'] == $group['organization'] && $group['name'] !== 'root') {
                            $product['groups'][] = $group['name'];
                            $product['joined'] = $group['dateJoined'];
                        } elseif ($product['sourceOrganization'] == $group['organization'] && $group['name'] == 'root' &&
                            !in_array($group['id'], $rootGroupIds)) {
                            $variables['rootGroups'][] = $group;
                            $rootGroupIds[] = $group['id'];
                        }
                    }
                    $variables['products'][] = $product;
                }
            }
        }

        return $variables;
    }

    /**
     * @Route("/organizations")
     * @Template
     */
    public function organizationsAction(Session $session, Request $request, CommonGroundService $commonGroundService, MailingService $mailingService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, IdVaultService $idVaultService, TranslatorInterface $translator)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
//        $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id')]);
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['rsin'] = '';
            $resource['chamberOfComerce'] = '';
            // @todo person toevoegen
            $person = $commonGroundService->getResource($this->getUser()->getPerson());
//            $resource['persons'][] = $person;

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

            // Als de organisatie nieuw is moeten we wat meer doen
            /*
            $new = false;
            if(!array_key_exists('@id',$resource) || !$resource['@id'] ){
                $new = true;
                // Contact aanmaken
                $resource['sourceOrganization'] = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $application['organization']['id']]);
                $contact = $commonGroundService->saveResource($resource, ['component' => 'cc', 'type' => 'organizations']);
                $resource['contact'] = $contact['@id'];

            }
            */
            // Update to the commonground component

            if (isset($resource['categories'])) {
                $categories = $resource['categories'];
            }
            if (!isset($categories)) {
                $categories = [];
            }
            unset($resource['categories']);

            $organization = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);
            $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization['id']]);
            $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];

            $idVaultService->createGroup($provider['configuration']['app_id'], 'administrators', "{$translator->trans('Administrators group for')} {$organization['name']}", $organizationUrl);
            $result = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl);
            $idVaultService->inviteUser($provider['configuration']['app_id'], $result['groups'][0]['id'], $this->getUser()->getUsername(), true);
            $idVaultService->createGroup($provider['configuration']['app_id'], 'members', "{$translator->trans('Members group for')} {$organization['name']}", $organizationUrl);

            // TODO: put this back? removed for demo
//            $idVaultService->createGroup($provider['configuration']['app_id'], 'root', "Root group for {$organization['name']}", $organizationUrl);
//            $result = $idVaultService->getGroups($provider['configuration']['app_id'], $organizationUrl);
//            $idVaultService->inviteUser($provider['configuration']['app_id'], $result['groups'][0]['id'], $this->getUser()->getUsername(), true);
//
//            //create the groups clients, members, administrators
//            $idVaultService->createGroup($provider['configuration']['app_id'], 'clients', "Clients group for {$organization['name']}", $organizationUrl);
//            $idVaultService->createGroup($provider['configuration']['app_id'], 'members', "Members group for {$organization['name']}", $organizationUrl);
//            $idVaultService->createGroup($provider['configuration']['app_id'], 'administrators', "Administrators group for {$organization['name']}", $organizationUrl);

            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource' => $organization['id']])['hydra:member'];

            if (count($categories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource' => $organization['@id'], 'catagories' => []];
            }

            $resourceCategory['categories'] = $categories;
            $resourceCategory['catagories'] = $categories;

            $resourceCategory = $commonGroundService->saveResource($resourceCategory, ['component' => 'wrc', 'type' => 'resource_categories']);

//            if($new){
//                /*@todo de ingelogde gebruiker toevoegen aan de organisatie */
//                $group = ['organization'=>$organization['@id'],'name'=>'members', 'description'=> $organization['name']];
//                $group = $commonGroundService->saveResource($group, ['component' => 'uc', 'type' => 'groups']);
//
//                // Make an admin group
//                $group = ['organization'=>$organization['@id'],'name'=>'admin','parent'=>'/groups/'.$group['id'], 'description'=> $organization['name']];
//                $group = $commonGroundService->saveResource($group, ['component' => 'uc', 'type' => 'groups']);
//
//               // Ad the current user to the admin group
//                $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()])['hydra:member'];
//
//            }
        }
//        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];

        $groups = $this->getUser()->getGroups();
        $variables['organizations'] = [];
        $organizationIds = [];

        if (isset($groups) && is_array($groups)) {
            foreach ($groups as $group) {
                if (($group['name'] == 'administrators' || $group['name'] == 'root') && !in_array($group['organization'], $organizationIds)) {
                    $variables['organizations'][] = $commonGroundService->getResource($group['organization']);
                    $organizationIds[] = $group['organization'];
                }
            }
        }

        return $variables;
    }

    /**
     * @Route("/addorganization")
     * @Template
     */
    public function addorganizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, MailingService $mailingService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        if ($request->isMethod('POST')) {
            //get all the fields from the form
            $name = $request->get('name');
            $description = $request->get('description');
            $socials = $request->get('socials');

            //make wrc org
            $wrc = [];
            $wrc['rsin'] = '';
            $wrc['chamberOfComerce'] = '';
            $wrc['name'] = $name;
            $wrc['description'] = $description;
            $wrcOrganization = $commonGroundService->saveResource($wrc, ['component' => 'wrc', 'type' => 'organizations']);
            $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $wrcOrganization['id']]);

            //make cc org
            $cc = [];
            $cc['name'] = $name;
            $cc['description'] = $name;
            $cc['sourceOrganization'] = $organizationUrl;

            //make address
            $address['name'] = 'address of '.$name;
            $address = array_merge($address, $request->get('addresses'));
            $address = $commonGroundService->saveResource($address, ['component' => 'cc', 'type' => 'addresses']);
            $cc['address'] = '/addresses/'.$address['id'];

            //make email
            $emails['name'] = 'email of '.$name;
            $emails = array_merge($emails, $request->get('emails'));
            $emails = $commonGroundService->saveResource($emails, ['component' => 'cc', 'type' => 'emails']);
            $cc['email'] = '/emails/'.$emails['id'];

            //make telephone
            $telephones['name'] = 'telephone of '.$name;
            $telephones = array_merge($telephones, $request->get('telephones'));
            $telephones = $commonGroundService->saveResource($telephones, ['component' => 'cc', 'type' => 'telephones']);
            $cc['telephones'] = '/telephones/'.$telephones['id'];

            //save organization and set as wrc contact
            $ccOrganization = $commonGroundService->saveResource($cc, ['component' => 'cc', 'type' => 'organizations']);
            $organizationUrl = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $ccOrganization['id']]);
            $wrcOrganization['contact'] = $organizationUrl;
            $commonGroundService->saveResource($wrcOrganization, ['component' => 'wrc', 'type' => 'organizations']);
        }

        return $variables;
    }

    /**
     * @Route("/events")
     * @Template
     */
    public function eventsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/characters")
     * @Template
     */
    public function charactersAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $variables['characters'] = []; //commonGroundService->getResourceList(['component'=>'arc', 'type'=>'events'])['hydra:member'];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'], ['persons' => $this->getUser()->getPerson()])['hydra:member'];

        if ($request->isMethod('POST')) {
            $character = $request->request->all();
        }

        return $variables;
    }

    /**
     * @Route("/favorites")
     * @Template
     */
    public function favoritesAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $variables['likes'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['author' => $this->getUser()->getPerson(), 'order[dateCreated]' => 'desc'])['hydra:member'];
        foreach ($variables['likes'] as &$like) {
            if ($commonGroundService->isResource($like['resource'])) {
                $like['resource'] = $commonGroundService->getResource($like['resource']);
            }
        }
        //don't display page if there aren't any likes from the user
        if (!$variables['likes'] > 0) {
            return $this->redirect($this->generateUrl('app_default_index'));
        }

        return $variables;
    }

    /**
     * @Route("/orders")
     * @Template
     */
    public function ordersAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['orders'] = $commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['customer' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/orders/{id}")
     * @Template
     */
    public function orderAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['order'] = $commonGroundService->getResource(['component' => 'orc', 'type' => 'orders', 'id' => $id]);

        if ($this->getUser()->getPerson() != $variables['order']['customer']) {
            return $this->redirectToRoute('app_default_index');
        }

        return $variables;
    }

    /**
     * @Route("/dowload-invoice")
     * @Template
     */
    public function downloadInvoiceAction(Session $session, Request $request, CommonGroundService $commonGroundService)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($request->isMethod('POST')) {
            $redirectUrl = $request->get('redirectUrl');
            $order = $commonGroundService->getResource($request->get('order'));

            $customer = $commonGroundService->getResource($order['customer']);
            $organization = $commonGroundService->getResource($order['organization']);
            $invoice = $commonGroundService->getResource($order['invoice']);

            // The pdf
            $mpdf = new \Mpdf\Mpdf();

            $dateCreated = new \DateTime($invoice['dateCreated']);

            $data = '';
            $data .= '<h1>Order for '.$customer['name'].'</h1>';
            $data .= '<h3>Ordered at '.$organization['name'].'</h3>';
            $data .= '<span>'.$dateCreated->format('d-m-Y H:i').'</span>';
            $data .= '<div style="height:30px"></div>';

            if (isset($order['items'])) {
                $data .= '<h3>Items</h3>';
                $data .= '<table>';
                $data .= '<thead><tr><th>Name</th><th>Quantity</th><th>Price</th></tr></thead>';
                $data .= '<tbody>';
                foreach ($order['items'] as $item) {
                    $data .= '<tr><td>'.$item['name'].'</td><td style="text-align: center">'.$item['quantity'].'</td><td>'.$item['priceCurrency'].' '.$item['price'].',-</td></tr>';
                }
                $data .= '<tr><td></td><td></td><td><hr></td></tr>';
                $data .= '<tr><td></td><td></td><td><b>'.$order['priceCurrency'].' '.$order['price'].',-</b></td></tr>';
                $data .= '</tbody>';
                $data .= '</table>';
            }
            $mpdf->WriteHtml($data);
            $mpdf->Output('invoice.pdf', 'D');

            if ($redirectUrl) {
                return $this->redirect($redirectUrl);
            } else {
                return $this->redirectToRoute('app_dashboarduser_orders');
            }
        }

        return $this->redirectToRoute('app_dashboarduser_orders');
    }
}
