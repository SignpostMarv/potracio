<?php

declare(strict_types=1);

namespace Potracio;

class Quad
{
    public $data = [0, 0, 0, 0, 0, 0, 0, 0, 0];

    public function at($x, $y)
    {
        return $this->data[$x * 3 + $y];
    }
}
