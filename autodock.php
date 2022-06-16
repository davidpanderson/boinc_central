<?php

require_once("../inc/util.inc");
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

class AutodockConfig {
}

function generate_config($receptor, $ligand) {
    $config = new AutodockConfig();
    $config->receptor = $receptor;
    $config->ligands = array($ligand);

    return json_encode($config, JSON_PRETTY_PRINT);
}

$user = get_logged_in_user();
$up = submit_permissions($user);
if (!$up) error_page("no permissions");
$app = BoincApp::lookup("name='autodock'");
if (!$app) error_page("no app");
if (!$up->submit_all) {
    if (!submit_permissions_app($user, $app)) error_page("no app permissions");
}

form();

?>
