<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Sum
{
    /**
    * @var float
    */
    public $x;

    /**
    * @var float
    */
    public $y;

    /**
    * @var float
    */
    public $xy;

    /**
    * @var float
    */
    public $x2;

    /**
    * @var float
    */
    public $y2;

    public function __construct(
        float $x,
        float $y,
        float $xy,
        float $x2,
        float $y2
    ) {
        $this->x = $x;
        $this->y = $y;
        $this->xy = $xy;
        $this->x2 = $x2;
        $this->y2 = $y2;
    }
}
