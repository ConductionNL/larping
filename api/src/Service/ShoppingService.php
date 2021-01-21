<?php

// src/Service/BRPService.php

namespace App\Service;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\IdVaultBundle\Service\IdVaultService;
use Exception;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;


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
    private $router;

    public function __construct(
        ParameterBagInterface $params,
        CacheInterface $cache,
        SessionInterface $session,
        FlashBagInterface $flash,
        RequestStack $requestStack,
        CommonGroundService $commonGroundService,
        Security $security,
        IdVaultService $idVaultService,
        RouterInterface $router
    )
    {
        $this->params = $params;
        $this->cash = $cache;
        $this->session = $session;
        $this->flash = $flash;
        $this->request = $requestStack->getCurrentRequest();
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
        $this->idVaultService = $idVaultService;
        $this->router = $router;
    }

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
            header('Location: ' . $object['paymentUrl']);
            exit;
        }
    }

    public function addItemsToCart($offers, $redirectUrl)
    {
        $ordersInSession = $this->session->get('orders');

        // Checks for nonexisting objects
        if ($this->checkForBrokenObjects($offers) === true) {
            $this->flash->add('danger', 'there is a problem with certain data');

            return false;
        }

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
                    $actualOffer = $this->commonGroundService->getResource($newOrderItem['offer']);
                    // Check if is personal ticket
                    if ($this->checkForTypeInProducts('ticket', $actualOffer['products']) == true &&
                        isset($actualOffer['audience']) && $actualOffer['audience'] == 'personal' &&
                        $newOrderItem['quantity'] + $order['orderItems'][$key]['quantity'] > 1) {
                        $order['orderItems'][$key]['quantity'] = 1;
                    } else {
                        $order['orderItems'][$key]['quantity'] += $newOrderItem['quantity'];
                    }
                    if (isset($newOrderItem['options']) && count($newOrderItem['options']) > 0) {
                        $order['orderItems'][$key]['options'] = $newOrderItem['options'];
                    }
                }
            }
        }

        return $order;
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
        foreach ($products as $product) {
            if (isset($product['type']) == $type) {
                return true;
            }
        }

        return false;
    }

    public function addItemToOrder($newOrderItem, $order)
    {
        $actualOffer = $this->commonGroundService->getResource($newOrderItem['offer']);

        if ($this->checkForTypeInProducts('ticket', $actualOffer['products']) == true &&
            isset($actualOffer['audience']) && $actualOffer['audience'] == 'personal' &&
            $newOrderItem['quantity'] > 1) {
            $newOrderItem['quantity'] = 1;
        }

//        if (!isset($newOrderItem['options'])) {
        $newOrderItem['price'] = $actualOffer['price'];
//        } else {
//            foreach ($newOrderItem['options'] as $option) {
//                $newOrderItem['price'] = $actualOffer['price'] + intval($option['price']);
//            }
//        }

        $order['orderItems'][] = $newOrderItem;

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
        if ($this->checkForBrokenObjects($person) == true ||
            $this->checkForBrokenObjects($order['orderItems']) == true) {
            $this->flash->add('danger', 'there is a problem with certain data');

            return false;
        }

        $uploadedOrder['name'] = 'Order for ' . $person['name'];
        $uploadedOrder['description'] = 'Order for ' . $person['name'];
        $uploadedOrder['organization'] = $order['organization'];
        $uploadedOrder['customer'] = $person['@id'];

        if ($this->request->get('remarks') != null or !empty($this->request->get('remarks'))) {
            $uploadedOrder['remarks'] = $this->request->get('remarks');
        }

        $uploadedOrder = $this->commonGroundService->saveResource($uploadedOrder, ['component' => 'orc', 'type' => 'orders']);

        //add user to clients group
        $provider = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'id-vault', 'application' => $this->params->get('app_id')])['hydra:member'][0];
        $groups = $this->idVaultService->getGroups($provider['configuration']['app_id'], $order['organization'])['groups'];

        foreach ($groups as $group) {
            if ($group['name'] == 'clients' || $group['name'] == 'root' && !in_array($this->security->getUser()->getUsername(), $group['users'])) {
                $this->idVaultService->inviteUser($provider['configuration']['app_id'], $group['id'], $this->security->getUser()->getUsername(), true);
            }
        }

        foreach ($order['orderItems'] as $item) {
            $offer = $this->commonGroundService->getResource($item['offer']);

            $offer['products'][0]['org'] = 'https://dev.larping.eu/api/v1/pdc/1235456';
            if ($this->checkForBrokenObjects($offer) == true ||
                $this->checkForBrokenObjects($offer['products']) == true) {
                $this->flash->add('danger', 'there is a problem with certain data');

                return false;
            }

            $item['name'] = $offer['name'];
            if (!isset($offer['description'])) {
                $item['description'] = $offer['name'];
            } else {
                $item['description'] = $offer['description'];
            }
            $item['quantity'] = intval($item['quantity']);

            $item['price'] = strval($offer['price']);
            if (isset($item['options'])) {
                foreach ($item['options'] as $option) {
                    $item['price'] = strval(intval($item['price']) + intval($option['price']));
                }
            }
            $item['priceCurrency'] = $offer['priceCurrency'];
            $item['order'] = '/orders/' . $uploadedOrder['id'];
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

    public function removeItem($offer)
    {
        $ordersInSession = $this->session->get('orders');

        if (isset($ordersInSession)) {
            foreach ($ordersInSession as $k1 => $order) {
                foreach ($order['orderItems'] as $k2 => $item) {
                    if ($item['offer'] == $offer) {
                        unset($ordersInSession[$k1]['orderItems'][$k2]);
                        if (!array_filter($ordersInSession[$k1]['orderItems'])) {
                            unset($ordersInSession[$k1]);
                            if (!array_filter($ordersInSession)) {
                                unset($ordersInSession);
                            }
                        }
                        if (isset($ordersInSession)) {
                            $this->session->set('orders', $ordersInSession);
                        } else {
                            $this->session->set('orders', null);
                        }

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ownsThisProduct($product)
    {
        $thisProductIsOwned = false;

        // Checks if required product is in one of the session orders
        // @todo kijken naar fix voor minder loops
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
        $orderItems = $this->commonGroundService->getResourceList(['component' => 'orc', 'type' => 'order_items'], ['order.customer' => $person])['hydra:member'];
        $ownedProducts = [];

        // Get all ownedProducts of the given person
        $productIds = [];
        if (isset($orderItems) && count($orderItems) > 0) {
            foreach ($orderItems as $item) {
                if (isset($item['offer'])) {
                    $offer = $this->commonGroundService->getResource($item['offer']);
                    if (isset($offer['products']) && count($offer['products']) > 0) {
                        foreach ($offer['products'] as $product) {
                            if (!in_array($product['id'], $productIds)) {
                                $product['orderItem'] = $item;
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

    public function removeOption($removedOption)
    {
        $ordersInSession = $this->session->get('orders');
        foreach ($ordersInSession as $k1 => $order) {
            foreach ($order['orderItems'] as $k2 => $item) {
                if ($item['offer'] == $removedOption['offer']) {
                    if (isset($item['options'])) {
                        foreach ($item['options'] as $k3 => $option) {
                            if ($removedOption['name'] == $option['name']) {
                                unset($ordersInSession[$k1]['orderItems'][$k2]['options'][$k3]);
                                if (!array_filter($ordersInSession[$k1]['orderItems'][$k2]['options'])) {
                                    unset($ordersInSession[$k1]['orderItems'][$k2]['options']);
                                }
                                $this->session->set('orders', $ordersInSession);

                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function checkForBrokenObjects($objects)
    {
        // Usefull for testing
//        $objects[0]['org'] = 'https://dev.larping.eu/api/v1/orc/order/123523';

        if (isset($objects)) {
            if (is_array($objects)) {
                foreach ($objects as $properties) {
                    if (is_array($properties)) {
                        foreach ($properties as $property) {
                            if (!is_array($property) && strpos($property, 'https')  !== false && strpos($property, '/api/v1/') !== false && $this->commonGroundService->isResource($property) == false) {
                                return true;
                            } elseif (is_array($property)) {
                                foreach ($property as $prop) {
                                    if (!is_array($prop) && strpos($prop, 'https') !== false && strpos($prop, '/api/v1/') !== false && $this->commonGroundService->isResource($prop) == false) {
                                        return true;
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif (!is_array($objects) && strpos($objects, 'https') && strpos($objects, '/api/v1/') && $this->commonGroundService->isResource($objects) != false) {
                return true;
            }

        }

        return false;
    }

}
