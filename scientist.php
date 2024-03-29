<?php

require_once('../inc/util.inc');

function main() {
    page_head('Computing with BOINC Central');
    text_start();
    echo "
        <p>
        <ul>
        <li> BOINC Central provides computing resources
            to support not-for-profit research at
            universities and research labs.
        <li> BOINC Central supports a limited set of applications, currently
            <ul>
            <li> Autodock Vina
            </ul>
        <li> You can submit jobs through a web interface,
            and in some cases other interfaces.
            For example, Autodock Vina jobs can be
            submitted using Raccoon2.
        </ul>
        <p> If you're interested in computing with BOINC Central:
        <ul>
        <li> <a href=signup.php target=_new>Create an account</a>.
            You don't have to install BOINC, thought we encourage you to do so.
        <li> Fill out <a href=apply.php>this form</a>.
        </ul>
    ";
    text_end();
    page_tail();
}

main();

?>
