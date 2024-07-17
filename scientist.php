<?php

require_once('../inc/util.inc');

function main() {
    page_head('Computing with BOINC Central');
    text_start();
    echo "
        <p>
        BOINC Central provides computing resources
            to support not-for-profit research at
            universities and research labs.
        <p>
        BOINC Central supports the following applications:
            <ul>
            <li> Autodock Vina.
        You can submit jobs through a web interface,
            or Raccoon2, using a
            <a href=https://github.com/BOINC/Raccoon2_BOINC_Plugin>Plug-in</a>.
            </ul>

        <p> If you're interested in computing with BOINC Central:
        <ul>
        <li> <a href=signup.php target=_new>Create an account</a>.
            You don't have to install BOINC, though we encourage you to do so.
        <li> Fill out <a href=apply.php>this form</a>.
        </ul>
    ";
    text_end();
    page_tail();
}

main();

?>
