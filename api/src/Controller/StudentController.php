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
        $variables['students'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], $variables['query'])['hydra:member'];
        $variables['emails'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'emails'])['hydra:member'];
        $variables['socials'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'socials'])['hydra:member'];


        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function portfolioAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];

        // Get resource students
        $variables['students'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'participants'])['hydra:member'];

        // Get Resource student
        $variables['student'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'participants', 'id' => $id]);
        $variables['person'] = $commonGroundService->getResource($variables['student']['person']);

        return $variables;
    }
}
