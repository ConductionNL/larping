<?php

// src/Controller/EventController.php

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
 * The EventController handles any calls about events.
 *
 * Class EventController
 *
 * @Route("/events")
 */
class EventController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
//        $variables['items'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];
        $variables['pathToSingular'] = 'app_event_event';
        $variables['path'] = 'app_event_index';
        $variables['typePlural'] = 'events';

        // Making sure these vars are defined
        $filters['keywordsInput'] = '';
        $filters['locationInput'] = '';

        if ($request->isMethod('POST')) {
            $filters = $request->request->get('filters');
        }

        // Fetching items (with filters but they can be empty)
        $variables['items'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'],
            [
                'name' => $filters['keywordsInput'],
                'description' => $filters['keywordsInput'],
                'location' => $filters['locationInput']
            ]
        )['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function eventAction(Session $session, Request $request, CommonGroundService $commonGroundService, ShoppingService $ss, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $variables = [];
        $variables['path'] = 'app_event_event';
        $variables['event'] = $commonGroundService->getResource(['component' => 'arc', 'type' => 'events', 'id' => $id]);
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews', 'resource' => $variables['event']['@id']])['hydra:member'];
        $variables['groups'] = $commonGroundService->getResource(['component' => 'pdc', 'type' => 'groups']);

        /* deze is wat wierd */
        if (isset($variables['event']['resource']) && strpos($variables['event']['resource'], '/pdc/products/')) {
            $variables['product'] = $commonGroundService->getResource($variables['event']['resource']);
        }

        // Add review
        if ($request->isMethod('POST') && $request->request->get('addReview') == 'true') {
            $resource = $request->request->all();


            $resource['organization'] = $variables['event']['organization'];
            $resource['resource'] = $variables['event']['@id'];
            $resource['author'] = $this->getUser()->getPerson();
            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);

            /*@todo make sure ratings work before using*/
            //make rating of the review
            //$rating = ['review' => '/reviews/' . $variables['review']['id'], 'ratingValue' => (int)$resource['ratingValue']];
            //$variables['rating'] = $commonGroundService->saveResource($rating, ['component' => 'rc', 'type' => 'ratings']);

        }

        // Make order in session
        if ($request->isMethod('POST') && $request->request->get('makeOrder') == 'true' &&
            $request->request->get('offers')) {

            $resource = $request->request->all();

            // Add offers to session
            $order = $ss->addItemsToCart($resource['offers']);

            return $this->redirectToRoute('app_order_index');
        }
        return $variables;
    }
}
