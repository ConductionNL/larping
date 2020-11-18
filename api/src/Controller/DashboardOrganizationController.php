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
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/tutorials")
     * @Template
     */
    public function tutorialsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        $variables['addPath'] = 'app_dashboardorganization_tutorial';
        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resource tutorials (known as cources component side)
        $variables['tutorials'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses'], $variables['query'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/tutorials/{id}")
     * @Template
     */
    public function tutorialAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];
        $variables['activities'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'activities'])['hydra:member'];
        $variables['additionalType'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'activities'])['hydra:member'];

        if ($id != 'new') {
            // Get resource tutorial (known as course component side)
            $variables['participants'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], ['courses.id' => $id])['hydra:member'];
            $variables['tutorial'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses', 'id' => $id]);
        } else {
            $variables['tutorial'] = ['id' => 'new'];
            $variables['tutorial']['name'] = 'new tutorial';
        }
        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            // Add the post data to the already aquired resource data
//            $resource = array_merge($variables['tutorial'], $resource);
            // Update to the commonground component
            $variables['tutorial'] = $commonGroundService->saveResource($resource, ['component' => 'edu', 'type' => 'courses']);

            return $this->redirect($this->generateUrl('app_dashboardorganization_tutorials'));
        }

        return $variables;
    }

    /**
     * @Route("/internships")
     * @Template
     */
    public function internshipsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        $variables['addPath'] = 'app_dashboardorganization_internship';
        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resources Interschips
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'], $variables['query'])['hydra:member'];

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            //array legen voor posten van nieuwe stage
            $variables['internship'] = [];
            //array waar mn form inzit
            $resource = $request->request->all();

            $resource['standardHours'] = (int) $resource['standardHours'];
            $resource['baseSalary'] = (int) $resource['baseSalary'];

            // Add the post data to the already aquired internship data
            $variables['internship'] = array_merge($variables['internship'], $resource);

            // Save to the commonground component
            $variables['internship'] = $commonGroundService->saveResource($resource, ['component' => 'mrc', 'type' => 'job_postings']);
        }

        return $variables;
    }

    /**
     * @Route("/internships/{id}")
     * @Template
     */
    public function internshipAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];
        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resource Interschip
        if ($id != 'new') {
            $variables['internship'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings', 'id' => $id]);
        } else {
            $variables['internship'] = [];
        }
        //Get resources Organizations
        $variables['organizations'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations'], $variables['query'])['hydra:member'];

        if (isset($variables['internship']['application'])) {
            //Get current application
            $variables['application'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'applications', 'id' => $variables['internship']['application']['id']]);
            //get employee
            $variables['employee'] = $commonGroundService->getResource('https://dev.zuid-drecht.nl/api/v1/mrc'.$variables['application']['employee']);
        }

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

        // Get resource challenges (known as tender component side)
        $variables['challenges'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders'], $variables['query'])['hydra:member'];

        $variables['addPath'] = 'app_dashboardorganization_challenge';

        return $variables;
    }

    /**
     * @Route("/challenges/{id}")
     * @Template
     */
    public function challengeAction(Request $request, CommonGroundService $commonGroundService, $id)
    {
        $variables = [];

        $variables['organizations'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        $variables['tutorials'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses'])['hydra:member'];

        if ($id != 'new') {
            // Get resource challenges (known as tender component side)
            $variables['challenge'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders', 'id' => $id]);
            $variables['proposals'] = $commonGroundService->getResourceList(['component' => 'chrc', 'type' => 'proposals'], ['tender.id' => $id])['hydra:member'];
        } else {
            $variables['challenge'] = ['id' => 'new'];
        }

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Add the post data to the already aquired resource data
            $resource = array_merge($variables['challenge'], $resource);

//            var_dump($resource);die;
            // Update to the commonground component
            $variables['challenge'] = $commonGroundService->saveResource($resource, ['component' => 'chrc', 'type' => 'tenders']);

            return $this->redirect($this->generateUrl('app_dashboardorganization_challenges'));
        }

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

        $variables['teams'] = [];

        return $variables;
    }

    /**
     * @Route("/teams/{id}")
     * @Template
     */
    public function teamAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/competences")
     * @Template
     */
    public function competencesAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        $variables['competences'] = [];

        return $variables;
    }

    /**
     * @Route("/competences/{id}")
     * @Template
     */
    public function competenceAction(CommonGroundService $commonGroundService, Request $request, $id)
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
}
