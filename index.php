<?php
// This file is part of BOINC.
// https://boinc.berkeley.edu
// Copyright (C) 2025 University of California
//
// BOINC is free software; you can redistribute it and/or modify it
// under the terms of the GNU Lesser General Public License
// as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// BOINC is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with BOINC.  If not, see <http://www.gnu.org/licenses/>.

// BOINC Central front page.
// cases:
// not logged in:
//      Scientists: button
//      Computer Owners: Join button
// logged in, not submitter
//      Scientists: button
//      recent work summary
//      Continue to home page
// logged in, submitter
//      navbar: Job submission menu
//      recent work summary
//      Continue to home page

require_once("../inc/db.inc");
require_once("../inc/util.inc");
require_once("../inc/news.inc");
require_once("../inc/cache.inc");
require_once("../inc/uotd.inc");
require_once("../inc/sanitize_html.inc");
require_once("../inc/text_transform.inc");
require_once("../inc/bootstrap.inc");

$config = get_config();
$no_web_account_creation = parse_bool($config, "no_web_account_creation");
$project_id = parse_config($config, "<project_id>");
    
$stopped = web_stopped();
$user = get_logged_in_user(false);

// The panel at the top of the page
//
function panel_contents() {
}

function top() {
    global $stopped, $master_url, $user;
    if ($stopped) {
        echo '
            <p class="lead text-center">'
            .tra("%1 is temporarily shut down for maintenance.", PROJECT)
            .'</p>
        ';
    }
    //panel(null, 'panel_contents');
}

function intro() {
    global $user;
    echo "<p>
<b>BOINC Central</b> uses
<a href=https://boinc.berkeley.edu>BOINC</a> -
a system for 'volunteer computing',
allowing people to donate computing power to science research.
We let scientists in
<a href=show_apps.php>multiple areas</a>
access the power of volunteer computing
without having to create their own BOINC project.
<p>
We're operated by
<a href=https://boinc.berkeley.edu>the U.C. Berkeley BOINC project</a>.
<p>
";
}

// show user contribution or lack thereof.
// if new, just say welcome
//
function show_user_info($user) {
    echo "<hr>";
    $dt = time() - $user->create_time;
    if ($dt < 86400) {
        echo tra("Thanks for joining %1", PROJECT);
    } else if ($user->total_credit == 0) {
        echo tra("Your computer hasn't completed any tasks yet.  If you need help, %1go here%2.",
                "<a href=https://boinc.berkeley.edu/help.php>",
                "</a>"
        );
    } else {
        $x = format_credit($user->expavg_credit);
        echo tra("You've contributed about %1 credits per day to %2 recently.", $x, PROJECT);
        if ($user->expavg_credit > 1) {
            echo " ";
            echo tra("Thanks!");
        } else {
            echo "<p><p>";
            echo tra("Please make sure BOINC is installed and enabled on your computer.");
        }
    }
    echo "<p><p>";
    echo sprintf('<a href=home.php class="btn btn-success" %s><font size=+1>%s</font></a>
        ',
        button_style(),
        tra('Continue to your home page')
    );
    echo "<p><p>";
    echo sprintf('%s
        <ul>
        <li> %s
        <li> %s
        <li> %s
        ',
        tra("Want to help more?"),
        tra("If BOINC is not installed on this computer, %1download it%2.",
            "<a href=download_software.php>", "</a>"
        ),
        tra("Install BOINC on your other computers, tablets, and phones."),
        tra("Tell your friends about BOINC, and show them how to join %1.", PROJECT)
    );
    echo "</ul>\n";
}

// user is not logged in.
//
function show_join_button() {
    global $no_web_account_creation;
    echo '
        <hr>
        <p>
        <h3>Computer owners:</h3>
        <p>
        Support computational research in
        <a href=show_apps.php>multiple areas</a>.
        <p>
    ';
    echo sprintf(
        '<a href="signup.php" %s class="btn"><font size=+1>%s</font></a>',
        button_style(),
        tra('Join %1', PROJECT)
    );
    echo sprintf('<p><p>%s <a href=%s>%s</a><p>',
        tra('Already joined?'),
        'login_form.php',
        tra('Log in')
    );
}

function scientist_button() {
    echo '<hr><h3>Scientists:</h3>';
    echo "
        <p>
        Need high-throughput computing power
        and can't afford the high cost of commercial clouds?
        We may be able to help,
        by giving you access to thousands of computers at no charge.
        We provide computing to independent researchers
        as well as those from academic institutions.
        <p>
        We support <a href=https://github.com/BOINC/boinc/wiki/BUDA-overview>any application packaged with Docker</a>,
        as well as widely-used science applications like
        <a href=https://autodock.scripps.edu/>Autodock Vina</a>
        from the Scripps Research Institute.
        <p>
        See <a href=videos.php>video tutorials</a> on using Autodock in BOINC Central.
        <p>
    ";
    echo sprintf(
        '<a href="scientist.php" %s class="btn btn-success"><font size=+1>Apply for computing</font></a>
        ',
        button_style()
    );
    echo "
        <p>
        ... or <a href=https://boinc.berkeley.edu/anderson/>contact us</a>.
    ";
}

// user is registered as a job submitter

function scientist_info() {
    echo "<hr>
        <p>
        You're registered as a job submitter.
        Use the commands under the 'Job submission' menu
        to manage files and submit jobs.
        <p>
        See <a href=videos.php>video tutorials</a>.
    ";
}

function left(){
    global $user, $no_web_account_creation, $master_url, $project_id;
    panel(
        $user?tra("Welcome, %1", $user->name):tra("What is %1?", PROJECT),
        function() use($user) {
            global $no_web_account_creation, $master_url, $project_id;
            if ($user) {
                intro();
                show_user_info($user);
                if (BoincUserSubmit::lookup_userid($user->id)) {
                    scientist_info();
                } else {
                    scientist_button();
                }
            } else {
                intro();
                show_join_button();
                scientist_button();
            }
        }
    );
    global $stopped;
    if (!$stopped) {
        $profile = get_current_uotd();
        if ($profile) {
            panel(tra('User of the Day'),
                function() use ($profile) {
                    show_uotd($profile);
                }
            );
        }
    }
}

function right() {
    panel(tra('News'),
        function() {
            include("motd.php");
            if (!web_stopped()) {
                show_news(0, 5);
            }
        }
    );
}

page_head(null, null, true);

grid('top', 'left', 'right');

page_tail(false, "", true);

?>
