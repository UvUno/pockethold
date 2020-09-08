<?php











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Symfony\Component\Console\Input\InputInterface;
use Composer\Repository\RepositoryInterface;
use Composer\DependencyResolver\Request;
use Composer\Package\PackageInterface;






class PrePoolCreateEvent extends Event
{



private $repositories;



private $request;



private $acceptableStabilities;



private $stabilityFlags;



private $rootAliases;



private $rootReferences;



private $packages;



private $unacceptableFixedPackages;





public function __construct($name, array $repositories, Request $request, array $acceptableStabilities, array $stabilityFlags, array $rootAliases, array $rootReferences, array $packages, array $unacceptableFixedPackages)
{
parent::__construct($name);

$this->repositories = $repositories;
$this->request = $request;
$this->acceptableStabilities = $acceptableStabilities;
$this->stabilityFlags = $stabilityFlags;
$this->rootAliases = $rootAliases;
$this->rootReferences = $rootReferences;
$this->packages = $packages;
$this->unacceptableFixedPackages = $unacceptableFixedPackages;
}




public function getRepositories()
{
return $this->repositories;
}




public function getRequest()
{
return $this->request;
}




public function getAcceptableStabilities()
{
return $this->acceptableStabilities;
}




public function getStabilityFlags()
{
return $this->stabilityFlags;
}





public function getRootAliases()
{
return $this->rootAliases;
}




public function getRootReferences()
{
return $this->rootReferences;
}




public function getPackages()
{
return $this->packages;
}




public function getUnacceptableFixedPackages()
{
return $this->unacceptableFixedPackages;
}




public function setPackages(array $packages)
{
$this->packages = $packages;
}




public function setUnacceptableFixedPackages(array $packages)
{
$this->unacceptableFixedPackages = $packages;
}
}
