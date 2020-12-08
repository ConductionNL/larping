<?php

// src/Controller/OrganizationController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller handles any pages with organization(s) as main subject.
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
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables['slug'] = 'organizations';
        $variables['h1'] = 'organizations';
        $variables['path'] = 'app_organization_index';
        $variables['organizations'] = $commonGroundService->getResourceList(['component'=>'wrc', 'type'=>'organizations'])['hydra:member'];

        // Set the organization background-color for the icons shown with every organization
        foreach ($variables['organizations'] as &$organization) {
            if (isset($organization['style']['css'])) {
                preg_match('/background-color: ([#A-Za-z0-9]+)/', $organization['style']['css'], $matches);
                $organization['backgroundColor'] = $matches;
            }
        }

        if ($request->isMethod('POST')) {
            $search = $request->request->all()['search'];

            $variables['organizations'] = $commonGroundService->getResourceList(['component'=>'wrc', 'type'=>'organizations'], ['name'=>$search])['hydra:member'];
        }

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function organizationAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $variables['organization'] = $commonGroundService->getResource(['component'=>'wrc', 'type'=>'organizations', 'id'=>$id]);
        $variables['challenges'] = $commonGroundService->getResourceList(['component'=>'chrc', 'type'=>'tenders'], ['submitters'=>$variables['organization']['@id']])['hydra:member'];
        $variables['jobPostings'] = $commonGroundService->getResourceList(['component'=>'mrc', 'type'=>'job_postings'], ['hiringOrganization'=>$variables['organization']['@id']])['hydra:member'];
        $variables['courses'] = $commonGroundService->getResourceList(['component'=>'edu', 'type'=>'courses'], ['organization'=>$variables['organization']['@id']])['hydra:member'];
//        $variables['programs'] = $commonGroundService->getResourceList(['component'=>'edu', 'type'=>'programs'], ['provider'=>$variables['organization']['@id']])['hydra:member'];

        $variables['h1'] = $variables['organization']['name'];
        $variables['slug'] = $variables['organization']['name'];

        return $variables;
    }
}
