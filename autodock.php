<?php

require_once("../inc/util.inc");
require_once("../inc/sandbox.inc");
require_once("../inc/submit_util.inc");

function form($s, $user) {
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
        form_input_text("Gauss 1", 'weight_gauss1');
        if ($s == 'vina') {
            form_input_text("Gauss 2", 'weight_gauss2');
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

function check_double($name, $min=null, $max=null) {
    $x = get_str($name, true);
    if ($x === null) return null;
    if ($x === '') return null;
    if (!is_numeric($x)) die("$name is not numeric");
    $x = (double)$x;
    if ($min!==null && $x < $min) die("$name is too small");
    if ($max!==null && $x > $max) die("$name is too big");
    return $x;
}

function check_int($name, $min=null, $max=null) {
    $x = get_str($name, true);
    if ($x === null) return null;
    if ($x === '') return null;
    if (!ctype_digit($x)) die("$name is not integer");
    $x = (int)$x;
    if ($min!==null && $x < $min) die("$name is too small");
    if ($max!==null && $x > $max) die("$name is too big");
    return $x;
}

function get_files($dir, $suffix) {
    $files = scandir($dir);
    $out = [];
    foreach ($files as $f) {
        if ($f[0] == '.') continue;
        if (!strstr($f, '.pdbqt')) continue;
        $out[] = $f;
    }
    return $out;
}

// $fname is of the form X.pdbqt
// copy all files $src_dir/X.Y  to $dst_dir
//
function copy_map_files($src_dir, $fname, $dst_dir) {
    $base_name = explode('.pdbqt', $fname)[0];
    $files = scandir($src_dir);
    foreach ($files as $f) {
        if ($f[0] == '.') continue;
        if (strpos($f, $base_name) === 0) {
            copy("$src_dir/$f", "$dst_dir/$f");
        }
    }
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

// create a job for vina or vinardo
// - copy the files to a temp "job dir"
// - add a JSON file
// - zip the dir
// - stage the zip file
// - create the job
//
// Return a line to pass to stdin of create_work
//
function make_job($desc, $batch_id, $seqno, $ligand, $other) {
    $job_name = sprintf('autodock_%d_%d', $batch_id, $seqno);
    $batch_dir = sprintf('submit/%d', $batch_id);
    $job_dir = sprintf('%s/%s', $batch_dir, $job_name);
    echo "job dir: $job_dir\n";
    mkdir($job_dir);
    $desc2 = clone $desc;
    copy("$batch_dir/ligands/$ligand", "$job_dir/$ligand");
    $desc2->ligands = $ligand;
    if ($desc->scoring == 'ad4') {
        copy_map_files("$batch_dir/maps", $other, $job_dir);
        $desc2->maps = explode('.pdbqt', $other)[0];
    } else {
        copy("$batch_dir/receptors/$other", "$job_dir/$other");
        $desc2->receptors = $other;
    }

    $desc2->seed = mt_rand();
    $desc2->out = sprintf('%s_%s_%s_out.pdbqt',
        explode('.pdbqt', $ligand)[0],
        explode('.pdbqt', $other)[0],
        $desc->scoring
    );
    $j = json_encode($desc2, JSON_PRETTY_PRINT);
    file_put_contents("$job_dir/desc.json", $j);
    $cmd = sprintf('zip -j -q -r %s.zip %s/*', $job_dir, $job_dir);
        // -j means archive just the files, not the dir structure
    //echo "cmd: $cmd\n";
    system($cmd);

    if (0) {
        echo sprintf(
            '<p>%s: <a href=submit/%d/%s>dir</a> | <a href=submit/%d/%s.zip>zip</a>',
            $job_name,
            $batch_id, $job_name,
            $batch_id, $job_name
        );
    }

    $path = stage_file_basic($batch_dir, "$job_name.zip");
    system($cmd);

    return "--command_line 'input.zip output.zip' $job_name.zip\n";
}

// create a batch and jobs for the given batch descriptor
// input files are in user sandbox
//
function make_batch($desc, $user) {
    // make a batch
    //
if (false) {
    $batch_id = 0;
    system('rm -rf submit/0/*');
} else {
    $now = time();
    $app = BoincApp::lookup("name='autodock'");
    $batch_id = BoincBatch::insert(
        "(user_id, create_time, name, app_id, state) values ($user->id, $now, 'autodock batch', $app->id, ".BATCH_STATE_IN_PROGRESS.")"
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
    $cmd = sprintf("unzip -q %s -d %s", $ligands_phys, $dir_ligands);
    //echo "ligands cmd: $cmd\n";
    $x = system($cmd, $retval);
    if ($retval && $retval != 1) die("ERROR: $cmd: $retval; $x\n");
    $ligands = get_files($dir_ligands, '.pdbqt');
    //print_r($ligands);

    if ($desc->scoring == 'ad4') {
        $dir_maps = "$dir/maps";
        @mkdir($dir_maps);
        $maps_phys = sandbox_log_to_phys($user, $desc->maps);
        if (!$maps_phys) die("no maps file $desc->maps");
        $cmd = sprintf("unzip -q %s -d %s", $maps_phys, $dir_maps);
        //echo "maps cmd: $cmd\n";
        $x = system($cmd, $retval);
        if ($retval && $retval != 1) die("ERROR: $cmd: $retval; $x\n");
        $maps = get_files($dir_maps, '.pdbqt');
        //print_r($maps);
    } else {
        $dir_receptors = "$dir/receptors";
        @mkdir($dir_receptors);
        $receptors_phys = sandbox_log_to_phys($user, $desc->receptors);
        if (!$receptors_phys) die("no receptors file $desc->receptors");
        $cmd = sprintf("unzip -q %s -d %s", $receptors_phys, $dir_receptors);
        //echo "receptors cmd: $cmd\n";
        $x = system($cmd, $retval);
        if ($retval && $retval != 1) die("ERROR: $cmd: $retval; $x\n");
        $receptors = get_files($dir_receptors, '.pdbqt');
        //print_r($receptors);
    }

    page_head('Jobs');
    // make a job for each combination of ligand and receptor
    //
    $seqno = 0;
    $cw_string = '';
    if ($desc->scoring == 'ad4') {
        foreach ($ligands as $ligand) {
            foreach ($maps as $map) {
                $cw_string .= make_job($desc, $batch_id, $seqno++, $ligand, $map);
            }
        }
    } else {
        foreach ($ligands as $ligand) {
            foreach ($receptors as $receptor) {
                $cw_string .= make_job($desc, $batch_id, $seqno++, $ligand, $receptor);
            }
        }
    }
    $batch = BoincBatch::lookup_id($batch_id);
    if ($batch) {
        $batch->update("njobs=$seqno");
    } else {
        die ("no batch $batch_id");
    }

    $cmd = sprintf(
        'cd ../..; bin/create_work --appname autodock --batch %d --stdin ',
        $batch_id
    );
    if ($user->seti_id) {
        $cmd .= " --target_user $user->id ";
    }
    $cmd .= sprintf(' 2>&1 > html/user/submit/%d/cw_err.txt',
        $batch_id
    );
    echo "<br>$cmd<br>";
    echo "cw_string: [$cw_string]</br>";
    $h = popen($cmd, 'w');
    if ($h === false) error_page("can't run create_work");
    fwrite($h, $cw_string);
    $ret = pclose($h);
    if ($ret) {
        echo "create_work failed: $ret";
        $err = file_get_contents($errfile);
        echo "<pre>$err</pre>";
    }
    page_tail();
}

function action($user) {
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

    $y = check_double('center_x');
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $x->center_x = $y;
    $y = check_double('center_y');
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $x->center_y = $y;
    $y = check_double('center_z');
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $x->center_z = $y;

    $y = check_double('size_x');
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $x->size_x = $y;
    $y = check_double('size_y');
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $x->size_y = $y;
    $y = check_double('size_z');
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $x->size_z = $y;

    $y = check_double('weight_gauss1');
    if ($y !== null) $x->weight_gauss1 = $y;

    $y = check_double('weight_gauss2');
    if ($y !== null) $x->weight_gauss2 = $y;

    $y = check_double('weight_repulsion');
    if ($y !== null) $x->weight_repulsion = $y;

    $y = check_double('weight_hydrophobic');
    if ($y !== null) $x->weight_hydrophobic = $y;

    $y = check_double('weight_hydrogen');
    if ($y !== null) $x->weight_hydrogen = $y;

    $y = check_double('weight_vdw');
    if ($y !== null) $x->weight_vdw = $y;

    $y = check_double('weight_hb');
    if ($y) $x->weight_hb = $y;

    $y = check_double('weight_elec');
    if ($y) $x->weight_elec = $y;

    $y = check_double('weight_dsolv');
    if ($y) $x->weight_dsolv = $y;

    $y = check_double('weight_rot');
    if ($y) $x->weight_rot = $y;

    $y = (boolean)get_str('force_even_voxels', true);
    if ($y) $x->force_even_voxels = $y;

    $y = check_double('weight_glue');
    if ($y) $x->weight_glue = $y;

    $y = check_int('exhaustiveness');
    if ($y) $x->exhaustiveness = $y;

    $y = check_int('max_evals');
    if ($y) $x->max_evals = $y;

    $y = check_int('num_modes');
    if ($y) $x->num_modes = $y;

    $y = check_double('min_rmsd');
    if ($y) $x->min_rmsd = $y;

    $y = check_double('energy_range');
    if ($y) $x->energy_range = $y;

    $y = check_double('spacing');
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
    action($user);
} else {
    $s = get_str('s', true);
    form($s, $user);
}

?>
