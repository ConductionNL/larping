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

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];

//        foreach($variables['organizations'] as $key => $value){
//            $variables['organizations'][$key]['totals'] =  $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'item_total'],['resource' => $variables['organizations']['@id']]);
//        }



        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function organizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher, $id)
    {
        $variables = [];
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id'=>$id]);
         if(array_key_exists('contact',$variables['organization']) && $variables['organization']['contact']){
            $variables['contact'] = $commonGroundService->getResource($variables['organization']['contact']);
        }

        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'],['resource' => $variables['organization']['@id']])['hydra:member'];
        $variables['likes'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'],['resource' => $variables['organization']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];

        // Add review
        if ($request->isMethod('POST') && $request->request->get('@type') == 'review') {
            $resource = $request->request->all();

            $resource['organization'] = $variables['organization']['@id'];
            $resource['resource'] = $variables['organization']['@id'];
            $resource['author'] = $this->getUser()->getPerson();

            // Save to the commonground component
            $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);
        }

        return $variables;
    }
}
