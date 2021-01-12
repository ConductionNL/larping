<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The OrderController handles any calls about orders.
 *
 * Class OrderController
 *
 * @Route("/invoices")
 */
class InvoiceController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params)
    {
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function invoiceAction(Session $session, CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, ParameterBagInterface $params, $id)
    {
        $variables['invoice'] = [];

        return $variables;
    }
}
