<?php

// src/Controller/DefaultController.php

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
 * The DashboardController handles any calls about administration and dashboard pages.
 *
 * Class DashboardController
 *
 * @Route("/dashboard")
 */
class DashboardController extends AbstractController
{
    /**
     * Lets catch any users lost in routes
     *
     * @Route("/")
     */
    public function indexAction()
    {
        return $this->redirectToRoute('app_dashboarduser_index');
    }

    /**
     * Method  for switching the organization on a user session
     *
     * @Route("/switch-organization/{id}")
     */
    public function switchOrganizationAction(CommonGroundService $commonGroundService, $id)
    {
        $organization = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);

        //@todo Het ophalen van de user voor een @id is natuurlijk knude
        $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];
        $user['organization'] = $organization['@id'];
        unset($user['userGroups']);

        $commonGroundService->saveResource(['organization'=>$organization['@id']], ['component' => 'uc', 'type' => 'users', 'id' => $user['id']]);

        return $this->redirectToRoute('app_dashboardorganization_index', ['id' => $id]);
    }
}
