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
        $variables['settings'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'settings'])['hydra:member'];
        $variables['regions'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'regions'])['hydra:member'];
        $variables['features'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories'], ['parent.name' => 'features'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/newsletter")
     * @Template
     */
    public function newsletterAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
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
            $userLike = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['resource' => $request->get('resource'), 'author' => $this->getUser()->getPerson()])['hydra:member'];

            $amountOfLikes = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['resource' => $request->get('resource')])['hydra:totalItems'];

            if (count($userLike) > 0) {
                $like = $userLike[0];
                // Delete this existing like
                $commonGroundService->deleteResource($like);

                return new JsonResponse([
                    'status'        => 'unliked',
                    'amountOfLikes' => $amountOfLikes,
                ]);
            } else {
                $like['author'] = $this->getUser()->getPerson();
                $like['resource'] = $request->get('resource');
                $like['organization'] = $request->get('organization');
                $commonGroundService->saveResource($like, ['component' => 'rc', 'type' => 'likes'], [], [], false, false);

                return new JsonResponse([
                    'status'        => 'liked',
                    'amountOfLikes' => $amountOfLikes,
                ]);
            }
        } else {
            return new JsonResponse(['status' => 'you are not logged in']);
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
     * @Route("/terms-and-conditions/{id}")
     * @Template
     */
    public function termsAndConditionsForOrgAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params, $id, Session $session)
    {
        try {
            $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);
        } catch (\Exception $exception) {
            return $this->redirectToRoute('app_index_default');
        }

        return $variables;
    }

    /**
     * @Route("/review")
     * @Template
     */
    public function reviewAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
        // Add review
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            $resource['author'] = $this->getUser()->getPerson();
            $resource['rating'] = (int) $resource['rating'];

            // Save to the commonground component
            $variables['review'] = $commonGroundService->saveResource($resource, ['component' => 'rc', 'type' => 'reviews'], [], [], false, false);

            // redirects externally
            if ($request->get('redirect')) {
                return $this->redirect($request->get('redirect'));
            }
        }

        return $variables;
    }

    /**
     * @Route("/contact")
     * @Template
     */
    public function contactAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
        $variables['organization'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => 'd24e147f-00b9-4970-9809-6684a3fb965b']);
        if (array_key_exists('contact', $variables['organization']) && $variables['organization']['contact']) {
            $variables['contact'] = $commonGroundService->getResource($variables['organization']['contact']);
        }

        if ($this->getUser() && $request->isMethod('POST')) {
            $resource = $request->request->all();
            $resource['snder'] = $this->getUser()->getPerson();

            $commonGroundService->saveResource($resource, ['component' => 'cm', 'type' => 'contact_moment']);

            // redirects externally
            if ($request->get('redirect')) {
                return $this->redirect($request->get('redirect'));
            }
        }

        return $variables;
    }
}
