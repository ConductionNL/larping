<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use App\Service\ShoppingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Procces test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class ShoppingController
 *
 * @Route("/")
 */
class ShoppingController extends AbstractController
{
    /**
     * @Route("/shopping-cart")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, ShoppingService $shoppingService, Request $request, Session $session)
    {
        $variables['orders'] = $session->get('orders');

        if ($request->isMethod('POST') && $request->request->get('makeOrder') == 'true' && isset($variables['orders']) &&
            $this->getUser()) {
            $order = $shoppingService->getOrderByOrganization($request->get('organization'));
            if ($order != false) {
                $person = $commonGroundService->getResource($this->getUser()->getPerson());
                $order = $shoppingService->uploadOrder($order, $person);
                if (isset($order['@id'])) {
                    $shoppingService->redirectToMollie($order);
                }
            }
        }

        return $variables;
    }

    /**
     * @Route("/payment-status")
     * @Template
     */
    public function paymentAction(Session $session, CommonGroundService $commonGroundService, ShoppingService $shoppingService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        if ($session->get('invoice@id')) {
            $variables['invoice'] = $commonGroundService->getResource($session->get('invoice@id'));

            // Get invoice with updated status from mollie
            $object['target'] = $variables['invoice']['id'];

            $variables['invoice'] = $commonGroundService->saveResource($object, ['component' => 'bc', 'type' => 'status']);

            // Empty session order when order is paid
            if (isset($variables['invoice']['status']) && $variables['invoice']['status'] == 'paid') {
                $shoppingService->removeOrderByInvoice($variables['invoice']);
            }
        } else {
            return $this->redirectToRoute('app_shopping_index');
        }

        return $variables;
    }

    /**
     * @Route("/add-items")
     * @Template
     */
    public function addOffersAction(CommonGroundService $commonGroundService, ShoppingService $shoppingService, Request $request)
    {
        if ($request->isMethod('POST')) {
            $offers = $request->get('offers');

            $shoppingService->addItemsToCart($offers);
        }

        return $this->redirectToRoute('app_shopping_index');
    }
}
