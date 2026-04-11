<?php
require_once('../inc/util.inc');

page_head('Computing projects');
text_start();
echo "
<p>
BOINC Central volunteers have provided computing power to these projects:
<p>

<h2>Boolean chains</h2>
<p>
This project searches for the shortest Boolean chains for specific functions,
inspired by problems discussed in The Art of Computer Programming Vol. 4A
by Donald E. Knuth.
<p>
Researcher: Oliver Runge
<p>
<a href=https://orunge.org/boolean-chains/>Web site</a>

<h2>Cislunar Orbit Stability Analyzer</h2>
<p>
This application computes the Jacobi constant for spacecraft orbits
in the Earth-Moon system, a key metric for stability analysis.
It processes public simulation data from
Lawrence Livermore National Laboratory.
The research aims to map stable regions in cislunar space
by performing a massive computational survey,
contributing to future mission design.
<p>
Researcher: Lezhe Gao
<p>
<a href=https://www.preprints.org/manuscript/202604.0498>Draft paper</a>
";
text_end();
page_tail();
?>
