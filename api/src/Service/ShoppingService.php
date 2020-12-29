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
                    $isAlreadyInCart = $this->checkIfInCart($offer, $order);
                    $order = $this->session->get('order');
                } else {
                    $isAlreadyInCart = false;
                }
                if ($isAlreadyInCart !== true) {
                    $actualOffer = $this->commonGroundService->getResource($offer['@id']);
                    $order['items'][] = [
                        'offer' => $offer['@id'],
                        'quantity' => $offer['quantity'],
                        'path' => '/events/' . $offer['path'],
                        'price' => $actualOffer['price'] * $offer['quantity']
                    ];
                }
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
                $order['items'][$key] = $item;
                $order['items'][$key]['price'] = $actualOffer['price'] * $item['quantity'];
                $this->session->set('order', $order);

                return true;
            }
        }

        return false;
    }
}
