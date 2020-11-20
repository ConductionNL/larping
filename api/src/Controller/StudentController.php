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
        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resource students
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], $variables['query'])['hydra:member'];

//        var_dump($variables['participants']);
        // Remove duplicate persons to get students
        $personIds = [];
        foreach ($variables['participants'] as $participant) {
            if (!in_array($participant['id'], $personIds)) {
                if (isset($participant['person']) && $participant['person']) {
                    $variables['students'][] = $commonGroundService->getResource($participant['person']);
                }
                $personIds[] = $participant['id'];
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
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // Get Resource student
        $variables['student'] = $commonGroundService->getResource(['component' => 'cc', 'type' => 'people', 'id' => $id]);
        $variables['participants'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], ['person' => $variables['student']['@id']])['hydra:member'];

        $programIds = [];
        foreach ($variables['participants'] as $participant) {
            if (isset($participant['program']) && $participant['program']) {
                if (!in_array($participant['program']['id'], $programIds)) {
                    $variables['programs'][] = $participant['program'];
                    $programIds[] = $participant['id'];
                }
            }
        }

        $courseIds = [];
        foreach ($variables['participants'] as $participant) {
            if (isset($participant['course']) && $participant['course']) {
                if (!in_array($participant['course']['id'], $courseIds)) {
                    $variables['courses'][] = $participant['course'];
                    $courseIds[] = $participant['course']['id'];
                }
            }
        }

        return $variables;
    }
}
