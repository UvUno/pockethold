<?php











namespace Composer\DependencyResolver\Operation;

use Composer\Package\PackageInterface;






class InstallOperation extends SolverOperation
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
return 'install';
}




public function show($lock)
{
return self::format($this->package, $lock);
}

public static function format(PackageInterface $package, $lock = false)
{
return ($lock ? 'Locking ' : 'Installing ').'<info>'.$package->getPrettyName().'</info> (<comment>'.$package->getFullPrettyVersion().'</comment>)';
}




public function __toString()
{
return $this->show(false);
}
}
