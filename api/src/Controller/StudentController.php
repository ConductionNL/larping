<?php

// src/Controller/StudentController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The StudentController test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class StudentController
 *
 * @Route("/students")
 */
class StudentController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {
        if ($request->query->get('organization') && empty($this->getUser()->getOrganization())) {
            return $this->redirect($this->generateUrl('app_default_organization').'?backUrl='.$request->getUri());
        }

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resource students
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], $variables['query'])['hydra:member'];

        // Remove duplicate persons to get students
        $personIds = [];
        foreach ($variables['participants'] as $participant) {
            if (isset($participant['person']) && $participant['person']) {
                if (!in_array($participant['person'], $personIds)) {
//                var_dump($personIds );
//                var_dump($participant['id']);
                    $variables['students'][] = $commonGroundService->getResource($participant['person']);
                    $personIds[] = $participant['person'];
                }
            }
        }

        // If somehow a student is a hydra collection add the members to the array and unset the hydra collection
        // Weird bug tho
        foreach ($variables['students'] as $i => $stud) {
            if (isset($stud['hydra:member'])) {
                foreach ($stud['hydra:member'] as $student) {
                    $variables['students'][] = $student;
                }
                unset($variables['students'][$i]);
            }
        }

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function portfolioAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];

        // Get Resource student
        $variables['student'] = $commonGroundService->getResource(['component' => 'cc', 'type' => 'people', 'id' => $id]);
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], ['person' => $variables['student']['@id']])['hydra:member'];

        $variables['portfolio'] = $commonGroundService->getResourceList(['component' => 'pfc', 'type' => 'portfolios'], ['owner' => $variables['student']['@id']])['hydra:member'];
        if (isset($variables['portfolio']) && $variables['portfolio']) {
            $variables['portfolio'] = $variables['portfolio'][0];
        } else {
            unset($variables['portfolio']);
        }

        $programIds = [];
        $courseIds = [];
        $groupIds = [];
        foreach ($variables['participants'] as $participant) {
            if (isset($participant['program']) && $participant['program']) {
                if (!in_array($participant['program']['id'], $programIds)) {
                    $variables['programs'][] = $participant['program'];
                    $programIds[] = $participant['id'];
                }
            }
            if (isset($participant['course']) && $participant['course']) {
                if (!in_array($participant['course']['id'], $courseIds)) {
                    $variables['courses'][] = $participant['course'];
                    $courseIds[] = $participant['course']['id'];
                }
            }
            if (isset($participant['groups']) && $participant['groups']) {
                foreach ($participant['groups'] as $group) {
                    if (!in_array($group['id'], $groupIds)) {
                        $variables['groups'][] = $group;
                        $groupIds[] = $group['id'];
                    }
                }
            }
        }

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            if (isset($resource['contactMoment']) && $resource['contactMoment']) {
                $resource['contactMoment']['receiver'] = $variables['student']['@id'];
                $resource['contactMoment']['sender'] = $variables['student']['@id'];
                $resource['contactMoment']['channel'] = 'Direct message';
                $resource['contactMoment']['topic'] = 'Direct message';
            }

            // Save to the commonground component
            $variables['contactMoment'] = $commonGroundService->saveResource($resource['contactMoment'], 'https://cmc.dev.zuid-drecht.nl/contact_moments');
        }

        return $variables;
    }
}
