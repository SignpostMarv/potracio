<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Opti
{
    /**
    * @var float
    */
    public $pen = 0;

    /**
    * @var Point[]
    */
    public $c;

    /**
    * @var float
    */
    public $t = 0;

    /**
    * @var float
    */
    public $s = 0;

    /**
    * @var float
    */
    public $alpha = 0;

    public function __construct()
    {
        $this->c = [new Point(), new Point()];
    }
}
