<?php

Use Pockethold\Pockethold;
Use Pockethold\Api;

require_once('loader.php');

if ( !defined('ABSPATH') )
{
    define('ABSPATH', dirname(__FILE__) . '/');
}
$tmppath = (ABSPATH);

$pockethold = new Pockethold(ABSPATH, $tmppath);
$pockethold -> api = new api(ABSPATH, $tmppath);
echo $pockethold -> api -> listen($_REQUEST['ajax']);
