<?php

// src/Controller/ChallengeController.php

namespace App\Controller;

//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The ChallengeController test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class ChallengeController
 *
 * @Route("/challenges")
 */
class ChallengeController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(Request $request, CommonGroundService $commonGroundService)
    {

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resource challenges (known as tender component side)
        $variables['challenges'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders'], $variables['query'])['hydra:member'];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function challengeAction(Request $request, CommonGroundService $commonGroundService, $id)
    {
        $variables = [];

        // Get resource challenges (known as tender component side)
        if ($this->getUser()) {
            $person = $commonGroundService->getResource($this->getUser()->getPerson());
            $personUrl = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'people', 'id' => $person['id']]);

            $variables['personUrl'] = $personUrl;
            $variables['person'] = $person;
        }
        $variables['challenge'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders', 'id' => $id]);
        $variables['entry'] = $commonGroundService->getResourceList(['component' => 'chrc', 'type' => 'entries'], ['tender.id' => $id])['hydra:member'];
        $variables['stages'] = $commonGroundService->getResourceList(['component' => 'chrc', 'type' => 'tender_stages'], ['tender.id' => $id])['hydra:member'];

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['tender'] = '/tenders/'.$resource['tender'];

            // Update to the commonground component
            $variables['entry'] = $commonGroundService->saveResource($resource, ['component' => 'chrc', 'type' => 'entries']);
        }

        return $variables;
    }
}
