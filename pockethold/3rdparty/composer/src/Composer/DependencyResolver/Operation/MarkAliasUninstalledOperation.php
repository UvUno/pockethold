<?php











namespace Composer\DependencyResolver\Operation;

use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;






class MarkAliasUninstalledOperation extends SolverOperation
{
protected $package;







public function __construct(AliasPackage $package, $reason = null)
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
return 'markAliasUninstalled';
}




public function show($lock)
{
return 'Marking <info>'.$this->package->getPrettyName().'</info> (<comment>'.$this->package->getFullPrettyVersion().'</comment>) as uninstalled, alias of <info>'.$this->package->getAliasOf()->getPrettyName().'</info> (<comment>'.$this->package->getAliasOf()->getFullPrettyVersion().'</comment>)';
}




public function __toString()
{
return $this->show(false);
}
}
