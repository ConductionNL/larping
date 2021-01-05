<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Security\User\CommongroundUser;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Security\User\IdVaultUser;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

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
    public function indexAction(CommonGroundService $commonGroundService, IdVaultService $idVaultService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        if ($request->isMethod('POST') && $request->get('organization')) {


            $user = $idVaultService->updateUserOrganization($request->get('organization'), $this->getUser()->getUsername());
            $person = $commonGroundService->getResource($user['person']);
            $userObject = new IdVaultUser($user['username'], $user['username'], $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'id-vault');

            $token = new UsernamePasswordToken($userObject, null, 'main', $userObject->getRoles());
            $this->container->get('security.token_storage')->setToken($token);
            $this->container->get('session')->set('_security_main', serialize($token));
        }

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'people'])['hydra:member'];
        //$variables['likes'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['author' => $this->getUser()->getPerson()])['hydra:member'];
        return $variables;
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
        $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id')]);
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['rsin'] = "";
            $resource['chamberOfComerce'] = "";
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
            $socials['name'] = $request->get('type'). ' of '.$person['name'];
            $socials['description'] = $request->get('type'). ' of '.$person['name'];
            $socials['type'] = $request->get('type');
            $socials['url'] = $request->get('url');
            if (isset($twitter['id'])) {
                $commonGroundService->saveResource($socials, ['component' => 'cc', 'type' => 'socials']);
                $resource['socials'][] = '/socials/'.$socials['id'];
            } else {
                $resource['socials'][] = $socials;
            }

            // Als de organisatie nieuw is moeten we wat meer doen
            $new = false;
            if(!array_key_exists('@id',$resource) || !$resource['@id'] ){
                $new = true;
                // Contact aanmaken
                $resource['sourceOrganization'] = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $application['organization']['id']]);
                $contact = $commonGroundService->saveResource($resource, ['component' => 'cc', 'type' => 'organizations']);
                $resource['contact'] = $contact['@id'];

            }

            // Update to the commonground component
            $organization = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

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
     * @Route("/organization/{id}")
     * @Template
     */
    public function organizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        $variables['item'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);
        if ($variables['item']['contact']) {
            $variables['organization'] = $commonGroundService->getResource($variables['item']['contact']);
        }

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Add the post data to the already aquired resource data
            $resource = array_merge($variables['item'], $resource);

            // Update to the commonground component
            $variables['item'] = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);
            $variables['organization'] = $commonGroundService->saveResource($resource, ['component' => 'cc', 'type' => 'organizations']);


        }

        return $variables;


//            //fill in all required fields for a style
//            $organization['style']['name'] = 'style for'.$request->get('name');
//            $organization['style']['description'] = 'style for'.$request->get('name');
//            $organization['style']['favicon']['name'] = 'style for'.$request->get('name');
//            $organization['style']['favicon']['description'] = 'style for'.$request->get('name');
//            //get profile pic
//            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== 4) {
//                $path = $_FILES['logo']['tmp_name'];
//                $type = filetype($_FILES['logo']['tmp_name']);
//                $data = file_get_contents($path);
//                $organization['style']['favicon']['base64'] = 'data:image/'.$type.';base64,'.base64_encode($data);
//            }
//            $url = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization['id']]);
//            $commonGroundService->saveResource($organization, $url);

//        }
//
//        return $variables;
    }

    /**
     * @Route("/participants")
     * @Template
     */
    public function participantsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/participants/{id}")
     * @Template
     */
    public function participantAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

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
        $variables['events'] = $commonGroundService->getResourceList(['component'=>'arc', 'type'=>'events'])['hydra:member'];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'], ['persons' => $this->getUser()->getPerson()])['hydra:member'];
        $variables['type'] = 'event';


        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Check if org has name (required)
            if ($resource['events']['name']) {
                $resource['events']['priority'] = (int) $resource['events']['priority'];
                //get wrc org of selected cc org
                $wrcOrg = $commonGroundService->getResource($resource['events']['organization']);
                $resource['events']['organization'] = $wrcOrg['sourceOrganization'];
                // Save event
               // $event = $commonGroundService->saveResource($resource['events'], ['component' => 'arc', 'type' => 'events']);
            }

        }

        $variables['items'] = $commonGroundService->getResourceList(['component'=>'arc', 'type'=>'events'])['hydra:member'];


        return $variables;
    }


    /**
     * @Route("/events/{id}")
     * @Template
     */
    public function eventAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $variables['event'] = $commonGroundService->getResource(['component'=>'arc', 'type'=>'events', 'id' => $id]);

        return $variables;
    }
}
