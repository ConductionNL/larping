<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\ShoppingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
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
                if ($order === false) {
                    return $variables;
                } elseif (isset($order['@id'])) {
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
    public function paymentAction(Session $session, CommonGroundService $commonGroundService, ShoppingService $shoppingService, IdVaultService $idVaultService, Request $request, ParameterBagInterface $params)
    {
        if ($session->get('invoice@id') && $this->getUser()) {
            $variables['invoice'] = $commonGroundService->getResource($session->get('invoice@id'));

            // Get invoice with updated status from mollie
            $object['target'] = $variables['invoice']['id'];

            $variables['invoice'] = $commonGroundService->saveResource($object, ['component' => 'bc', 'type' => 'status']);

            // Empty session order when order is paid
            if (isset($variables['invoice']['status']) && $variables['invoice']['status'] == 'paid') {
                $shoppingService->removeOrderByInvoice($variables['invoice']);

                //lets see if we need to add the user to the members group of the organization
                foreach ($variables['invoice']['items'] as $item) {
                    $offer = $commonGroundService->getResource($item['offer']);
                    if ($offer['products'][0]['type'] == 'subscription') {
                        //add user to clients group
                        $provider = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $params->get('app_id')])['hydra:member'][0];
                        $groups = $idVaultService->getGroups($provider['configuration']['app_id'], $variables['invoice']['targetOrganization'])['groups'];

                        $organization = $commonGroundService->getResource($variables['invoice']['targetOrganization']);

                        if (count($groups) < 1) {
                            $memberGroup = $idVaultService->createGroup($provider['configuration']['app_id'], 'members', 'User group for members of '.$organization['name'], $organization['@id']);
                            $idVaultService->inviteUser($provider['configuration']['app_id'], $memberGroup['id'], $this->getUser()->getUsername(), true);

                            $idVaultService->createGroup($provider['configuration']['app_id'], 'root', 'User group for the root/admins of '.$organization['name'], $organization['@id']);
                        }
                        foreach ($groups as $group) {
                            if ($group['name'] == 'members' || $group['name'] == 'root' && !in_array($this->getUser()->getUsername(), array_column($group['users'], 'username'))) {
                                $idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $this->getUser()->getUsername(), true);
                            }
                        }
                    }
                }
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
            $redirectUrl = $request->get('redirectUrl');

            if ($shoppingService->addItemsToCart($offers, $redirectUrl) === false) {
                return $this->redirect($redirectUrl);
            }
        }

        return $this->redirectToRoute('app_shopping_index');
    }

//    /**
//     * @Route("/add-item")
//     * @Template
//     */
//    public function addItemAction(CommonGroundService $commonGroundService, ShoppingService $shoppingService, Request $request)
//    {
//        if ($request->isMethod('POST')) {
//            $offers = $request->get('offers');
//            $redirectUrl = $request->get('redirectUrl');
//
//            if ($shoppingService->addItemsToCart($offers, $redirectUrl) === false) {
//                return $this->redirect($redirectUrl);
//            }
//        }
//
//        return $this->redirectToRoute('app_shopping_index');
//    }

    /**
     * @Route("/remove-option")
     * @Template
     */
    public function removeOptionAction(CommonGroundService $commonGroundService, ShoppingService $shoppingService, Request $request)
    {
        if ($request->isMethod('POST')) {
            $option = $request->get('option');
            $redirectUrl = $request->get('redirect');

            if (isset($option)) {
                $shoppingService->removeOption($option);
            }

            if (isset($redirectUrl)) {
                return $this->redirectToRoute($redirectUrl);
            }
        }

        return $this->redirectToRoute('app_shopping_index');
    }

    /**
     * @Route("/remove-item")
     * @Template
     */
    public function removeItemAction(CommonGroundService $commonGroundService, ShoppingService $shoppingService, Request $request)
    {
        if ($request->isMethod('POST')) {
            $offer = $request->get('offer');
            $redirectUrl = $request->get('redirect');

            if (isset($offer)) {
                $shoppingService->removeItem($offer);
            }

            if (isset($redirectUrl)) {
                return $this->redirectToRoute($redirectUrl);
            }
        }

        return $this->redirectToRoute('app_shopping_index');
    }
}
