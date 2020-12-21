<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
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
    public function indexAction(Session $session, CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];
        if ($session->get('order')) {
            $variables['order'] = $session->get('order');

            if (!isset($variables['order']['@id']) && $this->getUser()) {
                $person = $commonGroundService->getResource($this->getUser()->getPerson());

                if (isset($variables['order']['items'][0])) {
                    $offer = $commonGroundService->getResource($variables['order']['items'][0]['offer']);
                }

                $order['name'] = 'Order for ' . $person['name'];
                $order['description'] = 'Order for ' . $person['name'];
                $order['organization'] = $offer['offeredBy'];
                $order['customer'] = $person['@id'];

                $order = $commonGroundService->saveResource($order, ['component' => 'orc', 'type' => 'orders']);

                foreach ($variables['order']['items'] as $item) {
                    $offer = $commonGroundService->getResource($item['offer']);

                    $orderItem['name'] = $offer['name'];
                    $orderItem['description'] = $offer['description'];
                    $orderItem['quantity'] = intval($item['quantity']);
                    $orderItem['price'] = strval($item['price'] / $item['quantity']);
                    $orderItem['priceCurrency'] = 'EUR';
                    $orderItem['order'] = '/orders/' . $order['id'];
                    $orderItem['offer'] = $item['offer'];

                    $orderItem = $commonGroundService->saveResource($orderItem, ['component' => 'orc', 'type' => 'order_items']);

                    $order['items'][] = '/order_items/' . $orderItem['id'];
                }

                $order = $commonGroundService->saveResource($order, $order['@id']);

                $variables['order']['@id'] = $order['@id'];
                $variables['order']['id'] = $order['id'];
                $session->set('order', $order);
            }
        }

        // Make order
        if ($request->isMethod('POST') && $request->request->get('makeOrder') == 'true' && $this->getUser()) {

            $object['url'] = $variables['order']['@id'];
            $object['mollieKey'] = 'test_e56eJtnShswQS7Usn7uDhsheg9fjeH';

            if ($_ENV['APP_ENV'] != 'dev') {
                $object['redirectUrl'] = 'https://larping.eu/order/payment';
            } else {
                $object['redirectUrl'] = 'http://localhost/order/payment';
            }
            var_dump($object['redirectUrl']);

            $object = $commonGroundService->saveResource($object, ['component' => 'bc', 'type' => 'order']);

            var_dump($object['paymentUrl']);
            if (isset($object['paymentUrl']) && strpos($object['paymentUrl'], 'https://www.mollie.com') !== false) {
                $session->set('invoice', $object);
                header("Location: " . $object['paymentUrl']);
                die;
            }
        }

        return $variables;
    }

    /**
     * @Route("/payment")
     * @Template
     */
    public function paymentAction(Session $session, CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        if ($session->get('invoice')) {
            $variables['invoice'] = $session->get('invoice');
        } else {
            return $this->redirectToRoute('app_order_index');
        }

        return $variables;
    }
}
