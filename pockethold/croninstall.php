<?php
/*

Usage: Point a cron job to this file, and visit the installer in your browser to see status (Might take some time)

Cpanel:
Plesk:
Other:
*/
//Check if commandline
if (php_sapi_name() !='cli') exit;


if ( !defined('ABSPATH') )
{
		define('ABSPATH', realpath(__DIR__ . '/..');
}
$tmppath = (ABSPATH . 'pockethold/');

require_once("loader.php");

$cronjob = new Pocketcron(ABSPATH, $tmppath);;
$cronstatus = $cronjob->status;

if($cronstatus == 'finished') {
	$cronjob-logger('Cron called after completion');
	die(Pockethold Complete);
} else {
	if ($cronstatus == 'first') {
		touch('logger/cron/first.start');
		$cronjob->step1;
		touch('logger/cron/first.done');

	} elseif ($cronstatus == 'second') {
		touch('logger/cron/second.start');
		$cronjob->step2;
		touch('logger/cron/second.done');

	} elseif ($cronstatus == 'third') {
		touch('logger/cron/third.start');
		$cronjob->step3;
		touch('logger/cron/third.done');
	}
}
