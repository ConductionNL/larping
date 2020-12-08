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
 * The Procces test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class DefaultController
 *
 * @Route("/")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        if ($this->getUser()) {
            $person = $commonGroundService->getResource($this->getUser()->getPerson());
            $personUrl = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'people', 'id' => $person['id']]);

            $employees = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'employees'], ['person' => $personUrl])['hydra:member'];

            if (!count($employees) > 0) {
                $mailingService->sendMail('mails/welcome_mail.html.twig', 'no-reply@conduction.nl', $this->getUser()->getUsername(), 'Welkom op conduction.academy');

                $employee = [];
                $employee['person'] = $personUrl;

                $commonGroundService->createResource($employee, ['component' => 'mrc', 'type' => 'employees']);

                $providers = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'];
                $provider = $providers[0];

                $users = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()])['hydra:member'];
                $user = $users[0];

                $userUrl = $commonGroundService->cleanUrl(['component' => 'uc', 'type' => 'users', 'id' => $user['id']]);
            }
        }

        return $variables;
    }

    /**
     * @Route("/login")
     * @Template
     */
    public function loginAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/register")
     * @Template
     */
    public function registerAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/organization")
     * @Template
     */
    public function organizationAction(CommonGroundService $commonGroundService, Request $request)
    {
        $variables = [];

        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_user_idvault').'?backUrl='.$request->getUri());
        }

        if ($request->query->get('backUrl')) {
            $variables['backUrl'] = $request->query->get('backUrl');
        }

        return $variables;
    }

    /**
     * @Route("/newsletter")
     * @Template
     */
    public function newsletterAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        // TODO: use email used in form to subscribe to the newsletter?

        $session->set('backUrl', $request->query->get('backUrl'));

        $providers = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'];
        $provider = $providers[0];

        $redirect = $this->generateUrl('app_default_index', ['message' => 'you have successfully signed up for the newsletter!'], UrlGeneratorInterface::ABSOLUTE_URL);

        if (isset($provider['configuration']['app_id']) && isset($provider['configuration']['secret'])) {
            $dev = '';
            if ($params->get('app_env') == 'dev') {
                $dev = 'dev.';
            }

            return $this->redirect('http://id-vault.com/sendlist/authorize?client_id='.$provider['configuration']['app_id'].'&send_lists=8b929e53-1e16-4e59-a254-6af6b550bd08&redirect_uri='.$redirect);
        } else {
            return $this->render('500.html.twig');
        }
    }
}
