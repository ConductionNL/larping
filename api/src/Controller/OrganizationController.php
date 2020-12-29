<?php

// src/Controller/OrganizationController.php

namespace App\Controller;

use App\Service\MailingService;
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
 * The OrganizationController handles any calls about organizations.
 *
 * Class OrganizationController
 *
 * @Route("/organizations")
 */
class OrganizationController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
        $variables['items'] = $commonGroundService->getResourceList(['component' => 'cc', 'type' => 'organizations'])['hydra:member'];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        $variables['pathToSingular'] = 'app_organization_organization';
        $variables['typePlural'] = 'organizations';

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function organizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $variables = [];
        $variables['item'] = $commonGroundService->getResource(['component' => 'cc', 'type' => 'organizations', 'id'=>$id]);
        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'],['resource' => $variables['item']['@id']])['hydra:member'];
        $variables['wrcOrg'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])["hydra:member"];

        foreach ($variables['wrcOrg'] as $org){
            if ($org['contact'] == $variables['item']['@self']){
                $variables['wrcOrg'] = $org;
            }
        }
        $wrcUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $variables['wrcOrg']['id']]);
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $wrcUrl])['hydra:member'];

        // Add review
        if ($request->isMethod('POST') && $request->request->get('addReview') == 'true') {
            $resource = $request->request->all();


            $resource['organization'] = $variables['item']['@id'];
            $resource['resource'] = $variables['item']['@id'];
            $resource['author'] = $this->getUser()->getPerson();
            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);

            /*@todo make sure ratings work before using*/
            //make rating of the review
            //$rating = ['review' => '/reviews/' . $variables['review']['id'], 'ratingValue' => (int)$resource['ratingValue']];
            //$variables['rating'] = $commonGroundService->saveResource($rating, ['component' => 'rc', 'type' => 'ratings']);

        }

        return $variables;
    }
}
