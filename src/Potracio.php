<?php
/**
* @author Otamay
* @author SignpostMarv
*/
declare(strict_types=1);

namespace Potracio;

/*    Potracio - Port by Otamay (2017) (https://github.com/Otamay/potracio.git)
 * A PHP Port of Potrace (http://potrace.sourceforge.net),
 * ported from https://github.com/kilobtye/potrace. Info below:
 *
 *    Copyright (C) 2001-2013 Peter Selinger.
 *
 * A javascript port of Potrace (http://potrace.sourceforge.net).
 *
 * Licensed under the GPL
 *
 * Usage
 *     loadImageFromFile(file) : load image from File, JPG for now
 *
 *     setParameter({para1: value, ...}) : set parameters
 *         parameters:
 *                turnpolicy ("black" / "white" / "left" / "right" / "minority" / "majority")
 *                    how to resolve ambiguities in path decomposition. (default: "minority")
 *                turdsize
 *                    suppress speckles of up to this size (default: 2)
 *                optcurve (true / false)
 *                    turn on/off curve optimization (default: true)
 *                alphamax
 *                    corner threshold parameter (default: 1)
 *                opttolerance
 *                    curve optimization tolerance (default: 0.2)
 *
 *     getSVG(size, opt_type) : return a string of generated SVG image.
 *                                                                        result_image_size = original_image_size * size
 *                                                                        optional parameter opt_type can be "curve"
 */

class Potracio
{
    /**
    * @var null|Bitmap
    */
    public $bm = null;

    /**
    * @var Path[]
    */
    public $pathlist = [];

    /**
    * @var mixed[]
    */
    public $info = [
        'turnpolicy' => 'minority',
        'turdsize' => 2,
        'optcurve' => true,
        'alphamax' => 1,
        'opttolerance' => 0.2,
    ];

    public function __construct()
    {
        $this->info = $this->info;
    }

    public function setParameter(array $data)
    {
        $this->info = array_merge((array) $this->info, $data);
    }

    public function loadImageFromFile(string $file)
    {
        $image = imagecreatefromjpeg($file);
        list($w, $h) = getimagesize($file);

        $this->bm = new Bitmap($w, $h);

        for ($i = 0; $i < $h; ++$i) {
            for ($j = 0; $j < $w; ++$j) {
                $rgb = imagecolorat($image, $j, $i);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $color = (0.2126 * $r) + (0.7153 * $g) + (0.0721 * $b);
                $this->bm->data[] = $color < 128 ? 1 : 0;
            }
        }
    }

    public function process()
    {
        $this->bmToPathlist();
        $this->processPath();
    }

    public function clear()
    {
        $this->bm = null;
        $this->pathlist = [];
    }

    public function getSVG(int $size, string $opt_type = '') : string
    {
        /**
        * @var Bitmap $bm
        */
        $bm = &$this->bm;
        $pathlist = &$this->pathlist;
        $path = function (Curve $curve) use ($size) : string {
            $bezier = function (int $i) use ($curve, $size) : string {
                $b =
                    'C ' .
                    number_format($curve->c[$i * 3 + 0]->x * $size, 3) .
                    ' ' .
                    number_format($curve->c[$i * 3 + 0]->y * $size, 3) .
                    ',';
                $b .=
                    number_format($curve->c[$i * 3 + 1]->x * $size, 3) .
                    ' ' .
                    number_format($curve->c[$i * 3 + 1]->y * $size, 3) .
                    ',';
                $b .=
                    number_format($curve->c[$i * 3 + 2]->x * $size, 3) .
                    ' ' .
                    number_format($curve->c[$i * 3 + 2]->y * $size, 3) .
                    ' ';

                return $b;
            };

            $segment = function (int $i) use ($curve, $size) : string {
                $s =
                    'L ' .
                    number_format($curve->c[$i * 3 + 1]->x * $size, 3) .
                    ' ' .
                    number_format($curve->c[$i * 3 + 1]->y * $size, 3) .
                    ' ';
                $s .=
                    number_format($curve->c[$i * 3 + 2]->x * $size, 3) .
                    ' ' .
                    number_format($curve->c[$i * 3 + 2]->y * $size, 3) .
                    ' ';

                return $s;
            };

            $n = $curve->n;
            $p =
                'M' .
                number_format($curve->c[($n - 1) * 3 + 2]->x * $size, 3) .
                ' ' .
                number_format($curve->c[($n - 1) * 3 + 2]->y * $size, 3) .
                ' ';

            for ($i = 0; $i < $n; ++$i) {
                if ('CURVE' === $curve->tag[$i]) {
                    $p .= $bezier($i);
                } elseif ('CORNER' === $curve->tag[$i]) {
                    $p .= $segment($i);
                }
            }
            //p +=
            return $p;
        };

        $w = $bm->w * $size;
        $h = $bm->h * $size;
        $len = count($pathlist);

        $svg =
            '<svg id="svg" version="1.1" width="' .
            $w .
            '" height="' .
            $h .
            '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<path d="';
        for ($i = 0; $i < $len; ++$i) {
            /**
            * @var Curve $c
            */
            $c = $pathlist[$i]->curve;
            $svg .= $path($c);
        }
        if ('curve' === $opt_type) {
            $strokec = 'black';
            $fillc = 'none';
            $fillrule = '';
        } else {
            $strokec = 'none';
            $fillc = 'black';
            $fillrule = ' fill-rule="evenodd"';
        }
        $svg .=
            '" stroke="' .
            $strokec .
            '" fill="' .
            $fillc .
            '"' .
            $fillrule .
            '/></svg>';

        return $svg;
    }

    private function bmToPathlist()
    {
        $info = $this->info;
        /**
        * @var Bitmap $bm
        **/
        $bm = &$this->bm;
        $bm1 = clone $bm;
        $currentPoint = new Point(0, 0);

        $findNext =
            /**
            * @return int|Point
            */
            function (Point $point) use ($bm1) {
                $i = (int) ($bm1->w * $point->y + $point->x);
                while ($i < $bm1->size && 1 !== $bm1->data[$i]) {
                    ++$i;
                }
                if ($i < $bm1->size) {
                    return $bm1->index($i);
                }

                return 0;
            };

        $majority = function (float $x, float $y) use ($bm1) : int {
            for ($i = 2; $i < 5; ++$i) {
                $ct = 0;
                for ($a = -$i + 1; $a <= $i - 1; ++$a) {
                    $ct +=
                        $bm1->at(
                            (int) ($x + $a),
                            (int) ($y + $i - 1)
                        )
                            ? 1
                            : -1;
                    $ct +=
                        $bm1->at(
                            (int) ($x + $i - 1),
                            (int) ($y + $a - 1)
                        )
                            ? 1
                            : -1;
                    $ct +=
                        $bm1->at(
                            (int) ($x + $a - 1),
                            (int) ($y - $i)
                        )
                            ? 1
                            : -1;
                    $ct +=
                        $bm1->at(
                            (int) ($x - $i),
                            (int) ($y + $a)
                        )
                            ? 1
                            : -1;
                }
                if ($ct > 0) {
                    return 1;
                } elseif ($ct < 0) {
                    return 0;
                }
            }

            return 0;
        };

        $findPath = function (
            Point $point
        ) use (
            $bm,
            $bm1,
            $majority,
            $info
        ) : Path {
            $path = new Path();
            $x = $point->x;
            $y = $point->y;
            $dirx = 0;
            $diry = 1;

            $path->sign = $bm->at((int) $point->x, (int) $point->y) ? '+' : '-';

            while (1) {
                $path->pt[] = new Point($x, $y);
                if ($x > $path->maxX) {
                    $path->maxX = $x;
                }
                if ($x < $path->minX) {
                    $path->minX = $x;
                }
                if ($y > $path->maxY) {
                    $path->maxY = $y;
                }
                if ($y < $path->minY) {
                    $path->minY = $y;
                }
                ++$path->len;

                $x += $dirx;
                $y += $diry;
                $path->area -= $x * $diry;

                if ($x === $point->x && $y === $point->y) {
                    break;
                }

                $l = $bm1->at(
                    (int) ($x + ($dirx + $diry - 1) / 2),
                    (int) ($y + ($diry - $dirx - 1) / 2)
                );
                $r = $bm1->at(
                    (int) ($x + ($dirx - $diry - 1) / 2),
                    (int) ($y + ($diry + $dirx - 1) / 2)
                );

                if ($r && ! $l) {
                    if (
                        'right' === $info['turnpolicy'] ||
                        (
                            'black' === $info['turnpolicy'] &&
                            '+' === $path->sign
                        ) ||
                        (
                            'white' === $info['turnpolicy'] &&
                            '-' === $path->sign
                        ) ||
                        (
                            'majority' === $info['turnpolicy'] &&
                            $majority($x, $y)
                        ) ||
                        (
                            'minority' === $info['turnpolicy'] &&
                            ! $majority($x, $y)
                        )
                    ) {
                        $tmp = $dirx;
                        $dirx = -$diry;
                        $diry = $tmp;
                    } else {
                        $tmp = $dirx;
                        $dirx = $diry;
                        $diry = -$tmp;
                    }
                } elseif ($r) {
                    $tmp = $dirx;
                    $dirx = -$diry;
                    $diry = $tmp;
                } elseif ( ! $l) {
                    $tmp = $dirx;
                    $dirx = $diry;
                    $diry = -$tmp;
                }
            }

            return $path;
        };

        $xorPath = function (Path $path) use (&$bm1) {
            $y1 = $path->pt[0]->y;
            $len = $path->len;

            for ($i = 1; $i < $len; ++$i) {
                $x = $path->pt[$i]->x;
                $y = $path->pt[$i]->y;

                if ($y !== $y1) {
                    $minY = $y1 < $y ? $y1 : $y;
                    $maxX = $path->maxX;
                    for ($j = $x; $j < $maxX; ++$j) {
                        $bm1->flip($j, $minY);
                    }
                    $y1 = $y;
                }
            }
        };

        while ($currentPoint = $findNext($currentPoint)) {
            /**
            * @var Point $currentPoint
            */
            $currentPoint = $currentPoint;
            $path = $findPath($currentPoint);

            $xorPath($path);

            if ($path->area > $info['turdsize']) {
                $this->pathlist[] = $path;
            }
        }
    }

    private function processPath()
    {
        $info = $this->info;

        $mod = function (float $a, int $n) : float {
            return
                $a >= $n
                    ? $a % $n
                    : ($a >= 0 ? $a : $n - 1 - (-1 - $a) % $n);
        };

        $xprod = function (Point $p1, Point $p2) : float {
            return $p1->x * $p2->y - $p1->y * $p2->x;
        };

        $cyclic = function (float $a, float $b, float $c) : bool {
            if ($a <= $c) {
                return $a <= $b && $b < $c;
            }

            return $a <= $b || $b < $c;
        };

        $sign = function (float $i) : int {
            return $i > 0 ? 1 : ($i < 0 ? -1 : 0);
        };

        $quadform = function (Quad $Q, Point $w) : float {
            $v = array_fill(0, 3, null);

            $v[0] = $w->x;
            $v[1] = $w->y;
            $v[2] = 1;
            $sum = 0.0;

            for ($i = 0; $i < 3; ++$i) {
                for ($j = 0; $j < 3; ++$j) {
                    $sum += $v[$i] * $Q->at($i, $j) * $v[$j];
                }
            }

            return $sum;
        };

        $interval = function (float $lambda, Point $a, Point $b) : Point {
            $res = new Point();

            $res->x = $a->x + $lambda * ($b->x - $a->x);
            $res->y = $a->y + $lambda * ($b->y - $a->y);

            return $res;
        };

        $dorth_infty = function (Point $p0, Point $p2) use ($sign) : Point {
            $r = new Point();

            $r->y = $sign($p2->x - $p0->x);
            $r->x = -$sign($p2->y - $p0->y);

            return $r;
        };

        $ddenom = function (Point $p0, Point $p2) use ($dorth_infty) : float {
            $r = $dorth_infty($p0, $p2);

            return $r->y * ($p2->x - $p0->x) - $r->x * ($p2->y - $p0->y);
        };

        $dpara = function (Point $p0, Point $p1, Point $p2) : float {
            $x1 = $p1->x - $p0->x;
            $y1 = $p1->y - $p0->y;
            $x2 = $p2->x - $p0->x;
            $y2 = $p2->y - $p0->y;

            return $x1 * $y2 - $x2 * $y1;
        };

        $cprod = function (Point $p0, Point $p1, Point $p2, Point $p3) : float {
            $x1 = $p1->x - $p0->x;
            $y1 = $p1->y - $p0->y;
            $x2 = $p3->x - $p2->x;
            $y2 = $p3->y - $p2->y;

            return $x1 * $y2 - $x2 * $y1;
        };

        $iprod = function (Point $p0, Point $p1, Point $p2) : float {
            $x1 = $p1->x - $p0->x;
            $y1 = $p1->y - $p0->y;
            $x2 = $p2->x - $p0->x;
            $y2 = $p2->y - $p0->y;

            return $x1 * $x2 + $y1 * $y2;
        };

        $iprod1 = function (Point $p0, Point $p1, Point $p2, Point $p3) : float {
            $x1 = $p1->x - $p0->x;
            $y1 = $p1->y - $p0->y;
            $x2 = $p3->x - $p2->x;
            $y2 = $p3->y - $p2->y;

            return $x1 * $x2 + $y1 * $y2;
        };

        $ddist = function (Point $p, Point $q) : float {
            return sqrt(
                ($p->x - $q->x) *
                ($p->x - $q->x) +
                ($p->y - $q->y) *
                ($p->y - $q->y)
            );
        };

        $bezier = function (
            float $t,
            Point $p0,
            Point $p1,
            Point $p2,
            Point $p3
        ) : Point {
            $s = 1 - $t;
            $res = new Point();

            $res->x =
                $s *
                $s *
                $s *
                $p0->x +
                3 *
                ($s * $s * $t) *
                $p1->x +
                3 *
                ($t * $t * $s) *
                $p2->x +
                $t *
                $t *
                $t *
                $p3->x;
            $res->y =
                $s *
                $s *
                $s *
                $p0->y +
                3 *
                ($s * $s * $t) *
                $p1->y +
                3 *
                ($t * $t * $s) *
                $p2->y +
                $t *
                $t *
                $t *
                $p3->y;

            return $res;
        };

        $tangent = function (
            Point $p0,
            Point $p1,
            Point $p2,
            Point $p3,
            Point $q0,
            Point $q1
        ) use ($cprod) : float {
            $A = $cprod($p0, $p1, $q0, $q1);
            $B = $cprod($p1, $p2, $q0, $q1);
            $C = $cprod($p2, $p3, $q0, $q1);
            $a = $A - 2 * $B + $C;
            $b = -2 * $A + 2 * $B;
            $c = $A;

            $d = $b * $b - 4 * $a * $c;

            if (0 === $a || $d < 0) {
                return -1.0;
            }

            $s = sqrt($d);

            if (0 === $a) {
                return -1.0;
            }
            $r1 = (-$b + $s) / (2 * $a);
            $r2 = (-$b - $s) / (2 * $a);

            if ($r1 >= 0 && $r1 <= 1) {
                return $r1;
            } elseif ($r2 >= 0 && $r2 <= 1) {
                return $r2;
            }

            return -1.0;
        };

        $calcSums = function (Path &$path) {
            $path->x0 = $path->pt[0]->x;
            $path->y0 = $path->pt[0]->y;

            $path->sums = [];
            $s = &$path->sums;
            $s[] = new Sum(0, 0, 0, 0, 0);
            for ($i = 0; $i < $path->len; ++$i) {
                $x = $path->pt[$i]->x - $path->x0;
                $y = $path->pt[$i]->y - $path->y0;
                $s[] = new Sum(
                    $s[$i]->x + $x,
                    $s[$i]->y + $y,
                    $s[$i]->xy + $x * $y,
                    $s[$i]->x2 + $x * $x,
                    $s[$i]->y2 + $y * $y
                );
            }
        };

        $calcLon = function (Path &$path) use ($mod, $xprod, $sign, $cyclic) {
            $n = $path->len;
            $pt = &$path->pt;
            $pivk = array_fill(0, $n, null);
            $nc = array_fill(0, $n, null);
            $ct = array_fill(0, 4, null);
            /**
            * @var int[] $lon
            */
            $lon = array_fill(0, $n, 0);
            $path->lon = $lon;

            $constraint = [new Point(), new Point()];
            $cur = new Point();
            $off = new Point();
            $dk = new Point();

            $k = 0;
            for ($i = $n - 1; $i >= 0; --$i) {
                if ($pt[$i]->x !== $pt[$k]->x && $pt[$i]->y !== $pt[$k]->y) {
                    $k = $i + 1;
                }
                $nc[$i] = $k;
            }

            for ($i = $n - 1; $i >= 0; --$i) {
                $ct[0] = $ct[1] = $ct[2] = $ct[3] = 0;
                $dir =
                    (
                        3 +
                        3 *
                        ($pt[$mod($i + 1, $n)]->x - $pt[$i]->x) +
                        ($pt[$mod($i + 1, $n)]->y - $pt[$i]->y)
                    ) / 2;
                ++$ct[$dir];

                $constraint[0]->x = 0;
                $constraint[0]->y = 0;
                $constraint[1]->x = 0;
                $constraint[1]->y = 0;

                $k = $nc[$i];
                $k1 = $i;
                $foundk = 0;
                while (1) {
                    $dir =
                        (
                            3 +
                            3 *
                            $sign($pt[$k]->x - $pt[$k1]->x) +
                            $sign($pt[$k]->y - $pt[$k1]->y)
                        ) / 2;
                    ++$ct[$dir];

                    if ($ct[0] && $ct[1] && $ct[2] && $ct[3]) {
                        $pivk[$i] = $k1;
                        $foundk = 1;
                        break;
                    }

                    $cur->x = $pt[$k]->x - $pt[$i]->x;
                    $cur->y = $pt[$k]->y - $pt[$i]->y;

                    if (
                        $xprod($constraint[0], $cur) < 0 ||
                        $xprod($constraint[1], $cur) > 0
                    ) {
                        break;
                    }

                    if (!(abs($cur->x) <= 1 && abs($cur->y) <= 1)) {
                        $off->x =
                            $cur->x +
                            (
                                ($cur->y >= 0 && ($cur->y > 0 || $cur->x < 0))
                                    ? 1
                                    : -1
                            );
                        $off->y =
                            $cur->y +
                            (
                                ($cur->x <= 0 && ($cur->x < 0 || $cur->y < 0))
                                    ? 1
                                    : -1
                            );
                        if ($xprod($constraint[0], $off) >= 0) {
                            $constraint[0]->x = $off->x;
                            $constraint[0]->y = $off->y;
                        }
                        $off->x =
                            $cur->x +
                            (
                                ($cur->y <= 0 && ($cur->y < 0 || $cur->x < 0))
                                    ? 1
                                    : -1
                            );
                        $off->y =
                            $cur->y +
                            (
                                ($cur->x >= 0 && ($cur->x > 0 || $cur->y < 0))
                                    ? 1
                                    : -1
                            );
                        if ($xprod($constraint[1], $off) <= 0) {
                            $constraint[1]->x = $off->x;
                            $constraint[1]->y = $off->y;
                        }
                    }
                    $k1 = $k;
                    $k = $nc[$k1];
                    if ( ! $cyclic($k, $i, $k1)) {
                        break;
                    }
                }
                if (0 === $foundk) {
                    $dk->x = $sign($pt[$k]->x - $pt[$k1]->x);
                    $dk->y = $sign($pt[$k]->y - $pt[$k1]->y);
                    $cur->x = $pt[$k1]->x - $pt[$i]->x;
                    $cur->y = $pt[$k1]->y - $pt[$i]->y;

                    $a = $xprod($constraint[0], $cur);
                    $b = $xprod($constraint[0], $dk);
                    $c = $xprod($constraint[1], $cur);
                    $d = $xprod($constraint[1], $dk);

                    $j = 10000000;
                    if ($b < 0) {
                        $j = floor($a / -$b);
                    }
                    if ($d > 0) {
                        $j = min($j, floor(-$c / $d));
                    }
                    $pivk[$i] = $mod($k1 + $j, $n);
                }
            }

            $j = $pivk[$n - 1];
            $path->lon[$n - 1] = (int) $j;
            for ($i = $n - 2; $i >= 0; --$i) {
                if ($cyclic($i + 1, $pivk[$i], $j)) {
                    $j = $pivk[$i];
                }
                $path->lon[$i] = (int) $j;
            }

            for (
                $i = $n - 1;
                $cyclic($mod($i + 1, $n), $j, $path->lon[$i]);
                --$i
            ) {
                $path->lon[$i] = (int) $j;
            }
        };

        $bestPolygon = function (Path &$path) use ($mod) {
            $penalty3 = function (Path $path, int $i, int $j) : float {
                $n = $path->len;
                $pt = $path->pt;
                $sums = $path->sums;
                $r = 0;
                if ($j >= $n) {
                    $j -= $n;
                    $r = 1;
                }

                if (0 === $r) {
                    $x = $sums[$j + 1]->x - $sums[$i]->x;
                    $y = $sums[$j + 1]->y - $sums[$i]->y;
                    $x2 = $sums[$j + 1]->x2 - $sums[$i]->x2;
                    $xy = $sums[$j + 1]->xy - $sums[$i]->xy;
                    $y2 = $sums[$j + 1]->y2 - $sums[$i]->y2;
                    $k = $j + 1 - $i;
                } else {
                    $x = $sums[$j + 1]->x - $sums[$i]->x + $sums[$n]->x;
                    $y = $sums[$j + 1]->y - $sums[$i]->y + $sums[$n]->y;
                    $x2 = $sums[$j + 1]->x2 - $sums[$i]->x2 + $sums[$n]->x2;
                    $xy = $sums[$j + 1]->xy - $sums[$i]->xy + $sums[$n]->xy;
                    $y2 = $sums[$j + 1]->y2 - $sums[$i]->y2 + $sums[$n]->y2;
                    $k = $j + 1 - $i + $n;
                }

                $px = ($pt[$i]->x + $pt[$j]->x) / 2.0 - $pt[0]->x;
                $py = ($pt[$i]->y + $pt[$j]->y) / 2.0 - $pt[0]->y;
                $ey = ($pt[$j]->x - $pt[$i]->x);
                $ex = -($pt[$j]->y - $pt[$i]->y);

                $a = (($x2 - 2 * $x * $px) / $k + $px * $px);
                $b = (($xy - $x * $py - $y * $px) / $k + $px * $py);
                $c = (($y2 - 2 * $y * $py) / $k + $py * $py);

                $s = $ex * $ex * $a + 2 * $ex * $ey * $b + $ey * $ey * $c;

                return sqrt($s);
            };

            $n = $path->len;
            $pen = array_fill(0, $n + 1, null);
            $prev = array_fill(0, $n + 1, null);
            $clip0 = array_fill(0, $n, null);
            $clip1 = array_fill(0, $n + 1, null);
            $seg0 = array_fill(0, $n + 1, null);
            $seg1 = array_fill(0, $n + 1, null);

            for ($i = 0; $i < $n; ++$i) {
                $c = $mod($path->lon[$mod($i - 1, $n)] - 1, $n);
                if ($c === $i) {
                    $c = $mod($i + 1, $n);
                }
                if ($c < $i) {
                    $clip0[$i] = $n;
                } else {
                    $clip0[$i] = $c;
                }
            }

            $j = 1;
            for ($i = 0; $i < $n; ++$i) {
                while ($j <= $clip0[$i]) {
                    $clip1[$j] = $i;
                    ++$j;
                }
            }

            $i = 0;
            for ($j = 0; $i < $n; ++$j) {
                $seg0[$j] = $i;
                $i = $clip0[$i];
            }
            $seg0[$j] = $n;
            $m = $j;

            $i = $n;
            for ($j = $m; $j > 0; --$j) {
                $seg1[$j] = $i;
                $i = $clip1[$i];
            }
            $seg1[0] = 0;

            $pen[0] = 0;
            for ($j = 1; $j <= $m; ++$j) {
                for ($i = $seg1[$j]; $i <= $seg0[$j]; ++$i) {
                    $best = -1;
                    for ($k = $seg0[$j - 1]; $k >= $clip1[$i]; --$k) {
                        $thispen = $penalty3($path, $k, $i) + $pen[$k];
                        if ($best < 0 || $thispen < $best) {
                            $prev[$i] = $k;
                            $best = $thispen;
                        }
                    }
                    $pen[$i] = $best;
                }
            }
            $path->m = $m;
            /**
            * @var int[] $po
            */
            $po = array_fill(0, $m, 0);
            $path->po = $po;

            for ($i = $n, $j = $m - 1; $i > 0; --$j) {
                $i = (int) $prev[$i];
                $path->po[$j] = $i;
            }
        };

        $adjustVertices = function (Path &$path) use ($mod, $quadform) {
            $pointslope = function (
                Path $path,
                float $i,
                float $j,
                Point &$ctr,
                Point &$dir
            ) {
                $n = $path->len;
                $sums = $path->sums;
                $r = 0;

                while ($j >= $n) {
                    $j -= $n;
                    $r += 1;
                }
                while ($i >= $n) {
                    $i -= $n;
                    $r -= 1;
                }
                while ($j < 0) {
                    $j += $n;
                    $r -= 1;
                }
                while ($i < 0) {
                    $i += $n;
                    $r += 1;
                }

                $x = $sums[$j + 1]->x - $sums[$i]->x + $r * $sums[$n]->x;
                $y = $sums[$j + 1]->y - $sums[$i]->y + $r * $sums[$n]->y;
                $x2 = $sums[$j + 1]->x2 - $sums[$i]->x2 + $r * $sums[$n]->x2;
                $xy = $sums[$j + 1]->xy - $sums[$i]->xy + $r * $sums[$n]->xy;
                $y2 = $sums[$j + 1]->y2 - $sums[$i]->y2 + $r * $sums[$n]->y2;
                $k = $j + 1 - $i + $r * $n;

                $ctr->x = $x / $k;
                $ctr->y = $y / $k;

                $a = ($x2 - $x * $x / $k) / $k;
                $b = ($xy - $x * $y / $k) / $k;
                $c = ($y2 - $y * $y / $k) / $k;

                $lambda2 =
                    ($a + $c + sqrt(($a - $c) * ($a - $c) + 4 * $b * $b)) / 2;

                $a -= $lambda2;
                $c -= $lambda2;

                if (abs($a) >= abs($c)) {
                    $l = sqrt($a * $a + $b * $b);
                    if (0 !== $l) {
                        $dir->x = -$b / $l;
                        $dir->y = $a / $l;
                    }
                } else {
                    $l = sqrt($c * $c + $b * $b);
                    if (0 !== $l) {
                        $dir->x = -$c / $l;
                        $dir->y = $b / $l;
                    }
                }
                if (0 === $l) {
                    $dir->x = $dir->y = 0;
                }
            };

            $m = $path->m;
            $po = $path->po;
            $n = $path->len;
            $pt = $path->pt;
            $x0 = $path->x0;
            $y0 = $path->y0;
            $ctr = array_fill(0, $m, null);
            $dir = array_fill(0, $m, null);
            $q = array_fill(0, $m, null);
            $v = array_fill(0, 3, null);
            $s = new Point();

            $path->curve = new Curve($m);

            for ($i = 0; $i < $m; ++$i) {
                $j = $po[$mod($i + 1, $m)];
                $j = $mod($j - $po[$i], $n) + $po[$i];
                $ctr[$i] = new Point();
                $dir[$i] = new Point();
                $pointslope($path, $po[$i], $j, $ctr[$i], $dir[$i]);
            }

            for ($i = 0; $i < $m; ++$i) {
                $q[$i] = new Quad();
                $d = $dir[$i]->x * $dir[$i]->x + $dir[$i]->y * $dir[$i]->y;
                if (0.0 === $d) {
                    for ($j = 0; $j < 3; ++$j) {
                        for ($k = 0; $k < 3; ++$k) {
                            $q[$i]->data[$j * 3 + $k] = 0;
                        }
                    }
                } else {
                    $v[0] = $dir[$i]->y;
                    $v[1] = -$dir[$i]->x;
                    $v[2] = -$v[1] * $ctr[$i]->y - $v[0] * $ctr[$i]->x;
                    for ($l = 0; $l < 3; ++$l) {
                        for ($k = 0; $k < 3; ++$k) {
                            if (0 !== $d) {
                                $q[$i]->data[$l * 3 + $k] =
                                    $v[$l] * $v[$k] / $d;
                            } else {
                                /**
                                * @todo Hack para evitar división por 0
                                */
                                $q[$i]->data[$l * 3 + $k] = null;
                            }
                        }
                    }
                }
            }

            for ($i = 0; $i < $m; ++$i) {
                $Q = new Quad();
                $w = new Point();

                $s->x = $pt[$po[$i]]->x - $x0;
                $s->y = $pt[$po[$i]]->y - $y0;

                $j = $mod($i - 1, $m);

                for ($l = 0; $l < 3; ++$l) {
                    for ($k = 0; $k < 3; ++$k) {
                        $Q->data[$l * 3 + $k] =
                            (int) ($q[$j]->at($l, $k) + $q[$i]->at($l, $k));
                    }
                }

                while (1) {
                    $det =
                        $Q->at(0, 0) *
                        $Q->at(1, 1) -
                        $Q->at(0, 1) *
                        $Q->at(1, 0);
                    if (0.0 !== $det) {
                        $w->x =
                            (
                                -$Q->at(0, 2) *
                                $Q->at(1, 1) +
                                $Q->at(1, 2) *
                                $Q->at(0, 1)
                            ) / $det;
                        $w->y =
                            (
                                $Q->at(0, 2) *
                                $Q->at(1, 0) -
                                $Q->at(1, 2) *
                                $Q->at(0, 0)
                            ) / $det;
                        break;
                    }

                    if ($Q->at(0, 0) > $Q->at(1, 1)) {
                        $v[0] = -$Q->at(0, 1);
                        $v[1] = $Q->at(0, 0);
                    } elseif ($Q->at(1, 1)) {
                        $v[0] = -$Q->at(1, 1);
                        $v[1] = $Q->at(1, 0);
                    } else {
                        $v[0] = 1;
                        $v[1] = 0;
                    }
                    $d = $v[0] * $v[0] + $v[1] * $v[1];
                    $v[2] = -$v[1] * $s->y - $v[0] * $s->x;
                    for ($l = 0; $l < 3; ++$l) {
                        for ($k = 0; $k < 3; ++$k) {
                            $Q->data[$l * 3 + $k] += $v[$l] * $v[$k] / $d;
                        }
                    }
                }
                $dx = abs($w->x - $s->x);
                $dy = abs($w->y - $s->y);
                if ($dx <= 0.5 && $dy <= 0.5) {
                    $path->curve->vertex[$i] = new Point(
                        $w->x + $x0,
                        $w->y + $y0
                    );
                    continue;
                }

                $min = $quadform($Q, $s);
                $xmin = $s->x;
                $ymin = $s->y;

                if (0.0 !== $Q->at(0, 0)) {
                    for ($z = 0; $z < 2; ++$z) {
                        $w->y = $s->y - 0.5 + $z;
                        $w->x =
                            -(
                                $Q->at(0, 1) *
                                $w->y +
                                $Q->at(0, 2)
                            ) / $Q->at(0, 0);
                        $dx = abs($w->x - $s->x);
                        $cand = $quadform($Q, $w);
                        if ($dx <= 0.5 && $cand < $min) {
                            $min = $cand;
                            $xmin = $w->x;
                            $ymin = $w->y;
                        }
                    }
                }

                if (0.0 !== $Q->at(1, 1)) {
                    for ($z = 0; $z < 2; ++$z) {
                        $w->x = $s->x - 0.5 + $z;
                        $w->y =
                            -(
                                $Q->at(1, 0) *
                                $w->x +
                                $Q->at(1, 2)
                            ) / $Q->at(1, 1);
                        $dy = abs($w->y - $s->y);
                        $cand = $quadform($Q, $w);
                        if ($dy <= 0.5 && $cand < $min) {
                            $min = $cand;
                            $xmin = $w->x;
                            $ymin = $w->y;
                        }
                    }
                }

                for ($l = 0; $l < 2; ++$l) {
                    for ($k = 0; $k < 2; ++$k) {
                        $w->x = $s->x - 0.5 + $l;
                        $w->y = $s->y - 0.5 + $k;
                        $cand = $quadform($Q, $w);
                        if ($cand < $min) {
                            $min = $cand;
                            $xmin = $w->x;
                            $ymin = $w->y;
                        }
                    }
                }

                $path->curve->vertex[$i] = new Point($xmin + $x0, $ymin + $y0);
            }
        };

        $reverse = function (Path &$path) {
            /**
            * @var Curve $curve
            */
            $curve = &$path->curve;

            /**
            * @var int $m
            */
            $m = $curve->n;

            /**
            * @var Point[]
            */
            $v = &$curve->vertex;

            for ($i = 0, $j = $m - 1; $i < $j; $i++, $j--) {
                $tmp = $v[$i];
                $v[$i] = $v[$j];
                $v[$j] = $tmp;
            }
        };

        $smooth = function (
            Path &$path
        ) use (
            $mod,
            $interval,
            $ddenom,
            $dpara,
            $info
        ) {
            /**
            * @var Curve $curve
            */
            $curve = &$path->curve;
            $m = $curve->n;

            for ($i = 0; $i < $m; ++$i) {
                $j = $mod($i + 1, $m);
                $k = $mod($i + 2, $m);
                $p4 = $interval(
                    1 / 2.0,
                    $curve->vertex[$k],
                    $curve->vertex[$j]
                );

                $denom = $ddenom($curve->vertex[$i], $curve->vertex[$k]);
                if (0.0 !== $denom) {
                    $dd =
                        $dpara(
                            $curve->vertex[$i],
                            $curve->vertex[$j],
                            $curve->vertex[$k]
                        ) / $denom;
                    $dd = abs($dd);
                    $alpha = $dd > 1 ? (1 - 1.0 / $dd) : 0;
                    $alpha = $alpha / 0.75;
                } else {
                    $alpha = 4 / 3.0;
                }
                $curve->alpha0[$j] = $alpha;

                if ($alpha >= $info['alphamax']) {
                    $curve->tag[$j] = 'CORNER';
                    $curve->c[3 * $j + 1] = $curve->vertex[$j];
                    $curve->c[3 * $j + 2] = $p4;
                } else {
                    if ($alpha < 0.55) {
                        $alpha = 0.55;
                    } elseif ($alpha > 1) {
                        $alpha = 1;
                    }
                    $p2 = $interval(
                        0.5 + 0.5 * $alpha,
                        $curve->vertex[$i],
                        $curve->vertex[$j]
                    );
                    $p3 = $interval(
                        0.5 + 0.5 * $alpha,
                        $curve->vertex[$k],
                        $curve->vertex[$j]
                    );
                    $curve->tag[$j] = 'CURVE';
                    $curve->c[3 * $j + 0] = $p2;
                    $curve->c[3 * $j + 1] = $p3;
                    $curve->c[3 * $j + 2] = $p4;
                }
                $curve->alpha[$j] = $alpha;
                $curve->beta[$j] = 0.5;
            }
            $curve->alphaCurve = 1;
        };

        $optiCurve = function (Path &$path) use (
            $mod,
            $ddist,
            $sign,
            $cprod,
            $dpara,
            $interval,
            $tangent,
            $bezier,
            $iprod,
            $iprod1,
            $info
        ) {
            /**
            * @param int[] $convc
            * @param float[] $areac
            */
            $opti_penalty = function (
                Path $path,
                float $i,
                float $j,
                Opti $res,
                float $opttolerance,
                array $convc,
                array $areac
            ) use (
                $mod,
                $ddist,
                $sign,
                $cprod,
                $dpara,
                $interval,
                $tangent,
                $bezier,
                $iprod,
                $iprod1
            ) : int {
                /**
                * @var Curve $curve
                */
                $curve = $path->curve;

                /**
                * @var int $m
                */
                $m = $curve->n;

                $vertex = $curve->vertex;
                if ($i === $j) {
                    return 1;
                }

                $k = $i;
                $i1 = $mod($i + 1, $m);
                $k1 = $mod($k + 1, $m);
                $conv = $convc[$k1];
                if (0 === $conv) {
                    return 1;
                }
                $d = $ddist($vertex[$i], $vertex[$i1]);
                for ($k = $k1; $k !== $j; $k = $k1) {
                    $k1 = $mod($k + 1, $m);
                    $k2 = $mod($k + 2, $m);
                    if ($convc[$k1] !== $conv) {
                        return 1;
                    }
                    if (
                        $sign(
                            $cprod(
                                $vertex[$i],
                                $vertex[$i1],
                                $vertex[$k1],
                                $vertex[$k2]
                            )
                        ) !== $conv
                    ) {
                        return 1;
                    }
                    if (
                        $iprod1(
                            $vertex[$i],
                            $vertex[$i1],
                            $vertex[$k1],
                            $vertex[$k2]
                        ) < (
                            $d *
                            $ddist($vertex[$k1], $vertex[$k2]) *
                            -0.999847695156
                        )
                    ) {
                        return 1;
                    }
                }

                $p0 = clone $curve->c[$mod($i, $m) * 3 + 2];
                $p1 = clone $vertex[$mod($i + 1, $m)];
                $p2 = clone $vertex[$mod($j, $m)];
                $p3 = clone $curve->c[$mod($j, $m) * 3 + 2];

                $area = $areac[$j] - $areac[$i];
                $area -=
                    $dpara(
                        $vertex[0],
                        $curve->c[$i * 3 + 2],
                        $curve->c[$j * 3 + 2]
                    ) / 2;

                if ($i >= $j) {
                    $area += $areac[$m];
                }

                $A1 = $dpara($p0, $p1, $p2);
                $A2 = $dpara($p0, $p1, $p3);
                $A3 = $dpara($p0, $p2, $p3);

                $A4 = $A1 + $A3 - $A2;

                if ($A2 === $A1) {
                    return 1;
                }

                $t = $A3 / ($A3 - $A4);
                $s = $A2 / ($A2 - $A1);
                $A = $A2 * $t / 2.0;

                if (0.0 === $A) {
                    return 1;
                }

                $R = $area / $A;
                $alpha = 2 - sqrt(4 - $R / 0.3);

                $res->c[0] = $interval($t * $alpha, $p0, $p1);
                $res->c[1] = $interval($s * $alpha, $p3, $p2);
                $res->alpha = $alpha;
                $res->t = $t;
                $res->s = $s;

                $p1 = clone $res->c[0];
                $p2 = clone $res->c[1];

                $res->pen = 0;

                for ($k = $mod($i + 1, $m); $k !== $j; $k = $k1) {
                    $k1 = $mod($k + 1, $m);
                    $t = $tangent(
                        $p0,
                        $p1,
                        $p2,
                        $p3,
                        $vertex[$k],
                        $vertex[$k1]
                    );
                    if ($t < -0.5) {
                        return 1;
                    }
                    $pt = $bezier($t, $p0, $p1, $p2, $p3);
                    $d = $ddist($vertex[$k], $vertex[$k1]);
                    if (0.0 === $d) {
                        return 1;
                    }
                    $d1 = $dpara($vertex[$k], $vertex[$k1], $pt) / $d;
                    if (abs($d1) > $opttolerance) {
                        return 1;
                    }
                    if ($iprod($vertex[$k], $vertex[$k1], $pt) < 0 ||
                            $iprod($vertex[$k1], $vertex[$k], $pt) < 0) {
                        return 1;
                    }
                    $res->pen += $d1 * $d1;
                }

                for ($k = $i; $k !== $j; $k = $k1) {
                    $k1 = $mod($k + 1, $m);
                    $t = $tangent(
                        $p0,
                        $p1,
                        $p2,
                        $p3,
                        $curve->c[$k * 3 + 2],
                        $curve->c[$k1 * 3 + 2]
                    );
                    if ($t < -0.5) {
                        return 1;
                    }
                    $pt = $bezier($t, $p0, $p1, $p2, $p3);
                    $d = $ddist($curve->c[$k * 3 + 2], $curve->c[$k1 * 3 + 2]);
                    if (0.0 === $d) {
                        return 1;
                    }
                    $d1 =
                        $dpara(
                            $curve->c[$k * 3 + 2],
                            $curve->c[$k1 * 3 + 2],
                            $pt
                        ) / $d;
                    $d2 =
                        $dpara(
                            $curve->c[$k * 3 + 2],
                            $curve->c[$k1 * 3 + 2],
                            $vertex[$k1]
                        ) / $d;
                    $d2 *= 0.75 * $curve->alpha[$k1];
                    if ($d2 < 0) {
                        $d1 = -$d1;
                        $d2 = -$d2;
                    }
                    if ($d1 < $d2 - $opttolerance) {
                        return 1;
                    }
                    if ($d1 < $d2) {
                        $res->pen += ($d1 - $d2) * ($d1 - $d2);
                    }
                }

                return 0;
            };

            /**
            * @var Curve $curve
            */
            $curve = $path->curve;
            $m = $curve->n;
            $vert = $curve->vertex;
            $pt = array_fill(0, $m + 1, null);
            $pen = array_fill(0, $m + 1, null);
            $len = array_fill(0, $m + 1, null);

            /**
            * @var (Opti|null)[]
            */
            $opt = array_fill(0, $m + 1, null);
            $o = new Opti();

            $convc = array_fill(0, $m, null);
            $areac = array_fill(0, $m + 1, null);

            for ($i = 0; $i < $m; ++$i) {
                if ('CURVE' === $curve->tag[$i]) {
                    $convc[$i] = $sign(
                        $dpara(
                            $vert[$mod($i - 1, $m)],
                            $vert[$i],
                            $vert[$mod($i + 1, $m)]
                        )
                    );
                } else {
                    $convc[$i] = 0;
                }
            }

            $area = 0.0;
            $areac[0] = 0.0;
            $p0 = $curve->vertex[0];
            for ($i = 0; $i < $m; ++$i) {
                $i1 = $mod($i + 1, $m);
                if ('CURVE' === $curve->tag[$i1]) {
                    $alpha = $curve->alpha[$i1];
                    $area +=
                        0.3 *
                        $alpha *
                        (4 - $alpha) *
                        $dpara(
                            $curve->c[$i * 3 + 2],
                            $vert[$i1],
                            $curve->c[$i1 * 3 + 2]
                        ) /
                        2;
                    $area +=
                        $dpara(
                            $p0,
                            $curve->c[$i * 3 + 2],
                            $curve->c[$i1 * 3 + 2]
                        ) /
                        2;
                }
                $areac[$i + 1] = $area;
            }

            $pt[0] = -1;
            $pen[0] = 0;
            $len[0] = 0;

            for ($j = 1; $j <= $m; ++$j) {
                $pt[$j] = $j - 1;
                $pen[$j] = $pen[$j - 1];
                $len[$j] = $len[$j - 1] + 1;

                for ($i = $j - 2; $i >= 0; --$i) {
                    $r = $opti_penalty(
                        $path,
                        $i,
                        $mod($j, $m),
                        $o,
                        $info['opttolerance'],
                        $convc,
                        $areac
                    );
                    if ($r) {
                        break;
                    }
                    if (
                        $len[$j] > $len[$i] + 1 ||
                        (
                            $len[$i] + 1 === $len[$j] &&
                            $pen[$j] > $pen[$i] + $o->pen
                        )
                    ) {
                        $pt[$j] = $i;
                        $pen[$j] = $pen[$i] + $o->pen;
                        $len[$j] = $len[$i] + 1;
                        $opt[$j] = $o;
                        $o = new Opti();
                    }
                }
            }
            $om = $len[$m];
            $ocurve = new Curve($om);
            $s = array_fill(0, $om, null);
            $t = array_fill(0, $om, null);

            $j = $m;
            for ($i = $om - 1; $i >= 0; --$i) {
                if ($pt[$j] === $j - 1) {
                    $ocurve->tag[$i] = $curve->tag[$mod($j, $m)];
                    $ocurve->c[$i * 3 + 0] = $curve->c[$mod($j, $m) * 3 + 0];
                    $ocurve->c[$i * 3 + 1] = $curve->c[$mod($j, $m) * 3 + 1];
                    $ocurve->c[$i * 3 + 2] = $curve->c[$mod($j, $m) * 3 + 2];
                    $ocurve->vertex[$i] = $curve->vertex[$mod($j, $m)];
                    $ocurve->alpha[$i] = $curve->alpha[$mod($j, $m)];
                    $ocurve->alpha0[$i] = $curve->alpha0[$mod($j, $m)];
                    $ocurve->beta[$i] = $curve->beta[$mod($j, $m)];
                    $s[$i] = $t[$i] = 1.0;
                } else {
                    $ocurve->tag[$i] = 'CURVE';
                    /**
                    * @var Opti $opti
                    */
                    $opti = $opt[$j];
                    $ocurve->c[$i * 3 + 0] = $opti->c[0];
                    $ocurve->c[$i * 3 + 1] = $opti->c[1];
                    $ocurve->c[$i * 3 + 2] = $curve->c[$mod($j, $m) * 3 + 2];
                    $ocurve->vertex[$i] = $interval(
                        $opti->s,
                        $curve->c[$mod($j, $m) * 3 + 2],
                        $vert[$mod($j, $m)]
                    );
                    $ocurve->alpha[$i] = $opti->alpha;
                    $ocurve->alpha0[$i] = $opti->alpha;
                    $s[$i] = $opti->s;
                    $t[$i] = $opti->t;
                }
                $j = $pt[$j];
            }

            for ($i = 0; $i < $om; ++$i) {
                $i1 = $mod($i + 1, $om);
                if (($s[$i] + $t[$i1]) !== 0) {
                    $ocurve->beta[$i] = $s[$i] / ($s[$i] + $t[$i1]);
                } else {
                    /**
                    * @todo Hack para evitar división por 0
                    */
                    $ocurve->beta[$i] = null;
                }
            }
            $ocurve->alphaCurve = 1;
            $path->curve = $ocurve;
        };

        $len = count($this->pathlist);
        for ($i = 0; $i < $len; ++$i) {
            $path = &$this->pathlist[$i];
            $calcSums($path);
            $calcLon($path);
            $bestPolygon($path);
            $adjustVertices($path);

            if ('-' === $path->sign) {
                $reverse($path);
            }

            $smooth($path);

            if ($info['optcurve']) {
                $optiCurve($path);
            }
        }
    }
}
