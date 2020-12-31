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
 * @Route("/dashboard")
 */
class DashboardController extends AbstractController
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

            // Update to the commonground component
            $item = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

//          get url of new organization to make cc organization
            $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $item['id']]);
            $resource['sourceOrganization'] = $organizationUrl;
            $variables['organizations'] = $commonGroundService->saveResource($resource, ['component' => 'cc', 'type' => 'organizations']);
            $orgUrl = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $variables['organizations']['id']]);
            $item['contact'] = $orgUrl;
            $variables['items'][] = $commonGroundService->saveResource($item, ['component' => 'wrc', 'type' => 'organizations']);
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
        $variables = [];
        $variables['type'] = 'events';

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Check if org has name (required)
            if ($resource['events']['name']) {
                // Make or save email
                if ($resource['email']['email']) {
                    if (!isset($resource['email']['name'])) {
                        $resource['email']['name'] = 'Email';
                    }
                    $email = $commonGroundService->saveResource($resource['email'], ['component' => 'arc', 'type' => 'emails']);
                }
                // Make or save telephone
                if ($resource['telephone']['telephone']) {
                    if (!isset($resource['telephone']['name'])) {
                        $resource['telephone']['name'] = 'Telephone';
                    }
                    $telephone = $commonGroundService->saveResource($resource['telephone'], ['component' => 'arc', 'type' => 'telephones']);
                }
                // If we have a email add it to the org
                if (isset($email)) {
                    $resource['events']['emails'][0] = '/emails/'.$email['id'];
                }
                // If we have a telephone add it to the org
                if (isset($telephone)) {
                    $resource['events']['telephones'][0] = '/telephones/'.$telephone['id'];
                }

                // Save organization
                $org = $commonGroundService->saveResource($resource['events'], ['component' => 'arc', 'type' => 'events']);
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
