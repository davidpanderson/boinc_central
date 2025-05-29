<?php

require_once('../inc/util.inc');

function main() {
    page_head('Computing with BOINC Central');
    text_start();
    echo "
        <p>
        BOINC Central provides computing power
            to not-for-profit research projects at
            universities and research labs,
            and to independent researchers.
            Jobs run on the CPUs and GPUs of thousands
            of volunteered home computers.

        <h3>Applications</h3>
        <p>
        BOINC Central provides widely-used applications
        (currently Autodock and variants).
        Or you can supply your own applications, packaged using Docker.
        <a href=show_apps.php>See current applications</a>.

        <h3>Getting started</h3>
        <p> If you're interested in computing with BOINC Central:
        <ul>
        <li> <a href=signup.php target=_new>Register with BOINC Central</a>.
            You don't have to install BOINC on your own computer,
            though we encourage you to do so.
        <li> Apply for computing by <a href=apply.php>filling out this form</a>.
        </ul>
    ";
    text_end();
    page_tail();
}

main();

?>
