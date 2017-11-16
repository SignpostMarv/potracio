<?php
namespace Potracio;

class Path{
    public $area = 0;
    public $len = 0;
    public $curve = array();
    public $pt = array();
    public $minX = 100000;
    public $minY = 100000;
    public $maxX= -1;
    public $maxY = -1;
    public $sum = array();
    public $lon = array();
}
