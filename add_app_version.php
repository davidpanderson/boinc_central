<?php

// remotely add an app version
// 

require_once("../inc/util.inc");
require_once("../inc/bootstrap.inc");

function form() {
    page_head("Add app version");
    form_start("add_app_version.php", 'post', 'ENCTYPE="multipart/form-data"');
    form_input_hidden("action", "add");
    form_general("Zip file", "<input name=zipfile type=file>");
    form_submit("OK");
    form_end();
    page_tail();
}

function get_upload_file($name, $dest_name) {
    if ($_FILES[$name]['error'] != UPLOAD_ERR_OK) return -1;
    $tmp_name = $_FILES[$name]['tmp_name'];
    if (!move_uploaded_file($tmp_name, $dest_name)) return -1;
    return 0;
}

function action() {
    if (get_upload_file('zipfile', 'aav.zip')) {
        error_page("can't upload zip file");
    }
    $cmd = "unzip -u aav.zip -d ../../apps > /dev/null";
    passthru($cmd, $ret);
    if ($ret) error_page("unzip failed: $cmd");

    page_head("Adding app version");
    echo "<pre>";
    $cmd = "cd ../..; bin/update_versions --noconfirm";
    passthru($cmd, $ret);
    echo "</pre>";
    if ($ret) echo "update_versions failed: $cmd returned $ret";
    page_tail();
}

$user = get_logged_in_user();
$uids = parse_config(get_config(), "<add_av_userids>");
if (!$uids) die('no uids');
$uids = explode(',', $uids);
if (!in_array($user->id, $uids)) {
    die('bad uid');
}

$action = post_str("action", true);
if ($action == "add") {
    action();
} else {
    form();
}

?>
