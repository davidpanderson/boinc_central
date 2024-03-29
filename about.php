<?php

require_once('../inc/util.inc');
page_head('About BOINC Central');
text_start();
echo "
<p>
<a href=https://boinc.berkeley.edu>BOINC</a>
is the leading platform for volunteer computing.
It's used by many science projects, such as
<a href=https://einsteinathome.org/>Einstein@home</a>,
<a href=https://lhcathome.cern.ch/lhcathome/>LHC@home</a>,
<a href=https://boinc.bakerlab.org/rosetta/>Rosetta@home</a>.
and <a href=https://www.worldcommunitygrid.org/>World Community Grid</a>.
<p>
Volunteer computing provides lots of computing power to these projects.
But creating and operating such projects is expensive
and requires resources that most scientists don't have.
<p>
The goal of BOINC Central is to make the power of volunteer computing
available to all scientists,
including those with little money and technical resources
and those whose need for computing is sporadic.
It does this as follows:
<ul>
<li>
BOINC Central is a BOINC project.
We operate a server and maintain this web site,
so scientists don't have to.
<li>
BOINC Central supports widely-used science applications.
Initially we are supporting
<a href=https://autodock.scripps.edu/>Autodock Vina</a>
from the Scripps Research Institute.
We build versions of these applications
for a range of computing platforms:
different operating systems, CPU types, and GPU types.
<li>
Scientists from academic research institutions
can submit batches of jobs for these applications
using a web interface.
Please <a href=https://boinc.berkeley.edu/anderson/>contact us</a>
to register.
</ul>
<p>
BOINC Central is operated by
<a href=https://boinc.berkeley.edu>the U.C. Berkeley BOINC project</a>.
<p>
By participating in BOINC Central
you help broaden the scientific usage of BOINC and volunteer computing.
";
page_tail();
?>
