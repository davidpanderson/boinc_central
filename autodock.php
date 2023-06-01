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
    echo "<p>
        Scoring function: $n
        <br><a href=autodock.php>Select other scoring function</a>.
        <p>* = required fields
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
        form_select_multiple("* Maps$sb", 'maps', $sbitems);
    } else {
        form_select_multiple("* Receptors$sb", 'receptors', $sbitems);
    }
    form_select_multiple("* Ligands$sb", 'ligands', $sbitems);
    if ($s != 'ad4') {
        form_general('* Center',
            "<input name=center_x placeholder=X>
            <input name=center_y placeholder=Y>
            <input name=center_z placeholder=Z>"
        );
        form_general('* Size',
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

function get_files($dir, $suffix) {
    $files = scandir($dir);
    $out = [];
    foreach ($files as $f) {
        if ($f[0] == '.') continue;
        if (!strstr($f, '.pdbqt')) die("bad file $f");
        $out[] = $f;
    }
    return $out;
}

// directory structure:
// html/user/submit/
//      batchid/
//          zipped and unzipped ligand/map/rec files
//          these are temp files; can delete dir when done
//          Keep them here to avoid collisions if multiple submissions at same time
// physical filenames
// input file: autodock_userid_batchid_jobno_in.zip
//      jobno is 0,1,...
// output file: autodock_userid_batchid_jobno_out.zip

// create a job for a file pair
// - copy the files to a temp dir
// - add a JSON file
// - zip the dir
// - stage the zip file
// - create the job
//
function make_job($desc, $batch_id, $seqno, $ligand, $receptor) {
    $job_name = sprintf('autodock_%d_%d', $batch_id, $seqno);
    $batch_dir = sprintf('submit/%d', $batch_id);
    $job_dir = sprintf('%s/%s', $batch_dir, $job_name);
    mkdir($job_dir);
    copy("$batch_dir/ligands/$ligand", "$job_dir/$ligand");
    copy("$batch_dir/receptors/$receptor", "$job_dir/$receptor");
    $desc2 = clone $desc;
    $desc->ligands = $ligand;
    $desc->receptors = $receptor;
    $j = json_encode($desc2, JSON_PRETTY_PRINT);
    file_put_contents("$job_dir/desc.json", $j);
    $cmd = sprintf('zip -r %s.zip %s/*', $job_dir, $job_dir);
    echo "cmd: $cmd\n";
    system($cmd);
    exit;
    stage_local_file(sprintf('autodock/%s.zip', $job_name));

    $cmd = sprintf('create_work --appname autodock --batch %d autodock/%s.zip',
        $batch_id, $job_name
    );
    system($cmd);
}

// create a batch and jobs for the given batch descriptor
// input files are in user sandbox
//
function make_batch($desc, $user) {
    // make a batch
    //
if (true) {
    $batch_id = 0;
} else {
    echo "<pre>\n";
    $now = time();
    $njobs = count($ligands)*count($receptors);
    $app = BoincApp::lookup_name('autodock');
    $batch_id = BoincBatch::insert(
        "(user_id, create_time, njobs, name, app_id, state) values ($user->id, $now, $njobs, 'autodock batch', $app->id, ".BATCH_STATE_IN_PROGRESS.")"
    );
}

    // make a temp dir for the batch

    $dir = sprintf('submit/%d', $batch_id);
    @mkdir($dir);

    // unzip the input files

    $dir_ligands = "$dir/ligands";
    @mkdir($dir_ligands);
    $ligands_phys = sandbox_log_to_phys($user, $desc->ligands);
    if (!$ligands_phys) die("no ligands file $desc->ligands");
    $cmd = sprintf("unzip %s -d %s", $ligands_phys, $dir_ligands);
    echo "ligand cmd: $cmd\n";
    $x = system($cmd, $retval);
    if ($retval && $retval != 1) die("ERROR: $cmd: $retval; $x\n");
    $ligands = get_files($dir_ligands, '.pdbqt');

    print_r($ligands);
    echo "done with ligands\n";

    $dir_receptors = "$dir/receptors";
    @mkdir($dir_receptors);
    $receptors_phys = sandbox_log_to_phys($user, $desc->receptors);
    if (!$receptors_phys) die("no receptors file $desc->receptors");
    $cmd = sprintf("unzip %s -d %s", $receptors_phys, $dir_receptors);
    echo "receptors cmd: $cmd\n";
    $x = system($cmd, $retval);
    if ($retval && $retval != 1) die("ERROR: $cmd: $retval; $x\n");
    $receptors = get_files($dir_receptors, '.pdbqt');

    print_r($receptors);
    echo "done with receptors\n";

    // make a job for each combination of ligand and receptor
    //
    $seqno = 0;
    foreach ($ligands as $ligand) {
        foreach ($receptors as $receptor) {
            make_job($desc, $batch_id, $seqno++, $ligand, $receptor);
        }
    }
    echo "done";
}

function action2($user) {
    $x = new stdClass;
    $s = get_str('scoring');
    $x->scoring = $s;

    $y = get_str('maps', true);
    if ($y) {
        $x->maps = $y[0];
    } else if ($s == 'ad4') {
        error_page('no map specified');
    }

    $y = get_str('receptors', true);
    if ($y) {
        $x->receptors = $y[0];
    } else if ($s != 'ad4') {
        error_page('no receptor specified');
    }

    $y = get_str('ligands', true);
    if ($y) {
        $x->ligands = $y[0];
    } else {
        error_page('no ligands specified');
    }

    $y = check_double('center_x', 0, 999.);
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $x->center_x = $y;
    $y = check_double('center_y', 0, 999.);
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $x->center_y = $y;
    $y = check_double('center_z', 0, 999.);
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $x->center_z = $y;

    $y = check_double('size_x', 0, 999.);
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $x->size_x = $y;
    $y = check_double('size_y', 0, 999.);
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $x->size_y = $y;
    $y = check_double('size_z', 0, 999.);
    if ($y === null and $s != 'ad4') error_page('missing size');
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

    //echo "<pre>";
    //echo json_encode($x, JSON_PRETTY_PRINT);
    make_batch($x, $user);
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
