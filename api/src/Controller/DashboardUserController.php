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
 * @Route("/dashboard/user")
 */
class DashboardUserController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'])['hydra:member'];
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
        $variables['items'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'])['hydra:member'];
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['rsin'] = "";
            $resource['chamberOfComerce'] = "";

            // Als de organisatie nieuw is moeten we wat meer doen
            $new = false;
            if(!array_key_exists('@id',$resource) || !$resource['@id'] ){
                $new = true;
                // Contact aanmaken
                $resource['sourceOrganization'] = $params->get('organization');
                $contact = $commonGroundService->saveResource($resource, ['component' => 'cc', 'type' => 'organizations']);
                $resource['contact'] =$contact['@id'];

            }

            // Update to the commonground component
            $organization = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

            if($new){
                /*@todo de ingelogde gebruiker toevoegen aan de organisatie */
                $group = ['organization'=>$organization['@id'],'name'=>'members'];
                $group = $commonGroundService->saveResource($group, ['component' => 'uc', 'type' => 'groups']);

                // Make an admin group
                $group = ['organization'=>$organization['@id'],'name'=>'admin','parent'=>$group['id']];
                $group = $commonGroundService->saveResource($group, ['component' => 'uc', 'type' => 'groups']);

                // Ad the current user to the admin group
                $member = ['user'=>$this->getUser()->getId(),'group'=>$group['id']];
                $member = $commonGroundService->saveResource($member, ['component' => 'uc', 'type' => 'members']);
            }
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
            $rest['name'] = $request->get('name');
            $rest['description'] = $request->get('description');

            $organization = $variables['wrcorganization'];
            $organization['name'] = $rest['name'];
            $organization['description'] = $rest['description'];

            if (!empty($resource['socials'])){
                $resource['socials'] = $request->get('socials');

                if (!empty($resource['socials'][0])) {
                    $resource['socials'][0] = [
                        'name' => 'website van ' . $resource['name'],
                        'type' => 'website',
                        'url' => $resource['socials'][0]
                    ];
                    $commonGroundService->saveResource($resource['socials'][0], ['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
                }

                if (!empty($resource['socials'][1])) {
                    $resource['socials'][1] = [
                        'name' => 'facebook van ' . $rest['name'],
                        'type' => 'facebook',
                        'url' => $resource['socials'][1]
                    ];
                    $commonGroundService->saveResource($resource['socials'][1], ['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
                }

                if (!empty($resource['socials'][2])) {
                    $resource['socials'][2] = [
                        'name' => 'twitter van ' . $rest['name'],
                        'type' => 'twitter',
                        'url' => $resource['socials'][2]
                    ];
                    $commonGroundService->saveResource($resource['socials'][2], ['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
                }

                if (!empty($resource['socials'][3])) {
                    $resource['socials'][3] = [
                        'name' => 'instagram van ' . $rest['name'],
                        'type' => 'instagram',
                        'url' => $resource['socials'][3]
                    ];
                    $commonGroundService->saveResource($resource['socials'][3], ['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
                }
            }
//            $resource['telephones'] = $request->get('telephones');
//            if (!empty($resource['telephones'])){
//                $resource['telephones'] = [
//                        'name' => 'telephone for '.$rest['name'],
//                        'telephone' => $resource['telephones'][0]
//                    ];
//                $commonGroundService->saveResource($resource['telephones'], ['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
//            }
//
//            $resource['emails'] = $request->get('emails');
//            if (!empty($resource['emails'])){
//                    $resource['emails'] = [
//                      'name' => 'email for '.$rest['name'],
//                      'email' => $resource['emails'][0]
//                    ];
//                $commonGroundService->saveResource($resource['emails'], ['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
//            }


            //fill in all required fields for a style
            $organization['style']['name'] = 'style for'.$request->get('name');
            $organization['style']['description'] = 'style for'.$request->get('name');
            $organization['style']['favicon']['name'] = 'style for'.$request->get('name');
            $organization['style']['favicon']['description'] = 'style for'.$request->get('name');
            //get profile pic
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== 4) {
                $path = $_FILES['logo']['tmp_name'];
                $type = filetype($_FILES['logo']['tmp_name']);
                $data = file_get_contents($path);
                $organization['style']['favicon']['base64'] = 'data:image/'.$type.';base64,'.base64_encode($data);
            }
            $url = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization['id']]);
            $commonGroundService->saveResource($organization, $url);

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
//
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

        return $variables;
    }
}
