<?php

// src/Controller/LocationController.php

namespace App\Controller;

use App\Service\MailingService;
use App\Service\ShoppingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The LocationController handles any calls about locations.
 *
 * Class EventController
 *
 * @Route("/locations")
 */
class LocationController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService)
    {
        $variables = [];
        $variables['locations'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'lc']);
        $variables['categories'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'categories']);

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function locationAction(CommonGroundService $commonGroundService, $id)
    {
        $variables = [];
        $variables['location'] = $commonGroundService->getResource(['component' => 'lc', 'type' => 'lc','id'=>$id]);

        return $variables;
    }
}
