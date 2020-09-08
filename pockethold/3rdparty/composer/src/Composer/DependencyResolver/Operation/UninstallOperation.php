<?php











namespace Composer\DependencyResolver\Operation;

use Composer\Package\PackageInterface;






class UninstallOperation extends SolverOperation
{
protected $package;







public function __construct(PackageInterface $package, $reason = null)
{
parent::__construct($reason);

$this->package = $package;
}






public function getPackage()
{
return $this->package;
}






public function getOperationType()
{
return 'uninstall';
}




public function show($lock)
{
return self::format($this->package, $lock);
}

public static function format(PackageInterface $package, $lock = false)
{
return 'Removing <info>'.$package->getPrettyName().'</info> (<comment>'.$package->getFullPrettyVersion().'</comment>)';
}




public function __toString()
{
return $this->show(false);
}
}
