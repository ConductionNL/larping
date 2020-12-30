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
        $variables['type'] = 'organization';

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Check if org has name (required)
            if ($resource['organization']['name']) {
                // Make or save email
                if ($resource['email']['email']) {
                    if (!isset($resource['email']['name'])) {
                        $resource['email']['name'] = 'Email';
                    }
                    $email = $commonGroundService->saveResource($resource['email'], ['component' => 'cc', 'type' => 'emails']);
                }
                // Make or save telephone
                if ($resource['telephone']['telephone']) {
                    if (!isset($resource['telephone']['name'])) {
                        $resource['telephone']['name'] = 'Main telephone';
                    }
                    $telephone = $commonGroundService->saveResource($resource['telephone'], ['component' => 'cc', 'type' => 'telephones']);
                }
                // If we have a email add it to the org
                if (isset($email)) {
                    $resource['organization']['emails'][0] = '/emails/'.$email['id'];
                }
                // If we have a telephone add it to the org
                if (isset($telephone)) {
                    $resource['organization']['telephones'][0] = '/telephones/'.$telephone['id'];
                }

                //make wrc organization
                $wrcOrg['rsin'] = "";
                $wrcOrg['chamberOfComerce'] = "";
                $wrcOrg['name'] = $resource['organization']['name'];
                $wrcOrg['description'] = $resource['organization']['description'];
                $org = $commonGroundService->saveResource($wrcOrg, ['component' => 'wrc', 'type' => 'organizations']);
                //get url of new $org to make cc organization
                $orgUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $org['id']]);

                $resource['organization']['sourceOrganization'] = $orgUrl;
                // Save organization
                $ccorg = $commonGroundService->saveResource($resource['organization'], ['component' => 'cc', 'type' => 'organizations']);

                $orgUrl = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $ccorg['id']]);
                $wrcOrg['contact'] = $orgUrl;
                //save contact
                $org = $commonGroundService->saveResource($wrcOrg, ['component' => 'wrc', 'type' => 'organizations', 'id' => $org['id']]);
                //send mail to user for new organization
                $data = [];
                $data['organization'] = $org;
                $mailingService->sendMail('emails/new_organization.html.twig', 'no-reply@conduction.nl', $this->getUser()->getUsername(), 'welcome', $data);
            }
        }

        $variables['items'] = $commonGroundService->getResourceList(['component'=>'cc', 'type'=>'organizations'])['hydra:member'];
        $variables['items'][] = [
            'id' => 'randomuuid',
            'name' => 'Conduction',
            'emails' => [
                0 => [
                    'email' => 'info@conduction.nl'
                ]
            ]
        ];

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

        $variables['item'] = $commonGroundService->getResource(['component' => 'cc', 'type' => 'organizations', 'id' => $id]);
        $variables['wrcorganization'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])["hydra:member"];

        foreach ($variables['wrcorganization'] as $org){
            if ($org['contact'] == $variables['item']['@self']){
                $variables['wrcorganization'] = $org;
            }
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
