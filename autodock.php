<?php

require_once("../inc/util.inc");
require_once("../inc/sandbox.inc");
require_once("../inc/submit_util.inc");

function form() {
    page_head("Submit Autodock jobs");
    form_start("autodock.php");
    form_input_text("Receptor<br><small>Must be in your <a href=sandbox.php>sandbox</a></small>", 'file1');
    form_input_text("Ligand", 'file2');
    form_input_textarea("Parameters", 'param');
    form_submit("OK");
    form_end();
    page_tail();
}


function form2($s, $user) {
    if (!$s) {
        page_head("Submit Autodock jobs");
        echo "Select scoring function:
            <ul>
            <li> <a href=autodock.php?s=ad4>Autodock4</a>
            <li> <a href=autodock.php?s=vina>Vina</a>
            <li> <a href=autodock.php?s=vinardo>Vinardo</a>
            </ul>
        ";
        page_tail();
        return;
    }
    switch ($s) {
    case 'ad4': $n = 'Autodock4'; break;
    case 'vina': $n = 'Vina'; break;
    case 'vinardo': $n = 'Vinardo'; break;
    default: error_page('no scoring function');
    }

    page_head("Submit Autodock jobs");
    echo "Scoring function: $n
        <p><a href=autodock.php>Select other scoring function</a>.
    ";
    form_start("autodock.php");
    form_input_hidden('scoring', $s);

    $sbfiles = sandbox_file_names($user);
    $sbitems = [];
    foreach ($sbfiles as $f) {
        if (!preg_match('/.zip$/', $f)) continue;
        $sbitems[] = [$f, $f];
    }
    $sb = "<br><small>Select .zip files in your <a href=sandbox.php>sandbox</a>.
        <br>Use ctrl-click to select multiple files.</small>";
    if ($s == 'ad4') {
        form_select_multiple("Maps$sb", 'maps', $sbitems);
    } else {
        form_select_multiple("Receptors$sb", 'receptors', $sbitems);
    }
    form_select_multiple("Ligands$sb", 'ligands', $sbitems);
    if ($s != 'ad4') {
        form_general('Center',
            "<input name=center_x placeholder=X>
            <input name=center_y placeholder=Y>
            <input name=center_z placeholder=Z>"
        );
        form_general('Size',
            "<input name=size_x placeholder=X>
            <input name=size_y placeholder=Y>
            <input name=size_z placeholder=Z>"
        );
    }
    form_general('<big>Weights</big>','');
    if ($s == 'ad4') {
        form_input_text("vdw", 'weight_vdw');
        form_input_text("hb", 'weight_hb');
        form_input_text("elec", 'weight_elec');
        form_input_text("dsolv", 'weight_dsolv');
    } else {
        form_input_text("Gauss 1", 'weight_gauss_1');
        if ($s == 'vina') {
            form_input_text("Gauss 2", 'weight_gauss_2');
        }
        form_input_text("Repulsion", 'weight_repulsion');
        form_input_text("Hydrophobic", 'weight_hydrophobic');
        form_input_text("Hydrogen", 'weight_hydrogen');
    }
    form_input_text("Rot", 'weight_rot');
    form_checkboxes('Force even voxels',
        [['force_even_voxels', '', false]]
    );
    form_input_text("Macrocycle glue weight", 'weight_glue');
    form_input_text("Exhaustiveness", 'exhaustiveness');
    form_input_text("Max evaluations", 'max_evals');
    form_input_text("Number of binding modes", 'num_modes');
    form_input_text("Minimum RMSD", 'min_rmsd');
    form_input_text("Maximum energy difference", 'energy_range');
    form_input_text("Grid spacing (Angstroms)", 'spacing');


    form_submit('OK', 'name=submit value=on');
    form_end();
    page_tail();
}

function check_double($name, $min, $max) {
    $x = get_str($name, true);
    if ($x === null) return null;
    if ($x === '') return null;
    if (!is_numeric($x)) die("$name is not numeric");
    $x = (double)$x;
    if ($x < $min) die("$name is too small");
    if ($x > $max) die("$name is too big");
    return $x;
}

function check_int($name, $min, $max) {
    $x = get_str($name, true);
    if ($x === null) return null;
    if ($x === '') return null;
    if (!ctype_digit($x)) die("$name is not integer");
    $x = (int)$x;
    if ($x < $min) die("$name is too small");
    if ($x > $max) die("$name is too big");
    return $x;
}

function action2($user) {
    $x = new stdClass;
    $s = get_str('scoring');
    $x->scoring = $s;

    $sbdir = sandbox_dir($user);
    $y = get_str('maps', true);
    if ($y) {
        $x->maps = implode(' ', array_map(function($f) use($sbdir) {return "$sbdir/$f";}, $y));
    }

    $y = get_str('receptors', true);
    if ($y) {
        $x->receptors = implode(' ', array_map(function($f) use($sbdir) {return "$sbdir/$f";}, $y));
    }

    $y = get_str('ligands', true);
    if ($y) {
        $x->ligands = implode(' ', array_map(function($f) use($sbdir) {return "$sbdir/$f";}, $y));
    }

    $y = check_double('center_x', 0, 999.);
    if ($y !== null) $x->center_x = $y;
    $y = check_double('center_y', 0, 999.);
    if ($y !== null) $x->center_y = $y;
    $y = check_double('center_z', 0, 999.);
    if ($y !== null) $x->center_z = $y;

    $y = check_double('size_x', 0, 999.);
    if ($y !== null) $x->size_x = $y;
    $y = check_double('size_y', 0, 999.);
    if ($y !== null) $x->size_y = $y;
    $y = check_double('size_z', 0, 999.);
    if ($y !== null) $x->size_z = $y;

    $y = check_double('weight_gauss1', 0, 999.);
    if ($y !== null) $x->weight_gauss1 = $y;

    $y = check_double('weight_gauss2', 0, 999.);
    if ($y !== null) $x->weight_gauss2 = $y;

    $y = check_double('weight_repulsion', 0, 999.);
    if ($y !== null) $x->weight_repulsion = $y;

    $y = check_double('weight_hydrophobic', 0, 999.);
    if ($y !== null) $x->weight_hydrophobic = $y;

    $y = check_double('weight_hydrogen', 0, 999.);
    if ($y !== null) $x->weight_hydrogen = $y;

    $y = check_double('weight_vdw', 0, 999.);
    if ($y !== null) $x->weight_vdw = $y;

    $y = check_double('weight_hb', 0, 999.);
    if ($y) $x->weight_hb = $y;

    $y = check_double('weight_elec', 0, 999.);
    if ($y) $x->weight_elec = $y;

    $y = check_double('weight_dsolv', 0, 999.);
    if ($y) $x->weight_dsolv = $y;

    $y = check_double('weight_rot', 0, 999.);
    if ($y) $x->weight_rot = $y;

    $y = (boolean)get_str('force_even_voxels', true);
    if ($y) $x->force_even_voxels = $y;

    $y = check_double('weight_glue', 0, 999.);
    if ($y) $x->weight_glue = $y;

    $y = check_int('exhaustiveness', 0, 999.);
    if ($y) $x->exhaustiveness = $y;

    $y = check_int('max_evals', 0, 999.);
    if ($y) $x->max_evals = $y;

    $y = check_int('num_modes', 0, 999.);
    if ($y) $x->num_modes = $y;

    $y = check_double('min_rmsd', 0, 999.);
    if ($y) $x->min_rmsd = $y;

    $y = check_double('energy_range', 0, 999.);
    if ($y) $x->energy_range = $y;

    $y = check_double('spacing', 0, 999.);
    if ($y) $x->spacing = $y;

    echo "<pre>";
    echo json_encode($x, JSON_PRETTY_PRINT);
}

$user = get_logged_in_user();
$up = submit_permissions($user);
if (!$up) error_page("no permissions");
$app = BoincApp::lookup("name='autodock'");
if (!$app) error_page("no app");
if (!$up->submit_all) {
    if (!submit_permissions_app($user, $app)) error_page("no app permissions");
}

//print_r($_GET);
if (get_str('submit', true)) {
    action2($user);
} else {
    $s = get_str('s', true);
    form2($s, $user);
}

?>
