<?php

require_once('../inc/util.inc');

function form() {
    page_head('Apply to BOINC Central');
    form_start('apply.php', 'get');
    form_input_hidden('submit', 1);
    form_input_text('Institution:', 'institution');
    form_input_text('URL of page identifying you there:', 'url');
    form_input_text('Where did you hear about BOINC Central?', 'hear');
    form_input_textarea('Research for which you need computing', 'research');
    form_submit('OK');
    page_tail();
}

function action($user) {
    $subject = 'BOINC Central application';
    $body = sprintf('User: %s (%d)
    institution: %s
    URL: %s
    research: %s
    heard about us from: %s
',
        $user->email_addr, $user->id,
        get_str('institution', true),
        get_str('url', true),
        get_str('research', true),
        get_str('hear', true)
    );
    send_email(null, $subject, $body, null, 'davea@berkeley.edu lestat.de.lionkur@gmail.com');

    page_head('Message sent');
    echo "We'll review your application and get back to you in a day or two.";
    page_tail();
}

$user = get_logged_in_user();
if (get_str('submit', true)) {
    action($user);
} else {
    form();
}
