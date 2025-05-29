<?php

require_once('../inc/keywords.inc');

$buda = new StdClass;
$buda->app_name = 'buda';
$buda->form = 'buda_submit.php';
$buda->logo = 'images/docker2.png';
$buda->is_assim_move = true;
$buda->long_name = "BOINC Universal Docker App";

$autodock = new StdClass;
$autodock->app_name = 'autodock';
$autodock->form = 'autodock.php?s=ad4';
$autodock->logo = 'images/autodock.png';
$autodock->is_assim_move = true;
$autodock->long_name = 'Autodock';
$autodock->description = 'Autodock predicts how small molecules, such as substrates or drug candidates, bind to a receptor such as a protein.';
$autodock->url = 'https://autodock.scripps.edu/';
$autodock->sci_kw = [KW_BIOMED, KW_DRUG_DISCOVERY];

$vina = new StdClass;
$vina->app_name = 'autodock';
$vina->form = 'autodock.php?s=vina';
$vina->logo = 'images/vina.png';
$vina->is_assim_move = true;
$vina->long_name = 'Autodock Vina';
$vina->description = 'A version of Autodock that improves speed and accuracy';
$vina->url = 'https://vina.scripps.edu/';
$vina->sci_kw = [KW_BIOMED, KW_DRUG_DISCOVERY];

$vinardo = new StdClass;
$vinardo->app_name = 'autodock';
$vinardo->form = 'autodock.php?s=vinardo';
$vinardo->logo = 'images/vinardo.png';
$vinardo->is_assim_move = true;
$vinardo->long_name = 'Autodock Vinardo';
$vinardo->description = 'A version of Autodock that improves speed and accuracy';
$vinardo->url = 'https://journals.plos.org/plosone/article?id=10.1371/journal.pone.0155183';
$vinardo->sci_kw = [KW_BIOMED, KW_DRUG_DISCOVERY];

$remote_apps = [
    'Biomedical apps' => [$autodock, $vina, $vinardo],
    'Universal Docker app' => [$buda]
];

?>
