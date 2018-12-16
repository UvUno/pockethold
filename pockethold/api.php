<?php
require_once("autoloader.php");

$api = new PocketAPI($_REQUEST);

if(!isset($_REQUEST)) {
  die('Not a valid API Call');
  $api->logger('Unvalid API Request');
} else {
  $api->logger('API Call: ' . $_REQUEST);
  $api->listen($_REQUEST);
}

 ?>
