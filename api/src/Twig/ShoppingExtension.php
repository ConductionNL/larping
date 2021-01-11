<?php

// src/Twig/CommonGroundExtension.php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ShoppingExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            // the logic of this filter is now implemented in a different class
            new TwigFunction('owns_this_product', [ShoppingRuntime::class, 'ownsThisProduct']),
        ];
    }
}
