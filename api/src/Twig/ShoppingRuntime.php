<?php

// src/Twig/ShoppingController.php

namespace App\Twig;

use App\Service\ShoppingService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

class ShoppingRuntime implements RuntimeExtensionInterface
{
    private $ss;
    private $params;
    private $router;

    public function __construct(ShoppingService $ss, ParameterBagInterface $params, RouterInterface $router)
    {
        $this->ss = $ss;
        $this->params = $params;
        $this->router = $router;
    }

    public function ownsThisProduct($product)
    {
        return $this->ss->ownsThisProduct($product);
    }
}
