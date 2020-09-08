<?php











namespace Composer\DependencyResolver\Operation;






interface OperationInterface
{





public function getOperationType();






public function getReason();







public function show($lock);






public function __toString();
}
