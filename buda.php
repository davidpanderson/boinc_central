<?php

require_once('../inc/util.inc');
require_once('../inc/sandbox.inc');

error_reporting(E_ALL);
function sandbox_select_items($user, $pattern=null) {
    $sbfiles = sandbox_file_names($user);
    $sbitems = [];
    foreach ($sbfiles as $f) {
        if ($pattern && !preg_match($pattern, $f)) continue;
        $sbitems[] = [$f, $f];
    }
    return $sbitems;
}

function form($user) {
    $sbitems = sandbox_select_items($user);
    $sbitems_zip = sandbox_select_items($user, '/.zip$/');

    page_head("Submit Docker jobs");
    form_start('buda.php');
    form_select('Dockerfile', 'dockerfile', $sbitems);
    form_select('Main program', 'main_prog', $sbitems);
    form_select_multiple('Additional files', 'others', $sbitems);
    form_input_text('Plan class', 'plan_class');
    form_input_text('Output file names', 'output_files');
    form_input_text('Command line', 'cmdline');
    form_select('Job file', 'job_file', $sbitems_zip);
    form_input_text('Batch name', 'batch_name');
    form_submit('OK');
    form_end();
    page_tail();
}

$user = get_logged_in_user();
form($user);

?>
