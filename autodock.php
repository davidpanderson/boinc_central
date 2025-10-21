<?php

// Form and handler for submitting Autodock jobs
//
// The user selects a scoring type (ad4/vina/vinardo)
// and two zipped directories from their sandbox
//
// vina/vinardo:
// The directories (ligands and receptors) can contain only .pdbqt files.
// A job is created for each combination of ligand and receptor.
//
// ad4:
// The ligand directory is as above.
// The 2nd dir (maps) has sets of files
// foo.pdbqt [foo.X.map foo.glg foo.gpf foo.X.fld foo.X.xyz]
// A job is created for each combo of ligand and foo.pdbqt.
// Its input files include foo.*
//
// If a file with unexpected name or type is found, error out.

require_once("../inc/util.inc");
require_once("../inc/sandbox.inc");
require_once("../inc/submit_util.inc");

// allowed file types

define('MAP_TYPES', ['.map', '.glg', '.gpf', '.fld', '.xyz', '.pdbqt']);
define('LIGAND_TYPES', ['.pdbqt']);
define('RECEPTOR_TYPES', ['.pdbqt']);

// if the directory contains a single item, and it's a dir, return its name.
//
function contains_single_dir($dir) {
    $items = scandir($dir);
    $child = null;
    foreach ($items as $f) {
        if ($f[0] == '.') continue;
        if (is_dir("$dir/$f")) {
            if ($child) return null;
            $child = $f;
        } else {
            return null;
        }
    }
    return $child;
}

function small_line($msg) {
    return "<br><small>$msg</small>";
}

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

    page_head_select2("Submit Autodock jobs");
    echo "
        <p>
        For detailed instructions click
        <a href=https://github.com/BOINC/boinc-autodock-vina/wiki>here</a>.
        <hr>
        Scoring function: $n
        <br><a href=autodock.php>Select a different scoring function</a>
    ";
    $us = BoincUserSubmit::lookup_userid($user->id);
    if ($us->max_jobs_in_progress) {
        $n = n_jobs_in_progress($user->id);
        echo sprintf(
            '<p>Note: you are limited to %d jobs in progress,
            and you currently have %d, so this batch can be at most %d jobs.',
            $us->max_jobs_in_progress, $n,
            $us->max_jobs_in_progress - $n
        );
    }
    form_start("autodock.php");
    form_general('* = required fields', '');
    form_input_hidden('scoring', $s);

    $sbfiles = sandbox_file_names($user);
    $sbitems = [];
    foreach ($sbfiles as $f) {
        if (!preg_match('/.zip$/', $f)) continue;
        $sbitems[] = [$f, $f];
    }
    $sb = small_line('Select a zipped directory (.zip) in your <a href=sandbox.php>sandbox</a>');

    if ($s == 'ad4') {
        $mt = implode(', ', MAP_TYPES);
        $mt = small_line("It must contain files of type $mt");
        //form_select2_multi("* Maps $sb $mt", 'maps', $sbitems, null, "id=maps");
        form_select("* Maps $sb $mt", 'maps', $sbitems, null, "id=maps");
    } else {
        $mt = implode(', ', RECEPTOR_TYPES);
        $mt = small_line("It must contain files of type $mt");
        //form_select2_multi("* Receptors $sb $mt", 'receptors', $sbitems, null, "id=receptors");
        form_select("* Receptors $sb $mt", 'receptors', $sbitems, null, "id=receptors");
    }
    $mt = implode(', ', LIGAND_TYPES);
    $mt = small_line("It must contain files of type $mt");
    //form_select2_multi("* Ligands $sb $mt", 'ligands', $sbitems, null, "id=ligands");
    form_select("* Ligands $sb $mt", 'ligands', $sbitems, null, "id=ligands");
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
    if (!is_numeric($x)) {
        error_page("$name is not numeric");
    }
    $x = (double)$x;
    if ($min!==null && $x < $min) {
        error_page("$name is too small");
    }
    if ($max!==null && $x > $max) {
        error_page("$name is too big");
    }
    return $x;
}

function check_int($name, $min=null, $max=null) {
    $x = get_str($name, true);
    if ($x === null) return null;
    if ($x === '') return null;
    if (!ctype_digit($x)) {
        error_page("$name is not integer");
    }
    $x = (int)$x;
    if ($min!==null && $x < $min) {
        error_page("$name is too small");
    }
    if ($max!==null && $x > $max) {
        error_page("$name is too big");
    }
    return $x;
}

// this is in PHP 8
//
function str_ends_with($haystack, $needle) {
    $length = strlen( $needle );
    if (!$length) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}

// return list of .pdbqt files in the dir.
// Ignore other files, but error out if they have suffixes not in $suffixes
//
function get_files($dir, $suffixes, $type) {
    $files = scandir($dir);
    $out = [];
    foreach ($files as $f) {
        if ($f[0] == '.') continue;
        if (str_ends_with($f, '.pdbqt')) {
            $out[] = $f;
            continue;
        }
        $found = false;
        foreach ($suffixes as $suffix) {
            if (str_ends_with($f, $suffix)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $s = implode(', ', $suffixes);
            error_page(
                "Your $type directory has a file $f.
                Only file types in [$s] are allowed.
                "
            );
        }
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
// $other is the name of map or receptor file
//
// Return a line to pass to stdin of create_work
//
function make_job($batch_desc, $batch_id, $seqno, $ligand, $other) {
    $job_name = sprintf('autodock_%d_%d', $batch_id, $seqno);
    $batch_dir = sprintf('submit/%d', $batch_id);
    $job_dir = sprintf('%s/%s', $batch_dir, $job_name);
    mkdir($job_dir);

    // the batch desc has parameters that we need to pass
    //
    $job_desc = clone $batch_desc;
    copy("$batch_dir/ligands/$ligand", "$job_dir/$ligand");
    $job_desc->ligands = $ligand;
    if ($job_desc->scoring == 'ad4') {
        copy_map_files("$batch_dir/maps", $other, $job_dir);
        $job_desc->maps = explode('.pdbqt', $other)[0];
    } else {
        copy("$batch_dir/receptors/$other", "$job_dir/$other");
        $job_desc->receptors = $other;
    }

    $job_desc->seed = mt_rand();
    $job_desc->out = sprintf('%s_%s_%s_out.pdbqt',
        explode('.pdbqt', $ligand)[0],
        explode('.pdbqt', $other)[0],
        $job_desc->scoring
    );
    $j = json_encode($job_desc, JSON_PRETTY_PRINT);
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

// call this if error happened after creating the batch.
//
function bail($batch, $dir, $msg) {
    $batch->delete();
    system("rm -r $dir");

    page_head("No jobs created");
    echo $msg;
    page_tail();
    exit;
}

// create a batch and jobs for the given batch descriptor
// input files are in user sandbox
//
function make_batch($desc, $user) {
    page_head('Creating jobs');
    echo 'This takes on the order of 1 sec per 100 jobs,
        so for large batches it may take a while.
        Please wait, and do not reload this page.
        <p>
        Unzipping directories... 
    ';
    ob_flush(); flush();
        
    // make a batch
    //
    $now = time();
    $app = BoincApp::lookup("name='autodock'");
    $batch_name = sprintf('autodock_batch_%s', time());
    $batch_id = BoincBatch::insert(
        sprintf(
            "(user_id, create_time, name, app_id, state) values (%d, %d, '%s', %d, %d)",
            $user->id, $now, $batch_name,
            $app->id, BATCH_STATE_IN_PROGRESS
        )
    );
    $batch = BoincBatch::lookup_id($batch_id);
    if (!$batch) {
        die ("no batch $batch_id");
    }

    // make a temp dir for the batch

    @mkdir('submit');
    $batch_dir = sprintf('submit/%d', $batch_id);
    @mkdir($batch_dir);

    // unzip the input files

    $ligands_dir = "$batch_dir/ligands";
    @mkdir($ligands_dir);
    $ligands_phys = sandbox_path($user, $desc->ligands);
    if (!$ligands_phys) {
        error_page("no ligands file $desc->ligands");
    }
    $cmd = sprintf("unzip -q '%s' -d %s", $ligands_phys, $ligands_dir);
    //echo "ligands cmd: $cmd\n";
    $x = system($cmd, $retval);
    if ($retval && $retval != 1) {
        error_page("Command failed: $cmd: $retval; $x\n");
    }
    
    $d = contains_single_dir($ligands_dir);
    if ($d) {
        $ligands_dir = "$ligands_dir/$d";
    }

    $ligands = get_files($ligands_dir, LIGAND_TYPES, 'ligands');
    //print_r($ligands);

    if ($desc->scoring == 'ad4') {
        $maps_dir = "$batch_dir/maps";
        @mkdir($maps_dir);
        $maps_phys = sandbox_path($user, $desc->maps);
        if (!$maps_phys) {
            error_page("no maps file $desc->maps");
        }
        $cmd = sprintf("unzip -q '%s' -d %s", $maps_phys, $maps_dir);
        //echo "maps cmd: $cmd\n";
        $x = system($cmd, $retval);
        if ($retval && $retval != 1) {
            error_page("Command failed: $cmd: $retval; $x\n");
        }

        $d = contains_single_dir($maps_dir);
        if ($d) {
            $maps_dir = "$maps_dir/$d";
        }

        $maps = get_files($maps_dir, MAP_TYPES, 'maps');
        //print_r($maps);
    } else {
        $receptors_dir = "$batch_dir/receptors";
        @mkdir($receptors_dir);
        $receptors_phys = sandbox_path($user, $desc->receptors);
        if (!$receptors_phys) {
            error_page("no receptors file $desc->receptors");
        }
        $cmd = sprintf("unzip -q '%s' -d %s", $receptors_phys, $receptors_dir);
        //echo "receptors cmd: $cmd\n";
        $x = system($cmd, $retval);
        if ($retval && $retval != 1) {
            error_page("Command failed: $cmd: $retval; $x\n");
        }

        $d = contains_single_dir($receptors_dir);
        if ($d) {
            $receptors_dir = "$receptors_dir/$d";
        }

        $receptors = get_files($receptors_dir, RECEPTOR_TYPES, 'receptors');
        //print_r($receptors);
    }

    // make sure there are any jobs
    //
    $msg = null;
    if (!$ligands) {
        $msg = 'No ligands specified.';
    }
    if ($desc->scoring == 'ad4') {
        if (!$maps) {
            $msg = 'No maps specified.';
        }
    } else {
        if (!$receptors) {
            $msg = 'No receptors specified.';
        }
    }
    if ($msg) {
        bail($batch, $batch_dir, "$msg Check your .zip files.");
    }

    echo 'Done.  <p> Creating job descriptions... ';
    ob_flush(); flush();

    // make a job for each combination of ligand and receptor
    //
    $seqno = 0;
    $cw_string = '';
    if ($desc->scoring == 'ad4') {
        foreach ($ligands as $ligand) {
            foreach ($maps as $map) {
                $cw_string .= make_job(
                    $desc, $batch_id, $seqno++, $ligand, $map
                );
            }
        }
    } else {
        foreach ($ligands as $ligand) {
            foreach ($receptors as $receptor) {
                $cw_string .= make_job(
                    $desc, $batch_id, $seqno++, $ligand, $receptor
                );
            }
        }
    }

    if ($seqno > 10 && $user->seti_id) {
        bail($batch, $batch_dir,
            "Batches with > 10 jobs are not allowed if 'use only my computers' is set"
        );
    }

    $us = BoincUserSubmit::lookup_userid($user->id);
    if ($us->max_jobs_in_progress) {
        $n = n_jobs_in_progress($user->id);
        if ($n + $seqno > $us->max_jobs_in_progress) {
            bail($batch, $batch_dir,
                sprintf(
                    'This batch is %d jobs, and you already have %d in-progress jobs.
                    This would exceed your limit of %d in-progress jobs.
                    ',
                    $seqno, $n, $us->max_jobs_in_progress
                )
            );
        }
    }

    echo "Done ($seqno jobs).<p>Creating jobs... ";
    ob_flush(); flush();

    $batch->update("njobs=$seqno");

    $cmd = sprintf(
        'cd ../..; bin/create_work --appname autodock --batch %d --stdin ',
        $batch_id
    );
    if ($user->seti_id) {
        $cmd .= " --target_user $user->id ";
    }
    $errfile = sprintf('html/user/submit/%d/cw_err.txt', $batch_id);
    $cmd .= sprintf(' 2>&1 > %s', $errfile);
    //echo "<br>$cmd<br>";
    //echo "cw_string: [$cw_string]</br>";
    $h = popen($cmd, 'w');
    if ($h === false) error_page("can't run create_work");
    fwrite($h, $cw_string);
    $ret = pclose($h);
    if ($ret) {
        echo "create_work failed: $ret";
        $err = file_get_contents($errfile);
        echo "<pre>$err</pre>";
    }
    echo 'Done.<p>Removing temp files... ';
    ob_flush(); flush();

    system("rm -r $batch_dir");
    echo "
        Done.
        <p><p>
        Job creation is complete.
        <p>
        Go to the <a href=submit.php?action=query_batch&batch_id=$batch->id>batch status page</a>.
    ";
    page_tail();
}

// parse form args, create batch descriptor, call make_batch()
//
function action($user) {
    $desc = new stdClass;
    $s = get_str('scoring');
    $desc->scoring = $s;

    $y = get_str('maps', true);
    if ($y) {
        //$desc->maps = $y[0];
        $desc->maps = $y;
    } else if ($s == 'ad4') {
        error_page('no map specified');
    }

    $y = get_str('receptors', true);
    if ($y) {
        //$desc->receptors = $y[0];
        $desc->receptors = $y;
    } else if ($s != 'ad4') {
        error_page('no receptor specified');
    }

    $y = get_str('ligands', true);
    if ($y) {
        //$desc->ligands = $y[0];
        $desc->ligands = $y;
    } else {
        error_page('no ligands specified');
    }

    $y = check_double('center_x');
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $desc->center_x = $y;
    $y = check_double('center_y');
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $desc->center_y = $y;
    $y = check_double('center_z');
    if ($y === null and $s != 'ad4') error_page('missing center');
    if ($y !== null) $desc->center_z = $y;

    $y = check_double('size_x');
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $desc->size_x = $y;
    $y = check_double('size_y');
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $desc->size_y = $y;
    $y = check_double('size_z');
    if ($y === null and $s != 'ad4') error_page('missing size');
    if ($y !== null) $desc->size_z = $y;

    $y = check_double('weight_gauss1');
    if ($y !== null) $desc->weight_gauss1 = $y;

    $y = check_double('weight_gauss2');
    if ($y !== null) $desc->weight_gauss2 = $y;

    $y = check_double('weight_repulsion');
    if ($y !== null) $desc->weight_repulsion = $y;

    $y = check_double('weight_hydrophobic');
    if ($y !== null) $desc->weight_hydrophobic = $y;

    $y = check_double('weight_hydrogen');
    if ($y !== null) $desc->weight_hydrogen = $y;

    $y = check_double('weight_vdw');
    if ($y !== null) $desc->weight_vdw = $y;

    $y = check_double('weight_hb');
    if ($y) $desc->weight_hb = $y;

    $y = check_double('weight_elec');
    if ($y) $desc->weight_elec = $y;

    $y = check_double('weight_dsolv');
    if ($y) $desc->weight_dsolv = $y;

    $y = check_double('weight_rot');
    if ($y) $desc->weight_rot = $y;

    $y = (boolean)get_str('force_even_voxels', true);
    if ($y) $desc->force_even_voxels = $y;

    $y = check_double('weight_glue');
    if ($y) $desc->weight_glue = $y;

    $y = check_int('exhaustiveness');
    if ($y) $desc->exhaustiveness = $y;

    $y = check_int('max_evals');
    if ($y) $desc->max_evals = $y;

    $y = check_int('num_modes');
    if ($y) $desc->num_modes = $y;

    $y = check_double('min_rmsd');
    if ($y) $desc->min_rmsd = $y;

    $y = check_double('energy_range');
    if ($y) $desc->energy_range = $y;

    $y = check_double('spacing');
    if ($y) $desc->spacing = $y;

    //echo "<pre>";
    //echo json_encode($desc, JSON_PRETTY_PRINT);
    make_batch($desc, $user);
}

$user = get_logged_in_user();
$app = BoincApp::lookup("name='autodock'");
if (!$app) error_page("no app");
if (!has_submit_access($user, $app->id)) {
    error_page("no app permissions");
}

//print_r($_GET);
if (get_str('submit', true)) {
    action($user);
} else {
    $s = get_str('s', true);
    form($s, $user);
}

?>
