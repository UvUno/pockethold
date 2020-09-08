<?php











namespace Composer\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Request;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositorySet;
use Composer\EventDispatcher\Event;






class PackageEvent extends Event
{



private $composer;




private $io;




private $devMode;




private $localRepo;




private $operations;




private $operation;













public function __construct($eventName, Composer $composer, IOInterface $io, $devMode, RepositoryInterface $localRepo, array $operations, OperationInterface $operation)
{
parent::__construct($eventName);

$this->composer = $composer;
$this->io = $io;
$this->devMode = $devMode;
$this->localRepo = $localRepo;
$this->operations = $operations;
$this->operation = $operation;
}




public function getComposer()
{
return $this->composer;
}




public function getIO()
{
return $this->io;
}




public function isDevMode()
{
return $this->devMode;
}




public function getLocalRepo()
{
return $this->localRepo;
}




public function getOperations()
{
return $this->operations;
}






public function getOperation()
{
return $this->operation;
}
}
