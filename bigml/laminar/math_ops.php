<?php

# Copyright 2017 BigML
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.

//Activation functions and helpers

namespace BigML;

include_once "constants.php";

function broadcast($fn) {
        return function($xs) use ($fn){
            if (count($xs) == 0) {
                return [];
            } elseif (is_array($xs[0])) {
                $output = [];
                foreach ($xs as $xvec) {
                    $output[] = $fn($xvec);
                }
                return $output;
            } else {
                return $fn($xs);
            }
        };
}

function array_zip($a1, $a2) {
    $out = [];
    if (count($a1) < count($a2)) {
        $a = $a1;
    } else {
        $a = $a2;
    }

    foreach ($a as $index => $value) {
        $out[] = [$a1[$index], $a2[$index]];
    }
    return $out;
}

function plus($mat, $vec) {
    $output = [];
    foreach ($mat as $row) {
        $new_row = [];
        foreach (array_zip($row, $vec) as $pair) {
            $new_row[] = $pair[0] + $pair[1];
        }
        $output[] = $new_row;
    }
    return $output;
}        

function minus($mat, $vec) {
    $output = [];
    foreach ($mat as $row) {
        $new_row = [];
        foreach (array_zip($row, $vec) as $pair) {
            $new_row[] = $pair[0] - $pair[1];
        }
        $output[] = $new_row;
    }
    return $output;
}        

function times($mat, $vec) {
    $output = [];
    foreach ($mat as $row) {
        $new_row = [];
        foreach (array_zip($row, $vec) as $pair) {
            $new_row[] = $pair[0]*$pair[1];
        }
        $output[] = $new_row;
    }
    return $output;
}        

function divide($mat, $vec) {
    $output = [];
    foreach ($mat as $row) {
        $new_row = [];
        foreach (array_zip($row, $vec) as $pair) {
            $new_row[] = $pair[0]/$pair[1];
        }
        $output[] = $new_row;
    }
    return $output;
}        


function dot($mat1, $mat2) {
    $out_mat = [];

    foreach ($mat1 as $row1) {
        $new_row = [];
        foreach ($mat2 as $row2) {
            $sum = 0;
            foreach (array_zip($row1, $row2) as $pair) {
                $sum += $pair[0]*$pair[1];
            }
            $new_row[] = $sum;
        }
        $out_mat[] = $new_row;
    }
    return $out_mat;
}

function batch_norm($X, $mean, $stdev, $shift, $scale) {
    $norm_vals = divide(minus($X, $mean), $stdev);
    return plus(times($norm_vals, $scale), $shift);
}

function sigmoid($xs) {
    $out_vec = [];

    foreach ($xs as $x) {
        if ($x > 0) {
            if ($x < LARGE_EXP) {
                $ex_val = exp($x);
                $out_vec[] = $ex_val / ($ex_val + 1);
            } else {
                $out_vec[] = 1;
            }
        } else {
            if (-$x < LARGE_EXP) {
                $out_vec[] = 1 / (1 + exp(-$x));
            } else {
                $out_vec[] = 0;
            }
        }
    }

    return $out_vec;
}  

function softplus($xs) {
    $output= [];
    foreach ($xs as $x) {
        if ($x < LARGE_EXP) {
            $output[] = log(exp($x) + 1);
        } else {
            $output[] = $x;
        }
    }
    return $output;
}

function softmax($xs) {
    $xmax = max($xs);
    $exps = [];
    foreach ($xs as $x) {
        $exps[] = exp($x - $xmax);
    }
    $sumex = array_sum($exps);
    $output = [];
    foreach ($exps as $ex) {
        $output[] = $ex / $sumex;
    }
    return $output;
}

function relu($xs) {
    $output = [];
    foreach ($xs as $x) {
        if ($x > 0) {
            $output[] = $x;
        } else {
            $output[] = 0;
        }
    }
    return $output;
}

function activators($fn) {
    switch ($fn) {
        case "tanh":
            return broadcast( function ($xs) { return array_map('tanh', $xs); });
        case "sigmoid":
            return broadcast('BigML\sigmoid');
        case "softplus":
            return broadcast('BigML\softplus');
        case "relu":
            return broadcast('BigML\relu');
        case "softmax":
            return broadcast('BigML\softmax');
        case "identity":
            return broadcast( function($xs) { return array_map('floatval', $xs); });
    }
}

function init_layers($layers) {
    $output = [];
    foreach ($layers as $layer) {
        $output[] = (array) $layer;
    }
    return $output;
}

function destandardize($vec, $v_mean, $v_stdev) {
    $output = [];
    foreach ($vec as $v) {
        $output[] = array($v[0] * $v_stdev + $v_mean);
    }

    return $output;
}

function array_append($row, $n) {
    $array = [];
    for($i=0; $i<$n; $i++) {
        $array = array_merge($array, $row);
    }
    return $array;
}

function to_width($mat, $width) {
    if ($width > count($mat[0])) {
        $ntiles = floor( ceil($width / count($mat[0])));
    } else {
        $ntiles = 1;
    }

    $output = [];
    foreach ($mat as $row) {
        $output[] = array_slice( array_append($row, $ntiles), 0, $width);
    }
    return $output;
}

function add_residuals($residuals, $identities) {

    $to_add = to_width($identities, count($residuals[0]));

    $output = [];

    foreach (array_zip($residuals, $to_add) as $row_pair) {
        $new_row = [];
        foreach (array_zip($row_pair[0], $row_pair[1]) as $pair) {
            $new_row[] = $pair[0] + $pair[1];
        }
        $output[] = $new_row;
    }
    return $output;
}

function propagate($x_in, $layers) {

    $last_X = $x_in;
    $identities = $x_in;
    foreach ($layers as $layer) {
        $w = $layer['weights'];
        $m = $layer['mean'];
        $s = $layer['stdev'];
        $b = $layer['offset'];
        $g = $layer['scale'];

        $afn = $layer['activation_function'];
  
        $X_dot_w = dot($last_X, $w);
        
        if (!is_null($m) && !is_null($s)) {
            $next_in = batch_norm($X_dot_w, $m, $s, $b, $g);
        } else {
            $next_in = plus($X_dot_w, $b);
        }

        if ($layer['residuals']) {
            $next_in = add_residuals($next_in, $identities);
            $activator = activators($afn);
            $last_X = $activator($next_in);
            $identities = $last_X;
        } else {
            $activator = activators($afn);
            $last_X = $activator($next_in);
        }
    }

    return $last_X;
}

function sum_and_normalize($youts, $is_regression) {
    $ysums = [];
    foreach ($youts[0] as $i => $row) {
        $sum_row = [];
        foreach ($row as $j => $value) {
            $sum_array = [];
            foreach ($youts as $yout) {
                $sum_array[] = $yout[$i][$j];
            }
            $sum_row[] = array_sum($sum_array);
        }
        $ysums[] = $sum_row;
    }

    $out_dist = [];
    if ($is_regression) {
        foreach ($ysums as $ysum) {
            $out_dist = $ysum[0] / count($youts);
        }
    } else {
        foreach ($ysums as $ysum) {
            $rowsum = array_sum($ysum);
            $y_array = [];
            foreach ($ysum as $y) {
                $y_array[] = $y / $rowsum;
            }
            $out_dist[] = $y_array;
        }
    }

    return $out_dist;
}

?>
