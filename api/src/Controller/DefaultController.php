<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $variables = [];
        $variables['events'] = $commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'])['hydra:member'];
        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'settings'])['hydra:member'];
        $variables['regions'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name'=>'regions'])['hydra:member'];

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

    /**
     * @Route("/like")
     * @Template
     */
    public function likeAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        if ($this->getUser() && $request->isMethod('POST')) {
            $likes = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['resource' => $request->get('resource'), 'author' => $this->getUser()->getPerson()])['hydra:member'];
            if (count($likes) > 0) {
                $like = $likes[0];
                // Delete this existing like
                $commonGroundService->deleteResource($like);

                return new JsonResponse(['data' => 'unliked']);
            } else {
                $like['author'] = $this->getUser()->getPerson();
                $like['resource'] = $request->get('resource');
                $like['organization'] = 'https://test.com';
                $like = $commonGroundService->saveResource($like, ['component'=>'rc', 'type'=>'likes']);

                return new JsonResponse(['data' => 'liked']);
            }
        } else {
            return new JsonResponse(['data' => 'you are not logged in']);
        }
    }

    /**
     * @Route("/how_it_works")
     * @Template
     */
    public function howItWorksAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
    }

    /**
     * @Route("/payment")
     * @Template
     */
    public function paymentAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
    }

    /**
     * @Route("/pricing")
     * @Template
     */
    public function pricingAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
    }

    /**
     * @Route("/terms_and_conditions")
     * @Template
     */
    public function termsAndConditionsAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
    }

    /**
     * @Route("/contact")
     * @Template
     */
    public function contactAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
//        $variables['request'] = $commonGroundService->getResourceList(['component' => 'vrc', 'type' => 'requests'])['hydra:member'];

        if ($this->getUser() && $request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['organization'] = 'https://dev.larping.eu';
            $resource['submitters'] = $this->getUser()->getPerson();

            $commonGroundService->saveResource($resource, ['component' => 'vrc', 'type' => 'requests']);
        }

        return $variables;
    }
}
