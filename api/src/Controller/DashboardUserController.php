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
        $personUrl = $this->getUser()->getPerson();

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get random unfilterd data
        $variables['challenges'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders'], $variables['query'])['hydra:member'];
        $variables['teams'] = [];
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'], $variables['query'])['hydra:member'];

        //  Getting the participant @todo this needs to be more foolproof
        if ($this->getUser()) {
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants', ['person'=> $personUrl]])['hydra:member'];
        } else {
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants', ['person'=> 'https://dev.zuid-drecht.nl/api/v1/cc/people/d961291d-f5c1-46f4-8b4a-6abb41df88db']])['hydra:member'];
        }
        if (count($participants) > 0) {
            $variables['participant'] = $participants[0];
        }
        //employee connected to user
        $employee = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'employees', ['person' => $personUrl]])['hydra:member'];
        //applications ophalen die gemaakt zijn door de user
        $variables['applications'] = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'applications', ['employee' => $employee['@id']]])['hydra:member'];


        $variables['courses'] = $variables['participant']['courses'];
        $variables['programs'] = $variables['participant']['programs'];
        $variables['results'] = $variables['participant']['results'];

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
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants', ['person'=> $this->getUser()->getPerson()]])['hydra:member'];
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
        $variables = [];

        return $variables;
    }
}
