<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The DashboardController handles any calls about administration and dashboard pages.
 *
 * Class DashboardController
 *
 * @Route("/dashboard/administration")
 */
class DashboardAdministrationController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, Request $request, IdVaultService $idVaultService, ParameterBagInterface $params)
    {
        $variables = [];
        // Make sure the user is logged in
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault'));
        }

        return $variables;
    }

    /**
     * @Route("/organizations")
     * @Template
     */
    public function organizationsAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, IdVaultService $idVaultService)
    {
        $variables = [];
        // Make sure the user is logged in
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault'));
        }

        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/locations")
     * @Template
     */
    public function locationsAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, IdVaultService $idVaultService)
    {
        $variables = [];
        // Make sure the user is logged in
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault'));
        }

        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/categories")
     * @Template
     */
    public function categoriesAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, IdVaultService $idVaultService)
    {
        $variables = [];
        // Make sure the user is logged in
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault'));
        }

        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'settings'])['hydra:member'];
        $variables['features'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'features'])['hydra:member'];

        //aanmaken en aanpassen categories
        if ($request->isMethod('POST')) {
            $category = $request->request->all();
            $category['name'] = $request->get('name');
            $category['icon'] = $request->get('icon');
            $category['organization'] = '/organizations/e62b32b5-2d1f-4412-9eb7-225bce414d05';
            $category['parent'] = '/categories/'.$request->get('parent');
            $category['root'] = '/categories/'.$request->get('parent');
            $commonGroundService->saveResource($category, ['component' => 'wrc', 'type' => 'categories']);
        }

        return $variables;
    }
}
