<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Bitmap
{
    /**
    * @var int
    */
    public $w = 0;

    /**
    * @var int
    */
    public $h = 0;

    /**
    * @var int
    */
    public $size;

    /**
    * @var int[]
    */
    public $data = [];

    public function __construct(int $w, int $h)
    {
        $this->w = $w;
        $this->h = $h;
        $this->size = $w * $h;
    }

    public function at(int $x, int $y) : bool
    {
        return
            (
                $x >= 0 &&
                $x < $this->w &&
                $y >= 0 &&
                $y < $this->h
            ) &&
            1 === $this->data[$this->w * $y + $x];
    }

    public function index(int $i) : Point
    {
        $point = new Point();
        $point->y = floor($i / $this->w);
        $point->x = $i - $point->y * $this->w;

        return $point;
    }

    public function flip(float $x, float $y)
    {
        if ($this->at((int) $x, (int) $y)) {
            $this->data[(int) ($this->w * $y + $x)] = 0;
        } else {
            $this->data[(int) ($this->w * $y + $x)] = 1;
        }
    }
}
