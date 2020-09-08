<?php











namespace Composer\DependencyResolver;

use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootAliasPackage;
use Composer\Repository\LockArrayRepository;
use Composer\Semver\Constraint\ConstraintInterface;




class Request
{



const UPDATE_ONLY_LISTED = 0;





const UPDATE_LISTED_WITH_TRANSITIVE_DEPS_NO_ROOT_REQUIRE = 1;





const UPDATE_LISTED_WITH_TRANSITIVE_DEPS = 2;

protected $lockedRepository;
protected $requires = array();
protected $fixedPackages = array();
protected $unlockables = array();
protected $updateAllowList = array();
protected $updateAllowTransitiveDependencies = false;

public function __construct(LockArrayRepository $lockedRepository = null)
{
$this->lockedRepository = $lockedRepository;
}

public function requireName($packageName, ConstraintInterface $constraint = null)
{
$packageName = strtolower($packageName);
$this->requires[$packageName] = $constraint;
}






public function fixPackage(PackageInterface $package, $lockable = true)
{
$this->fixedPackages[spl_object_hash($package)] = $package;

if (!$lockable) {
$this->unlockables[spl_object_hash($package)] = $package;
}
}

public function unfixPackage(PackageInterface $package)
{
unset($this->fixedPackages[spl_object_hash($package)]);
unset($this->unlockables[spl_object_hash($package)]);
}

public function setUpdateAllowList($updateAllowList, $updateAllowTransitiveDependencies)
{
$this->updateAllowList = $updateAllowList;
$this->updateAllowTransitiveDependencies = $updateAllowTransitiveDependencies;
}

public function getUpdateAllowList()
{
return $this->updateAllowList;
}

public function getUpdateAllowTransitiveDependencies()
{
return $this->updateAllowTransitiveDependencies !== self::UPDATE_ONLY_LISTED;
}

public function getUpdateAllowTransitiveRootDependencies()
{
return $this->updateAllowTransitiveDependencies === self::UPDATE_LISTED_WITH_TRANSITIVE_DEPS;
}

public function getRequires()
{
return $this->requires;
}

public function getFixedPackages()
{
return $this->fixedPackages;
}

public function isFixedPackage(PackageInterface $package)
{
return isset($this->fixedPackages[spl_object_hash($package)]);
}


 
 public function getPresentMap($packageIds = false)
{
$presentMap = array();

if ($this->lockedRepository) {
foreach ($this->lockedRepository->getPackages() as $package) {
$presentMap[$packageIds ? $package->id : spl_object_hash($package)] = $package;
}
}

foreach ($this->fixedPackages as $package) {
$presentMap[$packageIds ? $package->id : spl_object_hash($package)] = $package;
}

return $presentMap;
}

public function getUnlockableMap()
{
$unlockableMap = array();

foreach ($this->unlockables as $package) {
$unlockableMap[$package->id] = $package;
}

return $unlockableMap;
}

public function getLockedRepository()
{
return $this->lockedRepository;
}
}
