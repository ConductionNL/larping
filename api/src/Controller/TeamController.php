<?php

// src/Controller/TeamController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The TeamController test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class TeamController
 *
 * @Route("/teams")
 */
class TeamController extends AbstractController
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

        //@TODO filter when filter arrays are possible

        // Get resource
        $variables['teams'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'groups'])['hydra:member']; //Do not use query here?!
        $variables['entries'] = $commonGroundService->getResourceList(['component' => 'chrc', 'type' => 'entries'], $variables['query'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function teamAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];

        // Get Resource
        $variables['team'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'groups', 'id' => $id]);
        $entries = $commonGroundService->getResourceList(['component' => 'chrc', 'type' => 'entries'], ['submitter' => $variables['team']['@id']]);
        $variables['entries'] = $entries['hydra:member'];
        $variables['numberOfEntries'] = $entries['hydra:totalItems'];

        $variables['participations'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], [''])['hydra:member'];

        if ($request->isMethod('POST') && $this->getUser()) {
            $participant['person'] = $this->getUser()->getPerson();
            $participant['groups'][] = '/groups/'.$variables['team']['id'];

            if ($request->request->get('motivaton')) {
                $participant = $request->request->get('motivaton');
            }
            $participant['status'] = 'pending';

            $participant = $commonGroundService->saveResource($participant, ['component' => 'edu', 'type' => 'participants']);
            $variables['team'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'groups', 'id' => $id]);
        }

        if (isset($variables['team']['participations']) && count($variables['team']['participations']) > 0) {
            foreach ($variables['team']['participations'] as $part) {
                if ($part['person'] == $this->getUser()->getPerson()) {
                    $variables['userIsInTeam'] = true;
                }
            }
        }

        return $variables;
    }
}
