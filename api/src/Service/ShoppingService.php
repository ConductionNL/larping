<?php

// src/Service/BRPService.php

namespace App\Service;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

class ShoppingService
{
    private $params;
    private $cache;
    private $session;
    private $flashBagInterface;
    private $request;
    private $commonGroundService;
    private $requestService;
    private $idVaultService;

    public function __construct(
        ParameterBagInterface $params,
        CacheInterface $cache,
        SessionInterface $session,
        FlashBagInterface $flash,
        RequestStack $requestStack,
        CommonGroundService $commonGroundService,
        Security $security,
        IdVaultService $idVaultService
    ) {
        $this->params = $params;
        $this->cash = $cache;
        $this->session = $session;
        $this->flash = $flash;
        $this->request = $requestStack->getCurrentRequest();
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
        $this->idVaultService = $idVaultService;
    }

//    public function makeOrder($person)
//    {
//        if ($this->session->get('order')) {
//            $order = $this->session->get('order');
//
//            if (!isset($variables['order']['@id'])) {
//                $person = $this->commonGroundService->getResource($person);
//
//                if (isset($order['items']) && count($order['items']) > 0) {
//                    $offer = $this->commonGroundService->getResource($order['items'][0]['offer']);
//
//                    $items = $order['items'];
//
    ////                $sessionOrder = $order;
//
//                    $order['name'] = 'Order for ' . $person['name'];
//                    $order['description'] = 'Order for ' . $person['name'];
    ////                    $order['organization'] = $offer['offeredBy'];
//
//                    // Hardcoded org because of bug in PDC !
//                    $order['organization'] = 'https://dev.larping.eu/api/v1/wrc/organizations/51eb5628-3b37-497b-a57f-6b039ec776e5';
//
//
//                    $order['customer'] = $person['@id'];
//                    if ($this->request->get('remarks')) {
//                        $order['remark'] = $this->request->get('remarks');
//                    }
//                    if (isset($order['items'])) {
//                        unset($order['items']);
//                    }
//                    $order = $this->commonGroundService->saveResource($order, ['component' => 'orc', 'type' => 'orders']);
//
//                    foreach ($items as $item) {
//                        $offer = $this->commonGroundService->getResource($item['offer']);
//
//                        if (!isset($item['@id'])) {
//                            $item['name'] = $offer['name'];
//                            if (!isset($offer['description'])) {
//                                $item['description'] = $offer['name'];
//                            } else {
//                                $item['description'] = $offer['description'];
//                            }
//                            $item['quantity'] = intval($item['quantity']);
//                            $item['price'] = strval($offer['price']);
//                            $item['priceCurrency'] = $offer['priceCurrency'];
//                            $item['order'] = '/orders/' . $order['id'];
//                        }
//                        $item = $this->commonGroundService->saveResource($item, ['component' => 'orc', 'type' => 'order_items']);
//                    }
//                    $order = $this->commonGroundService->getResource($order['@id']);
//                }
//                $this->session->set('order', $order);
//            }
//        }
//        return $order;
//    }

    public function redirectToMollie($order)
    {
        $object['url'] = $order['@id'];
        $object['mollieKey'] = 'test_e56eJtnShswQS7Usn7uDhsheg9fjeH';

        if ($_ENV['APP_ENV'] != 'dev') {
            $object['redirectUrl'] = 'https://larping.eu/order/payment-status';
        } else {
            $object['redirectUrl'] = 'https://dev.larping.eu/payment-status';
        }

        // Only enable on localhost ! Dont forget to disable before pushing !
//        $object['redirectUrl'] = 'http://localhost/payment-status';

        $object = $this->commonGroundService->saveResource($object, ['component' => 'bc', 'type' => 'order']);

        if (isset($object['paymentUrl']) && strpos($object['paymentUrl'], 'https://www.mollie.com') !== false) {
            $this->session->set('invoice@id', $object['@id']);
            header('Location: '.$object['paymentUrl']);
            exit;
        }
    }

    public function addItemsToCart($offers)
    {
        $ordersInSession = $this->session->get('orders');
//        var_dump($ordersInSession);
        foreach ($offers as $newOrderItem) {
            // Check if new item has accpetable quantity
            if (!isset($newOrderItem['quantity']) || $newOrderItem < !1) {
                $newOrderItem['quantity'] = 1;
            }

            $offerFromThisItem = $this->commonGroundService->getResource($newOrderItem['offer']);

            if (isset($ordersInSession) && count($ordersInSession) > 0) {
                foreach ($ordersInSession as $key => $order) {
                    // Check if order with organization from this offer exists if true add item to that order
                    if (isset($order['organization']) && $order['organization'] == $offerFromThisItem['offeredBy']) {
                        if ($this->checkIfInOrder($newOrderItem, $order) == true) {
                            $ordersInSession[$key] = $this->cumulateItems($newOrderItem, $order);
                            $isNotInAOrder = true;
                        } else {
                            $ordersInSession[$key] = $this->addItemToOrder($newOrderItem, $order);
                            $isNotInAOrder = true;
                        }
                    } else {
                        $isNotInAOrder = false;
                    }
                    // Lazy fix
//                    if (isset($order['organization'])) {
//                        $ordersInSession[$key] = $order;
//                    }
                }
                if (isset($isNotInAOrder) && $isNotInAOrder == false) {
                    $ordersInSession[] = $this->makeNewOrder($newOrderItem);
                }
            } else {
                $ordersInSession[] = $this->makeNewOrder($newOrderItem);
                // Lazy fix
//                if (isset($order['organization'])) {
//                    $ordersInSession[] = $order;
//                }
            }
        }
        // Set orders in session
//        var_dump($ordersInSession);
//        die;
        $this->session->set('orders', $ordersInSession);

//        if (!isset($order)) {
//            $order = null;
//        }
//        return $order;
    }

    public function cumulateItems($newOrderItem, $order)
    {
        if (isset($order['orderItems']) && count($order['orderItems']) > 0) {
            foreach ($order['orderItems'] as $key => $orderItem) {
                if (isset($orderItem['offer']) && isset($newOrderItem['offer']) &&
                    $orderItem['offer'] == $newOrderItem['offer']) {
                    if ($this->checkIfIsPersonalTicket($this->commonGroundService->getResource($newOrderItem['offer'])) == true &&
                            $newOrderItem['quantity'] + $order['orderItems'][$key]['quantity'] > 1) {
                        $order['orderItems'][$key]['quantity'] = 1;
                    } else {
                        $order['orderItems'][$key]['quantity'] += $newOrderItem['quantity'];
                    }
                }
            }
        }

        return $order;
    }

    public function checkIfIsPersonalTicket($offer)
    {
        if (isset($offer['audience']) && $offer['audience'] == 'internal' &&
            isset($offer['products']) && $this->checkForTypeInProducts('ticket', $offer['products']) == true) {
            return true;
        }

        return false;
    }

    // Checks if item is in given order if true cumulates quantity
    public function checkIfInOrder($newOrderItem, $order)
    {
        if (isset($order['orderItems']) && count($order['orderItems']) > 0) {
            foreach ($order['orderItems'] as $key => $orderItem) {
                if (isset($orderItem['offer'])) {
                    if ($orderItem['offer'] == $newOrderItem['offer']) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function checkForTypeInProducts($type, $products)
    {
        if (count($products) > 0) {
            foreach ($products as $product) {
                if (isset($product['type']) == $type) {
                    return true;
                }
            }
        }

        return false;
    }

    public function addItemToOrder($newOrderItem, $order)
    {
        $actualOffer = $this->commonGroundService->getResource($newOrderItem['offer']);

        if ($this->checkIfIsPersonalTicket($actualOffer) == true &&
            $newOrderItem['quantity'] > 1) {
            $newOrderItem['quantity'] = 1;
        }

        $order['orderItems'][] = [
            'offer'    => $newOrderItem['offer'],
            'quantity' => $newOrderItem['quantity'],
            'path'     => $newOrderItem['path'],
            'price'    => $actualOffer['price'],
        ];

        return $order;
    }

    public function makeNewOrder($newOrderItem)
    {
        $actualOffer = $this->commonGroundService->getResource($newOrderItem['offer']);

        $order['organization'] = $actualOffer['offeredBy'];
        $order = $this->addItemToOrder($newOrderItem, $order);

        return $order;
    }

    public function getOrderByOrganization($organization)
    {
        $ordersInSession = $this->session->get('orders');

        if (isset($ordersInSession)) {
            foreach ($ordersInSession as $order) {
                if ($order['organization'] == $organization) {
                    return $order;
                }
            }
        }

        return false;
    }

    public function uploadOrder($order, $person)
    {
        $uploadedOrder['name'] = 'Order for '.$person['name'];
        $uploadedOrder['description'] = 'Order for '.$person['name'];
        $uploadedOrder['organization'] = $order['organization'];
        $uploadedOrder['customer'] = $person['@id'];

        if ($this->request->get('remarks') != null or !empty($this->request->get('remarks'))) {
            $uploadedOrder['remarks'] = $this->request->get('remarks');
        }

        // Hardcoded org because of bug in PDC !
//        $uploadedOrder['organization'] = 'https://dev.larping.eu/api/v1/wrc/organizations/51eb5628-3b37-497b-a57f-6b039ec776e5';

        $uploadedOrder = $this->commonGroundService->saveResource($uploadedOrder, ['component' => 'orc', 'type' => 'orders']);

        //add user to
        $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $this->params->get('app_id')])['hydra:member'][0];
        $groups = $this->idVaultService->getGroups($provider['configuration']['app_id'], $order['organization'])['groups'];

        foreach ($groups as $group) {
            if ($group['name'] == 'clients' || $group['name'] == 'root' && !in_array($this->security->getUser()->getUsername(), $group['users'])) {
                $this->idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $this->security->getUser()->getUsername(), true);
            }
        }

        foreach ($order['orderItems'] as $item) {
            $offer = $this->commonGroundService->getResource($item['offer']);

            $item['name'] = $offer['name'];
            if (!isset($offer['description'])) {
                $item['description'] = $offer['name'];
            } else {
                $item['description'] = $offer['description'];
            }
            $item['quantity'] = intval($item['quantity']);
            $item['price'] = strval($offer['price']);
            $item['priceCurrency'] = $offer['priceCurrency'];
            $item['order'] = '/orders/'.$uploadedOrder['id'];
        }

        $item = $this->commonGroundService->saveResource($item, ['component' => 'orc', 'type' => 'order_items']);
        $uploadedOrder = $this->commonGroundService->getResource($uploadedOrder['@id']);

        $order['@id'] = $uploadedOrder['@id'];
        $ordersInSession = $this->session->get('orders');
        foreach ($ordersInSession as $k => $orderInSession) {
            if (isset($orderInSession['organization']) && $orderInSession['organization'] == $uploadedOrder['organization']) {
                $ordersInSession[$k] = $order;
            }
        }
        $this->session->set('orders', $ordersInSession);

        return $uploadedOrder;
    }

    public function removeOrderByInvoice($invoice)
    {
        $ordersInSession = $this->session->get('orders');
        if (isset($ordersInSession) && count($ordersInSession) > 0) {
            if (isset($invoice['order'])) {
                foreach ($ordersInSession as $k => $order) {
                    if (isset($order['@id']) && $order['@id'] == $invoice['order']) {
                        unset($ordersInSession[$k]);
                        $this->session->set('orders', $ordersInSession);

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function removeItem($id)
    {
        $order = $this->session->get('order');
        if (isset($order) && isset($order['items'])) {
            foreach ($order['items'] as $key => $item) {
                if ($item['id'] == $id) {
                    unset($order['items'][$key]);

                    // If we have saved the order we also update it when removing a item
                    if (isset($order['@id'])) {
                        $order = $this->commonGroundService->saveResource($order, $order['@id']);
                    }
                    $this->session->set('order', $order);
                }
            }
        }

        return $order;
    }

    public function ownsThisProduct($product)
    {
        $thisProductIsOwned = false;

        // Checks if required product is in one of the session orders
        $orders = $this->session->get('orders');
        if (isset($orders) && count($orders) > 0) {
            foreach ($orders as $order) {
                if (isset($order['orderItems']) && count($order['orderItems']) > 0) {
                    foreach ($order['orderItems'] as $item) {
                        if (isset($item['offer'])) {
                            $offer = $this->commonGroundService->getResource($item['offer']);

                            if (isset($offer['products']) && count($offer['products']) > 0) {
                                foreach ($offer['products'] as $ownedProduct) {
                                    if ($product['id'] == $ownedProduct['id']) {
                                        $thisProductIsOwned = true;

                                        return $thisProductIsOwned;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Checks if required product is in array of owned products | Needs to be logged in
        if ($thisProductIsOwned == false && $this->security->getUser() && $this->security->getUser()->getPerson()) {
            // Fetches owned products
            $ownedProducts = $this->getOwnedProducts($this->security->getUser()->getPerson());
            if (isset($ownedProducts) && count($ownedProducts) > 0) {
                foreach ($ownedProducts as $ownedProduct) {
                    if ($ownedProduct['id'] == $product['id']) {
                        $thisProductIsOwned = true;
                    }
                }
            }
        }

        return $thisProductIsOwned;
    }

    public function getOwnedProducts($person)
    {
        $orders = $this->commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['customer' => $person])['hydra:member'];
        $orderItemIds = [];
        $ownedProducts = [];

        // Get all order items of the given person
        foreach ($orders as $order) {
            if (isset($order['items']) && count($order['items']) > 0) {
                foreach ($order['items'] as $item) {
                    if (!in_array($item['id'], $orderItemIds)) {
                        $orderItems[] = $item;
                        $orderItemIds[] = $item['id'];
                    }
                }
            }
        }

        // Get all ownedProducts of the given person
        $productIds = [];
        if (isset($orderItems) && count($orderItems) > 0) {
            foreach ($orderItems as $item) {
                if (isset($item['offer'])) {
                    $offer = $this->commonGroundService->getResource($item['offer']);
                    if (isset($offer['products']) && count($offer['products']) > 0) {
                        foreach ($offer['products'] as $product) {
                            if (!in_array($product['id'], $productIds)) {
                                $ownedProducts[] = $product;
                                $productIds[] = $product['id'];
                            }
                        }
                    }
                }
            }
        }

        return $ownedProducts;
    }
}
