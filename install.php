<?php

/*
Overcomplicating it for more speedy development...
*/
require_once("pockethold/autoloader.php");
// Run Installer
if(!isset($_REQUEST)) {
  $installer = new Pockethold();
  echo $installer->listen();
} else {
  $api = new PocketAPI($_REQUEST);
}


?>
