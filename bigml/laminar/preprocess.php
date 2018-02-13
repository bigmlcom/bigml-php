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

//Auxiliary functions for preprocessing

namespace BigML;

include_once "constants.php";

define("MEAN", "mean");
define("STANDARD_DEVIATION", "stdev");

define("ZERO", "zero_value");
define("ONE", "one_value");

function dtype_ok($dtype_fn) {
    return (!is_null($dtype_fn) && ($dtype_fn == 'intval' OR 
                                    $dtype_fn == 'floatval'));
}

function np_zeros($x_dim, $y_dim, $dtype_fn = null) {
    $value = "0";
    try {
        if (dtype_ok($dtype_fn)) {
            $value = $dtype_fn($value);
        }
    } catch (Exception $e) {
    }
    $array = [];
    foreach (range(0, $x_dim - 1) as $i ) {
        $array[] = [];
        foreach (range(0, $y_dim - 1) as $j) {
            $array[$i][] = $value;
        }
    }
    return $array;
}

function np_asarray($array, $dtype_fn=null) {
    $new_array = [];
    try {
        foreach ($array as $item) {
            if (is_null($item)) {
                $new_array[] = "nan";
            } else {
                $new_array[] = $item;
            }
        }
    } catch (Exception $e) {
    }

    return $new_array;
}

function np_c_($array_a, $array_c) {
    if (is_null($array_a) OR $array_a == []) {
        return array($array_c);
    }
    $new_array = $array_a;
    $new_array[0] = array_merge($array_a[0], $array_c);

    return $new_array;
}

function v_index($alist, $value) {
    return array_search($value, $alist);
}

function one_hot($vector, $possible_values) {
    $idxs = [];
    foreach ($vector[0] as $v) {
        $idxs[] = v_index($possible_values, $v);
    }
    $valid_pairs = [];
    foreach ($idxs as $key => $value) {
        if (!is_bool($value)) {
            $valid_pairs[$key] = $value;
        }
    }
    $outvec = np_zeros( count($idxs), count($possible_values), 'floatval');
    foreach ($valid_pairs as $i => $j) {
        $outvec[$i][$j] = 1;
    }

    return $outvec;
}

function standardize($vector, $mean, $stdev) {
    $newvec = [];

    foreach ($vector as $component) {
        if (is_numeric($component)) {
            $newvec[] = $component - $mean;
        } else {
            $newvec[] = $component;
        }
    }

    if ($stdev > 0) {
        foreach ($newvec as $index => $component) {
            if (is_numeric($component)) {
                $newvec[$index] = $component / $stdev;
            } else {
                $newvec[$index] = $component;
            }
        }
    }

    foreach ($newvec as $index => $component) {
        if (is_numeric($component)) {
            $newvec[$index] = $component;
        } else {
            $newvec[$index] = 0.0;
        }
    }

    return $newvec;
}

function binarize($vector, $zero, $one) {
    foreach ($vector as $index => $value) {
        if ($one == 0.0) {
            if ($value == $one) {
                $vector[$index] = 1.0;
            }
            if ($value != $one && $value != 1.0) {
                $vector[$index] = 0.0;
            }
        } else {
            if ($value != $one) {
                $vector[$index] = 0.0;
            }
            if ($value == $one) {
                $vector[$index] = 1.0;
            }
        }
    }
    return $vector;
}

function moments($amap) {
    return array($amap->{MEAN}, $amap->{STANDARD_DEVIATION});
}

function bounds($amap) {
    return array($amap->{ZERO}, $amap->{ONE});
}

function transform($vector, $spec) {
    $vtype = $spec->type;

    if ($vtype == NUMERIC) {
        if (array_key_exists(STANDARD_DEVIATION, $spec)) {
            $mean = moments($spec)[0];
            $stdev = moments($spec)[1];
            $output = standardize($vector, $mean, $stdev);
        } elseif (array_key_exists(ZERO, $spec)) {
            $low = bounds($spec)[0];
            $high = bounds($spec)[1];
            $output = binarize($vector, $low, $high);
        } else {
            trigger_error(str($spec) . " is not a valid numeric spec!", E_USER_ERROR);
        }
    } elseif ($vtype == CATEGORICAL) {
        $output = one_hot($vector, $spec->values)[0];
    } else {
        trigger_error(str($vtype) . " is not a valid spec type!");
    }
    return $output;
}

function tree_predict($tree, $point) {
    $node = $tree;
    while (!is_null(end($node))) {
        if ($point[$node[0]] <= $node[1]) {
            $node = $node[2];
        } else {
            $node = $node[3];
        }
    }
    return $node[0];
}

function get_embedding($X, $model) {
    if (is_array($model)) {
        $preds = null;
        foreach ($model as $tree) {
            $tree_preds = [];
            foreach ($X as $row) {
                $tree_preds[] = tree_predict($tree, $row);
            }            
            if (is_null($preds)) {
                $preds = np_asarray($tree_preds)[0];
            } else {
                foreach ($preds as $index => $pred) {
                    $preds[$index] += np_asarray($tree_preds)[0][$index];
                }
            }
        }
                    
        if ($preds && count($preds) > 1) {
            $norm = array_sum($preds);
            foreach ($preds as $index => $pred) {
                $preds[$index] = $pred / $norm;
            }
        } else {
            foreach ($preds as $index => $pred) {
                $preds[$index] = $pred / count($model);
            }
        }
        return [$preds];
    } else {
        trigger_error("Model is unknown type!", E_USER_ERROR);
    }
}

function tree_transform($X, $trees) {
    $outdata = null;

    foreach ($trees as $component) {

        $feature_range = $component[0];
        $model = $component[1];

        $sidx = $feature_range[0];
        $eidx = $feature_range[1];
        $inputs = $X;
        foreach ($inputs as $index => $row) {

            $inputs[$index] = array_slice($row, $sidx, $eidx - $sidx);
        }
        $outarray = get_embedding($inputs, $model);

        if (!is_null($outdata)) {
            $outdata = np_c_($outdata, $outarray[0]);
        } else {
            $outdata = $outarray;
        }
    }
    return np_c_($outdata, $X[0]);
}

function preprocess($columns, $specs) {
    $outdata = null;

    foreach ($specs as $spec) {
        $column = array($columns[$spec->index]);

        if ($spec->type == NUMERIC) {
            $column = np_asarray($column, 'floatval');
        }
        $outarray = transform($column, $spec);

        if (!is_null($outdata)) {
            $outdata = np_c_($outdata, $outarray);
        } else {
            $outdata = [$outarray];
        }
    }
    return $outdata;
}
