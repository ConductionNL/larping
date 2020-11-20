<?php

// src/Controller/DashboardController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The DashboardController test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
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
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get random unfilterd data
        $variables['challenges'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders'], $variables['query'])['hydra:member'];
        $variables['teams'] = [];
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'], $variables['query'])['hydra:member'];

        //  Getting the participant @todo this needs to be more foolproof
        if ($this->getUser()) {
            $personUrl = $this->getUser()->getPerson();
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants', ['person'=> $personUrl]])['hydra:member'];

            if (count($participants) > 0) {
                $variables['participant'] = $participants[0];
            }
        }

        //employee connected to user
        $employee = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'employees', ['person' => $personUrl]])['hydra:member'];
        if (count($employee) > 0) {
            $employee = $employee[0];
        }
        //applications ophalen die gemaakt zijn door de user
        $variables['applications'] = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'applications', ['employee' => $employee['@id']]])['hydra:member'];

        if (!empty($variables['participant']['courses'])) {
            $variables['courses'] = $variables['participant']['courses'];
        }
        if (!empty($variables['participant']['programs'])) {
            $variables['programs'] = $variables['participant']['programs'];
        }
        if (!empty($variables['participant']['programs'])) {
            $variables['results'] = $variables['participant']['results'];
        }

        return $variables;
    }

    /**
     * @Route("/tutorials")
     * @Template
     */
    public function tutorialsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        $variables['tutorials'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses'], $variables['query'])['hydra:member'];

        //  Getting the participant @todo this needs to be more foolproof
        if ($this->getUser()) {
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants', ['person' => $this->getUser()->getPerson()]])['hydra:member'];
        }
        if (count($participants) > 0) {
            // Get all tutorials for each participant of this user
            $tutorials = [];
            foreach ($participants as $participant) {
                if (isset($participant['course'])) {
                    array_push($tutorials, $participant['course']);
                }
            }
            $variables['tutorials'] = $tutorials;
        }

        return $variables;
    }

    /**
     * @Route("/tutorials/{id}")
     * @Template
     */
    public function tutorialAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];

        $variables['tutorial'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses', 'id' => $id]);

        return $variables;
    }

    /**
     * @Route("/internships")
     * @Template
     */
    public function internshipsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/internships/{id}")
     * @Template
     */
    public function internshipAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/propositions")
     * @Template
     */
    public function propositionsAction(Request $request, CommonGroundService $commonGroundService)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/propositions/{id}")
     * @Template
     */
    public function propositionAction(Request $request, CommonGroundService $commonGroundService, $id)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/challenges")
     * @Template
     */
    public function challengesAction(Request $request, CommonGroundService $commonGroundService)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/challenges/{id}")
     * @Template
     */
    public function challengeAction(Request $request, CommonGroundService $commonGroundService, $id)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/teams")
     * @Template
     */
    public function teamsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/teams/{id}")
     * @Template
     */
    public function teamAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/settings")
     * @Template
     */
    public function settingsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/organizations")
     * @Template
     */
    public function organizationsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['path'] = 'app_dashboarduser_organizations';
        $variables['pathToSingular'] = 'app_dashboarduser_organization';
        $variables['type'] = 'organization';

        if ($organization = $this->getUser()->getOrganization()) {
            $variables['organization'] = $commonGroundService->getResource($organization);
        }

        $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];
        $userGroups = $user['userGroups'];

        // Organizations
        $variables['items'] = [];
        $organizationIds = [];
        foreach ($userGroups as $userGroup) {
            $organization = $commonGroundService->getResource($userGroup['organization']);
            if (!in_array($organization['id'], $organizationIds)) {
                $variables['items'][] = $organization;
                $organizationIds[] = $organization['id'];
            }
        }

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];

            $resource = array_merge($user, $resource);
            unset($resource['userGroups']);
            $user = $commonGroundService->saveResource($resource, ['component' => 'uc', 'type' => 'users']);
//            $this->getUser()->setOrganization($resource['organization']);

            if (isset($variables['item']) && !empty($variables['item'])) {
                return $this->redirectToRoute($variables['path'], ['id' => $variables['item']['id']]);
            } else {
                return $this->redirectToRoute($variables['path']);
            }
        }

        return $variables;
    }

    /**
     * @Template
     * @Route("/organizations/{id}")
     */
    public function organizationAction(CommonGroundService $commonGroundService, Request $request, $id = null)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];
        $redirectToPlural = false;
        if ($id && $id != 'new') {
            $variables['item'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);
        } else {
            $variables['item'] = [];
            $variables['item']['name'] = 'New';

            $redirectToPlural = true;
        }
        $variables['path'] = 'app_dashboarduser_organization';
        $variables['pathToPlural'] = 'app_dashboarduser_organizations';

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Add the post data to the already aquired resource data
            $resource = array_merge($variables['item'], $resource);

            if (isset($resource['style'])) {
                $resource['style'] = '/styles/'.$resource['style']['id'];
            }

            // Update to the commonground component
            $variables['item'] = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

            $variables['userGroups'] = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $variables['item']['@id']])['hydra:member'];

            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];

            if (!empty($resource['nameUG']) && !empty($resource['descUG'])) {
                $newUserGroup['name'] = $resource['nameUG'];
                $newUserGroup['description'] = $resource['descUG'];
                $newUserGroup['title'] = $resource['nameUG'];
                $newUserGroup['organization'] = $variables['item']['@id'];
                $newUserGroup['canBeRegisteredFor'] = true;
                $newUserGroup['users'][] = 'users/'.$user['id'];

                $newUserGroup = $commonGroundService->saveResource($newUserGroup, ['component' => 'uc', 'type' => 'groups']);
            }

            if (count($variables['userGroups']) == 0) {
                $userGroup = [];
                $userGroup['name'] = $variables['item']['name'].'-admin';
                $userGroup['organization'] = $variables['item']['@id'];
                $userGroup['description'] = 'The administrators for the organization';
                $userGroup['code'] = $variables['item']['name'].'-admin';
                $userGroup['canBeRegisteredFor'] = false;
                $userGroup['users'][] = 'users/'.$user['id'];

                $userGroup = $commonGroundService->saveResource($userGroup, ['component' => 'uc', 'type' => 'groups']);
            } else {
                $userGroup = $variables['userGroups'][0];
            }
            if (!$this->getUser()->getOrganization()) {
                $user['organization'] = $userGroup['organization'];
                unset($user['userGroups']);
                $user = $commonGroundService->saveResource($user, ['component' => 'uc', 'type' => 'users']);
            }
            if ($redirectToPlural === true) {
                return $this->redirectToRoute($variables['pathToPlural']);
            } else {
                return $this->redirectToRoute($variables['path'], ['id' => $variables['item']['id']]);
            }
        }

        return $variables;
    }
}
