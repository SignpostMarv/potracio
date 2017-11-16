<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Sum
{
    public $x;
    public $y;
    public $xy;
    public $x2;
    public $y2;

    public function __construct($x, $y, $xy, $x2, $y2)
    {
        $this->x = $x;
        $this->y = $y;
        $this->xy = $xy;
        $this->x2 = $x2;
        $this->y2 = $y2;
    }
}
