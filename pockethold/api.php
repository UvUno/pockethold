<?php

require_once('loader.php');

$pockethold = new pockethold(ABSPATH, $tmppath);
$pockethold -> api = new api();
$pockethold -> api -> listen($request);
