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

        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['features'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'features'])['hydra:member'];
        $variables['search'] = $request->get('search', false);
        $variables['categories'] = $request->get('categories', []);

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];

        foreach ($variables['organizations'] as $organization) {
            $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organizations' => $organization['@id']])['hydra:member'];
        }

//        foreach($variables['organizations'] as $key => $value){
//            $variables['organizations'][$key]['totals'] =  $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'item_total'],['resource' => $variables['organizations']['@id']]);
//        }

        if ($request->isMethod('POST')) {
            $variables['filters'] = $request->request->get('filters');

            if (!$request->request->get('resetFilters')) {

                // We do 3 calls because we need to filter separately because if not it gives a empty list back if one filter doesn't find any results
                $organizations = [];
                if (isset($variables['filters']['keywordsInput']) && !empty($variables['filters']['keywordsInput'])) {
                    $organizations[] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'], ['name' => $variables['filters']['keywordsInput']])['hydra:member'];
                    $organizations[] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'], ['description' => $variables['filters']['keywordsInput']])['hydra:member'];
                }
//                if (isset($variables['filters']['locationInput']) && !empty($variables['filters']['locationInput'])) {
//                    $organizations[] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'events'], ['location' => $variables['filters']['locationInput']])['hydra:member'];
//                }

                // Looping through events to remove duplicates
                if (count($organizations) > 0) {
                    $orgIds = [];
                    $variables['organizations'] = [];
                    foreach ($organizations as $key => $organization) {
                        if (empty($organization)) {
                            unset($organizations[$key]);
                        }
                        // Because of previous array merging there gets an array in a array or multiple arrays in which we need to find the actual events..
                        if (is_array($organization) && !isset($organization['id'])) {
                            foreach ($organization as $item) {
                                if (isset($item['id']) && !in_array($item['id'], $orgIds)) {
                                    $variables['organizations'][] = $item;
                                    $orgIds[] = $item['id'];
                                }
                            }
                        } elseif (isset($organization['id']) && !in_array($organization['id'], $orgIds)) {
                            $variables['organizations'][] = $organization;
                            $orgIds[] = $organization['id'];
                        }
                    }
                }
            }
        }

        // Shitty code but it works
        // If filter is not set or reset filters has been clicked fetch all organizations

        if ((!isset($variables['filters']['keywordsInput']) or $variables['filters']['keywordsInput'] == '') or
            $request->request->get('resetFilters')) {
            $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        }

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
        if (array_key_exists('contact', $variables['organization']) && $variables['organization']['contact']) {
            $variables['contact'] = $commonGroundService->getResource($variables['organization']['contact']);
        }

        $variables['reviews'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['resource' => $variables['organization']['@id']])['hydra:member'];
        $variables['offers'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['organization' => $variables['organization']['@id']])['hydra:member'];
        $variables['totals'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'totals'], ['resource' => $variables['organization']['@id']]);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['resources.resource' => $variables['organization']['id']])['hydra:member'];

        // Add review
        if ($request->isMethod('POST') && $request->request->get('@type') == 'Review') {
            $resource = $request->request->all();

            $resource['organization'] = $variables['organization']['@id'];
            $resource['resource'] = $variables['organization']['@id'];
            $resource['author'] = $this->getUser()->getPerson();
            $resource['rating'] = (int) $resource['rating'];

            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews']);
        }

        return $variables;
    }
}
