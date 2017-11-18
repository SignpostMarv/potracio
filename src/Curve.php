<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

class Curve
{
    /**
    * @var int
    */
    public $n;

    /**
    * @var mixed[]
    */
    public $tag;

    /**
    * @var mixed[]
    */
    public $c;

    /**
    * @var float
    */
    public $alphaCurve = 0;

    /**
    * @var Point[]
    */
    public $vertex = [];

    /**
    * @var float[]
    */
    public $alpha;

    /**
    * @var float[]
    */
    public $alpha0;

    /**
    * @var (float|null)[]
    */
    public $beta;

    public function __construct(int $n)
    {
        $this->n = $n;
        $this->tag = array_fill(0, $n, null);
        $this->c = array_fill(0, $n * 3, null);

        /**
        * @var Point[] $zeros
        */
        $zeros = array_fill(0, $n, null);
        $this->vertex = $zeros;

        /**
        * @var float[] $zeros
        */
        $zeros = array_fill(0, $n, 0);
        $this->alpha = $zeros;

        /**
        * @var float[] $zeros
        */
        $zeros = array_fill(0, $n, 0);
        $this->alpha0 = $zeros;

        /**
        * @var (float|null)[]
        */
        $zeros = array_fill(0, $n, null);
        $this->beta = $zeros;
    }
}
