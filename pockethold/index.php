<?php

if(file_exists('../install.php')) {
  header('Location: ../install.php');
} elseif (file_exists('../index.php') {
  header('Location: ../index.php');
} else {
  //Run full validation check.
  //require_once(include.php);
}
 ?>
