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

class ShoppingService
{
    private $params;
    private $cache;
    private $session;
    private $flashBagInterface;
    private $request;
    private $commonGroundService;
    private $requestService;

    public function __construct(ParameterBagInterface $params, CacheInterface $cache, SessionInterface $session, FlashBagInterface $flash, RequestStack $requestStack, CommonGroundService $commonGroundService)
    {
        $this->params = $params;
        $this->cash = $cache;
        $this->session = $session;
        $this->flash = $flash;
        $this->request = $requestStack->getCurrentRequest();
        $this->commonGroundService = $commonGroundService;
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
            if (isset($order['items']) && $order['items'] > 0) {
                foreach ($order['items'] as $existingItem) {

                }
            }
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
                $order['items'][] = [
                    'offer' => $offer['@id'],
                    'quantity' => $offer['quantity'],
                    'path' => '/events/' . $offer['path'],
                    'price' => $actualOffer['price'] * $offer['quantity'],
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
                if (isset($item['@id'])) {
                    $item = $this->commonGroundService->saveResource($item, ['component' => 'orc', 'type' => 'order_items']);
                }
                $order['items'][$key] = $item;
                $order['items'][$key]['price'] = $actualOffer['price'] * $item['quantity'];
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

    public function ownsThisProduct($paidInvoices, $product)
    {
        $thisProductIsOwned = false;
        foreach ($paidInvoices as $inv) {
            if (isset($inv['items']) and count($inv['items']) > 0) {
                foreach ($inv['items'] as $itm) {
                    if (isset($itm['offer'])) {
                        $offer = $this->commonGroundService->getResource($itm['offer']);
                        if (isset($offer['products']) and count($offer['products']) > 0) {
                            foreach ($offer['products'] as $prod) {
                                if ($prod['@id'] == $product['@id']) {
                                    $thisProductIsOwned = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $thisProductIsOwned;
    }

    function getUserProducts()
    {
        $result = [];
        $orderItems = [];
        foreach ($orderItems as $item) {
            $offer = $this->commongroundservice->getResource($item['offer']);
            $result = array_merge($result, $offer['products']);
        }
        return $result;
    }
}
