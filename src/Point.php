<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Point
{
    public $x;
    public $y;

    public function __construct($x = null, $y = null)
    {
        if (null !== $x) {
            $this->x = $x;
        }
        if (null !== $y) {
            $this->y = $y;
        }
    }
}
