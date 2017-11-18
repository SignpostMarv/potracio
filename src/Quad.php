<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Quad
{
    /**
    * @var int[]
    */
    public $data = [0, 0, 0, 0, 0, 0, 0, 0, 0];

    public function at(int $x, int $y) : int
    {
        return $this->data[$x * 3 + $y];
    }
}
