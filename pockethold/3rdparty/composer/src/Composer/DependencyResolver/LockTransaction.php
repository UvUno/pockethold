<?php











namespace Composer\DependencyResolver;

use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\Package\AliasPackage;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackageInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Test\Repository\ArrayRepositoryTest;





class LockTransaction extends Transaction
{




protected $presentMap;





protected $unlockableMap;




protected $resultPackages;

public function __construct(Pool $pool, $presentMap, $unlockableMap, $decisions)
{
$this->presentMap = $presentMap;
$this->unlockableMap = $unlockableMap;

$this->setResultPackages($pool, $decisions);
parent::__construct($this->presentMap, $this->resultPackages['all']);

}


 public function setResultPackages(Pool $pool, Decisions $decisions)
{
$this->resultPackages = array('all' => array(), 'non-dev' => array(), 'dev' => array());
foreach ($decisions as $i => $decision) {
$literal = $decision[Decisions::DECISION_LITERAL];

if ($literal > 0) {
$package = $pool->literalToPackage($literal);
$this->resultPackages['all'][] = $package;
if (!isset($this->unlockableMap[$package->id])) {
$this->resultPackages['non-dev'][] = $package;
}
}
}
}

public function setNonDevPackages(LockTransaction $extractionResult)
{
$packages = $extractionResult->getNewLockPackages(false);

$this->resultPackages['dev'] = $this->resultPackages['non-dev'];
$this->resultPackages['non-dev'] = array();

foreach ($packages as $package) {
foreach ($this->resultPackages['dev'] as $i => $resultPackage) {

 if ($package->getName() == $resultPackage->getName()) {
$this->resultPackages['non-dev'][] = $resultPackage;
unset($this->resultPackages['dev'][$i]);
}
}
}
}


 public function getNewLockPackages($devMode, $updateMirrors = false)
{
$packages = array();
foreach ($this->resultPackages[$devMode ? 'dev' : 'non-dev'] as $package) {
if (!($package instanceof AliasPackage) && !($package instanceof RootAliasPackage)) {

 
 if ($updateMirrors && !isset($this->presentMap[spl_object_hash($package)])) {
foreach ($this->presentMap as $presentPackage) {
if ($package->getName() == $presentPackage->getName() &&
$package->getVersion() == $presentPackage->getVersion() &&
$presentPackage->getSourceReference() &&
$presentPackage->getSourceType() === $package->getSourceType()
) {
$package->setSourceDistReferences($presentPackage->getSourceReference());
}
}
}
$packages[] = $package;
}
}

return $packages;
}




public function getAliases($aliases)
{
$usedAliases = array();

foreach ($this->resultPackages['all'] as $package) {
if ($package instanceof AliasPackage) {
foreach ($aliases as $index => $alias) {
if ($alias['package'] === $package->getName()) {
$usedAliases[] = $alias;
unset($aliases[$index]);
}
}
}
}

return $usedAliases;
}
}
