<?php
namespace Potracio;

class Quad{
    public $data = array(0,0,0,0,0,0,0,0,0);

    public function at($x, $y) {
        return $this->data[$x * 3 + $y];
    }
}
