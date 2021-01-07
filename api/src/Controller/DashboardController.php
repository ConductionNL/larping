<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Security\User\IdVaultUser;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

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
    public function switchOrganizationAction(CommonGroundService $commonGroundService, IdVaultService $idVaultService, $id)
    {
        $user = $idVaultService->updateUserOrganization($id, $this->getUser()->getUsername());
        $person = $commonGroundService->getResource($user['person']);
        $userObject = new IdVaultUser($user['username'], $user['username'], $person['name'], null, $user['roles'], $user['person'], $user['organization'], 'id-vault');

        $token = new UsernamePasswordToken($userObject, null, 'main', $userObject->getRoles());
        $this->container->get('security.token_storage')->setToken($token);
        $this->container->get('session')->set('_security_main', serialize($token));

        return $this->redirectToRoute('app_dashboardorganization_index');
    }
}
