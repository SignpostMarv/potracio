<?php
namespace Potracio;

class Point{
    public $x;
    public $y;

    public function __construct($x=NULL, $y=NULL) {
        if($x !== NULL)
            $this->x = $x;
        if($y !== NULL)
            $this->y = $y;
    }
}
