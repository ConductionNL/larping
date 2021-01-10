<?php

// src/Controller/LocationController.php

namespace App\Controller;

use App\Service\MailingService;
use App\Service\ShoppingService;
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
 * The LocationController handles any calls about locations.
 *
 * Class EventController
 *
 * @Route("/locations")
 */
class LocationController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];
        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'])['hydra:member'];
        $variables['regions'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'regions'])['hydra:member'];
        $variables['features'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'features'])['hydra:member'];
        $variables['search'] = $request->get('search', false);
        $variables['categories'] = $request->get('categories', []);
        $variables['hidefooter'] = 'hide';

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function locationAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables = [];
        $variables['location'] = $commonGroundService->getResource(['component' => 'lc', 'type' => 'places','id'=>$id]);
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['location' => $variables['location']['@id']])['hydra:member'];
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'],['resource' => $variables['location']['@id']]);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'],['resources.resource' => $variables['location']['@id']])['hydra:member'];

        // Add review
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Review') {
            $resource = $request->request->all();

            $resource['organization'] = $variables['organization']['@id'];
            $resource['resource'] = $variables['organization']['@id'];
            $resource['author'] = $this->getUser()->getPerson();

            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);

        }

        return $variables;
    }
}
