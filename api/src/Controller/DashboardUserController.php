<?php

// src/Controller/DefaultController.php

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
    public function membershipsAction(Session $session, Request $request, CommonGroundService $commonGroundService, MailingService $mailingService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
    }

    /**
     * @Route("/organizations")
     * @Template
     */
    public function organizationsAction(Session $session, Request $request, CommonGroundService $commonGroundService, MailingService $mailingService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
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

            $categories = $resource['categories'];
            if (!$categories) {
                $categories = [];
            }
            unset($resource['categories']);

            $organization = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

            $resourceCategories = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'resource_categories'], ['resource'=>$organization['id']])['hydra:member'];

            if (count($categories) > 0) {
                $resourceCategory = $resourceCategories[0];
            } else {
                $resourceCategory = ['resource'=>$organization['@id'], 'catagories'=>[]];
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
    }



    /**
     * @Route("/events")
     * @Template
     */
    public function eventsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $variables['events'] = $commonGroundService->getResourceList(['component'=>'arc', 'type'=>'events'])['hydra:member'];

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
        $variables['likes'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['author' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }
}
