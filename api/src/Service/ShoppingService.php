<?php

// src/Service/BRPService.php

namespace App\Service;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use GuzzleHttp\Client;
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

    public function __construct(
        ParameterBagInterface $params,
        CacheInterface $cache,
        SessionInterface $session,
        FlashBagInterface $flash,
        RequestStack $requestStack,
        CommonGroundService $commonGroundService,
        Security $security)
    {
        $this->params = $params;
        $this->cash = $cache;
        $this->session = $session;
        $this->flash = $flash;
        $this->request = $requestStack->getCurrentRequest();
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
    }

    public function makeOrder($person)
    {
        if ($this->session->get('order')) {
            $order = $this->session->get('order');

            if (!isset($variables['order']['@id'])) {
                $person = $this->commonGroundService->getResource($person);


                if (isset($order['items']) && count($order['items']) > 0) {
                    $offer = $this->commonGroundService->getResource($order['items'][0]['offer']);

                    $items = $order['items'];

//                $sessionOrder = $order;

                    $order['name'] = 'Order for ' . $person['name'];
                    $order['description'] = 'Order for ' . $person['name'];
                    $order['organization'] = $offer['offeredBy'];
                    $order['customer'] = $person['@id'];

                    if (isset($order['items'])) {
                        unset($order['items']);
                    }

                    $order = $this->commonGroundService->saveResource($order, ['component' => 'orc', 'type' => 'orders']);

                    foreach ($items as $item) {
                        $offer = $this->commonGroundService->getResource($item['offer']);

                        if (!isset($item['@id'])) {
                            $item['name'] = $offer['name'];
                            if (!isset($offer['description'])) {
                                $item['description'] = $offer['name'];
                            } else {
                                $item['description'] = $offer['description'];
                            }
                            $item['quantity'] = intval($item['quantity']);
                            $item['price'] = strval($offer['price']);
                            $item['priceCurrency'] = $offer['priceCurrency'];
                            $item['order'] = '/orders/' . $order['id'];
                        }
                        $item = $this->commonGroundService->saveResource($item, ['component' => 'orc', 'type' => 'order_items']);
                    }
                    $order = $this->commonGroundService->getResource($order['@id']);
                }
                $this->session->set('order', $order);
            }
        }
        return $order;
    }

    public function redirectToMollie($order)
    {

        $object['url'] = $order['@id'];
        $object['mollieKey'] = 'test_e56eJtnShswQS7Usn7uDhsheg9fjeH';

        if ($_ENV['APP_ENV'] != 'dev') {
            $object['redirectUrl'] = 'https://larping.eu/order/payment';
        } else {
            $object['redirectUrl'] = 'https://dev.larping.eu/order/payment';
        }

        // Only enable on localhost ! Dont forget to disable before pushing !
//        $object['redirectUrl'] = 'https://localhost/order/payment';

        $object = $this->commonGroundService->saveResource($object, ['component' => 'bc', 'type' => 'order']);

        if (isset($object['paymentUrl']) && strpos($object['paymentUrl'], 'https://www.mollie.com') !== false) {
            $this->session->set('invoice@id', $object['@id']);
            header("Location: " . $object['paymentUrl']);
            die;
        }
    }


    public function addItemsToCart($items)
    {
        // Check if we already have an order in the session
        if ($this->session->get('order')) {
            $order = $this->session->get('order');
        } else {
            $order = [];
        }

        foreach ($items as $offer) {
            if (!isset($offer['quantity']) || !$offer['quantity']) {
                $offer['quantity'] = 1;
            }
            if (!$offer['quantity'] == 0 && isset($offer['@id']) && isset($offer['path'])) {
                if (isset($order) && isset($order['items'])) {
                    if ($this->checkIfInCart($offer, $order) == true) {
                        $order = $this->session->get('order');
                        continue;
                    }
                }

                $actualOffer = $this->commonGroundService->getResource($offer['@id']);

                // Add item to cart
                $order['items'][] = [
                    'offer' => $offer['@id'],
                    'quantity' => $offer['quantity'],
                    'path' => '/events/' . $offer['path'],
                    'price' => $actualOffer['price'],
//                    'id' => basename($offer['@id']) . PHP_EOL
                ];
            }
        }

        // Set order in the session
        $this->session->set('order', $order);

        return $order;
    }

    public function checkIfInCart($offer, $order)
    {
        foreach ($order['items'] as $key => $item) {
            if ($offer['@id'] == $item['offer']) {

                $actualOffer = $this->commonGroundService->getResource($offer['@id']);
                $item['quantity'] += $offer['quantity'];
                if (isset($item['@id']) && isset($order['@id'])) {
                    $item = $this->commonGroundService->saveResource($item, ['component' => 'orc', 'type' => 'order_items']);
                    $order = $this->commonGroundService->getResource($order['@id']);
                } else {
                    $order['items'][$key] = $item;
                    $order['items'][$key]['price'] = $actualOffer['price'] * $item['quantity'];
                }

                $this->session->set('order', $order);
                return true;
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

        // Checks if required product is in session order
        $order = $this->session->get('order');
        if (isset($order['items']) && count($order['items']) > 0) {
            foreach ($order['items'] as $item) {
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

    function getOwnedProducts($person)
    {
        $orders = $this->commonGroundService->getResourceList(['component' => 'orc', 'type' => 'order_items'], ['customer' => $person]);
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
                    if (isset($offer['products']) && count($offer['products']) > 0)
                        foreach ($offer['products'] as $product) {
                            if (!in_array($product['id'], $productIds)) {
                                $ownedProducts[] = $product;
                                $productIds[] = $product['id'];
                            }
                        }
                }
            }
        }

        return $ownedProducts;
    }
}
