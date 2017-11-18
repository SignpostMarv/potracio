<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Path
{
    /**
    * @var int
    */
    public $area = 0;

    /**
    * @var int
    */
    public $len = 0;

    /**
    * @var Curve|null
    */
    public $curve;

    /**
    * @var Point[]
    */
    public $pt = [];

    /**
    * @var float
    */
    public $minX = 100000;
    /**
    * @var float
    */
    public $minY = 100000;
    /**
    * @var float
    */
    public $maxX = -1;
    /**
    * @var float
    */
    public $maxY = -1;

    /**
    * @var mixed[]
    */
    public $sum = [];

    /**
    * @var int[]
    */
    public $lon = [];

    /**
    * @var string
    */
    public $sign = '';

    /**
    * @var int
    */
    public $m = 0;

    /**
    * @var int[]
    */
    public $po = [];

    /**
    * @var float
    */
    public $x0 = 0;

    /**
    * @var float
    */
    public $y0 = 0;

    /**
    * @var Point[]
    */
    public $pt = [];

    /**
    * @var int
    */
    public $len = 0;

    /**
    * @var Sum[]
    */
    public $sums = [];

    /**
    * @var int[]
    */
    public $lon = [];
}
