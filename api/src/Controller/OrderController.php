<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use App\Service\ShoppingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use http\Env\Response;
use phpDocumentor\Reflection\Types\String_;
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
 * The OrderController handles any calls about orders.
 *
 * Class OrderController
 *
 * @Route("/order")
 */
class OrderController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, CommonGroundService $commonGroundService, ShoppingService $ss, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
        $variables['order'] = $session->get('order');

        if ($this->getUser() && $this->getUser()->getPerson()) {
            $person = $this->getUser()->getPerson();
            $order = $ss->makeOrder($person);
        }

        if ($request->isMethod('POST') && $request->request->get('makeOrder') == 'true' && isset($order) &&
            $this->getUser()) {
            $ss->redirectToMollie($order);
        }
        
        return $variables;
    }


    /**
     * @Route("/remove-item/{id}")
     * @Template
     */
    public function removeItemAction(Session $session, CommonGroundService $commonGroundService, ShoppingService $ss, MailingService $mailingService, Request $request, ParameterBagInterface $params, $id)
    {
        $order = $ss->removeItem($id);

        return $this->redirectToRoute('app_order_index');
    }

    /**
     * @Route("/payment")
     * @Template
     */
    public function paymentAction(Session $session, CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        if ($session->get('invoice@id')) {
            $variables['invoice'] = $commonGroundService->getResource($session->get('invoice@id'));


            // Get invoice with updated status from mollie
            $object['target'] = $variables['invoice']['id'];
            $variables['invoice'] = $commonGroundService->saveResource($object, ['component' => 'bc', 'type' => 'status']);

            // Empty session order when order is paid
            if (isset($variables['invoice']['status']) && $variables['invoice']['status'] == 'paid') {
                $session->set('order', null);
            }
        } else {
            return $this->redirectToRoute('app_order_index');
        }

        return $variables;
    }
}
