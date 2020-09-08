<?php











namespace Composer\DependencyResolver;

use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\StabilityFilter;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RootPackageRepository;
use Composer\Semver\CompilingMatcher;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;




class PoolBuilder
{



private $acceptableStabilities;



private $stabilityFlags;



private $rootAliases;



private $rootReferences;



private $eventDispatcher;



private $io;




private $aliasMap = array();



private $nameConstraints = array();
private $loadedNames = array();



private $packages = array();



private $unacceptableFixedPackages = array();
private $updateAllowList = array();
private $skippedLoad = array();



private $updateAllowWarned = array();











public function __construct(array $acceptableStabilities, array $stabilityFlags, array $rootAliases, array $rootReferences, IOInterface $io, EventDispatcher $eventDispatcher = null)
{
$this->acceptableStabilities = $acceptableStabilities;
$this->stabilityFlags = $stabilityFlags;
$this->rootAliases = $rootAliases;
$this->rootReferences = $rootReferences;
$this->eventDispatcher = $eventDispatcher;
$this->io = $io;
}

public function buildPool(array $repositories, Request $request)
{
if ($request->getUpdateAllowList()) {
$this->updateAllowList = $request->getUpdateAllowList();
$this->warnAboutNonMatchingUpdateAllowList($request);

foreach ($request->getLockedRepository()->getPackages() as $lockedPackage) {
if (!$this->isUpdateAllowed($lockedPackage)) {
$request->fixPackage($lockedPackage);
$lockedName = $lockedPackage->getName();

 $this->skippedLoad[$lockedName] = $lockedName;
foreach ($lockedPackage->getReplaces() as $link) {
$this->skippedLoad[$link->getTarget()] = $lockedName;
}
}
}
}

$loadNames = array();
foreach ($request->getFixedPackages() as $package) {
$this->nameConstraints[$package->getName()] = null;
$this->loadedNames[$package->getName()] = true;


 foreach ($package->getReplaces() as $link) {
$this->nameConstraints[$package->getName()] = null;
$this->loadedNames[$link->getTarget()] = true;
}


 

if (
$package->getRepository() instanceof RootPackageRepository
|| $package->getRepository() instanceof PlatformRepository
|| StabilityFilter::isPackageAcceptable($this->acceptableStabilities, $this->stabilityFlags, $package->getNames(), $package->getStability())
) {
$loadNames += $this->loadPackage($request, $package, false);
} else {
$this->unacceptableFixedPackages[] = $package;
}
}

foreach ($request->getRequires() as $packageName => $constraint) {

 if (isset($this->loadedNames[$packageName])) {
continue;
}

$loadNames[$packageName] = $constraint;
$this->nameConstraints[$packageName] = $constraint && !($constraint instanceof MatchAllConstraint) ? array($constraint) : null;
}


 foreach ($loadNames as $name => $void) {
if (isset($this->loadedNames[$name])) {
unset($loadNames[$name]);
}
}

while (!empty($loadNames)) {
foreach ($loadNames as $name => $void) {
$this->loadedNames[$name] = true;
}

$newLoadNames = array();
foreach ($repositories as $repository) {

 
 if ($repository instanceof PlatformRepository || $repository === $request->getLockedRepository()) {
continue;
}
$result = $repository->loadPackages($loadNames, $this->acceptableStabilities, $this->stabilityFlags);

foreach ($result['namesFound'] as $name) {

 unset($loadNames[$name]);
}
foreach ($result['packages'] as $package) {
$newLoadNames += $this->loadPackage($request, $package);
}
}

$loadNames = $newLoadNames;
}


 $nameConstraints = array();
foreach ($this->nameConstraints as $name => $constraints) {
if (\is_array($constraints)) {
$nameConstraints[$name] = MultiConstraint::create(array_values(array_unique($constraints)), false);
}
}
foreach ($this->packages as $i => $package) {

 
 if (!$package instanceof AliasPackage && isset($nameConstraints[$package->getName()])) {
$constraint = $nameConstraints[$package->getName()];

$aliasedPackages = array($i => $package);
if (isset($this->aliasMap[spl_object_hash($package)])) {
$aliasedPackages += $this->aliasMap[spl_object_hash($package)];
}

$found = false;
foreach ($aliasedPackages as $packageOrAlias) {
if (CompilingMatcher::match($constraint, Constraint::OP_EQ, $packageOrAlias->getVersion())) {
$found = true;
}
}
if (!$found) {
foreach ($aliasedPackages as $index => $packageOrAlias) {
unset($this->packages[$index]);
}
}
}
}

if ($this->eventDispatcher) {
$prePoolCreateEvent = new PrePoolCreateEvent(
PluginEvents::PRE_POOL_CREATE,
$repositories,
$request,
$this->acceptableStabilities,
$this->stabilityFlags,
$this->rootAliases,
$this->rootReferences,
$this->packages,
$this->unacceptableFixedPackages
);
$this->eventDispatcher->dispatch($prePoolCreateEvent->getName(), $prePoolCreateEvent);
$this->packages = $prePoolCreateEvent->getPackages();
$this->unacceptableFixedPackages = $prePoolCreateEvent->getUnacceptableFixedPackages();
}

$pool = new Pool($this->packages, $this->unacceptableFixedPackages);

$this->aliasMap = array();
$this->nameConstraints = array();
$this->loadedNames = array();
$this->packages = array();
$this->unacceptableFixedPackages = array();

return $pool;
}

private function loadPackage(Request $request, PackageInterface $package, $propagateUpdate = true)
{
end($this->packages);
$index = key($this->packages) + 1;
$this->packages[] = $package;

if ($package instanceof AliasPackage) {
$this->aliasMap[spl_object_hash($package->getAliasOf())][$index] = $package;
}

$name = $package->getName();


 
 
 if (isset($this->rootReferences[$name])) {

 if (!$request->isFixedPackage($package)) {
$package->setSourceDistReferences($this->rootReferences[$name]);
}
}


 
 if ($propagateUpdate && isset($this->rootAliases[$name][$package->getVersion()])) {
$alias = $this->rootAliases[$name][$package->getVersion()];
if ($package instanceof AliasPackage) {
$basePackage = $package->getAliasOf();
} else {
$basePackage = $package;
}
$aliasPackage = new AliasPackage($basePackage, $alias['alias_normalized'], $alias['alias']);
$aliasPackage->setRootPackageAlias(true);

$this->packages[] = $aliasPackage;
$this->aliasMap[spl_object_hash($aliasPackage->getAliasOf())][$index+1] = $aliasPackage;
}

$loadNames = array();
foreach ($package->getRequires() as $link) {
$require = $link->getTarget();
if (!isset($this->loadedNames[$require])) {
$loadNames[$require] = null;

 
 } elseif ($propagateUpdate && $request->getUpdateAllowTransitiveDependencies() && isset($this->skippedLoad[$require])) {
if ($request->getUpdateAllowTransitiveRootDependencies() || !$this->isRootRequire($request, $this->skippedLoad[$require])) {
$this->unfixPackage($request, $require);
$loadNames[$require] = null;
} elseif (!$request->getUpdateAllowTransitiveRootDependencies() && $this->isRootRequire($request, $require) && !isset($this->updateAllowWarned[$require])) {
$this->updateAllowWarned[$require] = true;
$this->io->writeError('<warning>Dependency "'.$require.'" is also a root requirement. Package has not been listed as an update argument, so keeping locked at old version. Use --with-all-dependencies to include root dependencies.</warning>');
}
}

$linkConstraint = $link->getConstraint();
if ($linkConstraint && !($linkConstraint instanceof MatchAllConstraint)) {
if (!\array_key_exists($require, $this->nameConstraints)) {
$this->nameConstraints[$require] = array($linkConstraint);
} elseif (\is_array($this->nameConstraints[$require])) {
$this->nameConstraints[$require][] = $linkConstraint;
}

 } else {
$this->nameConstraints[$require] = null;
}
}


 
 if ($propagateUpdate && $request->getUpdateAllowTransitiveDependencies()) {
foreach ($package->getReplaces() as $link) {
$replace = $link->getTarget();
if (isset($this->loadedNames[$replace]) && isset($this->skippedLoad[$replace])) {
if ($request->getUpdateAllowTransitiveRootDependencies() || !$this->isRootRequire($request, $this->skippedLoad[$replace])) {
$this->unfixPackage($request, $replace);
$loadNames[$replace] = null;

 $this->nameConstraints[$replace] = null;
} elseif (!$request->getUpdateAllowTransitiveRootDependencies() && $this->isRootRequire($request, $replace) && !isset($this->updateAllowWarned[$replace])) {
$this->updateAllowWarned[$replace] = true;
$this->io->writeError('<warning>Dependency "'.$replace.'" is also a root requirement. Package has not been listed as an update argument, so keeping locked at old version. Use --with-all-dependencies to include root dependencies.</warning>');
}
}
}
}

return $loadNames;
}






private function isRootRequire(Request $request, $name)
{
$rootRequires = $request->getRequires();
return isset($rootRequires[$name]);
}





private function isUpdateAllowed(PackageInterface $package)
{
foreach ($this->updateAllowList as $pattern => $void) {
$patternRegexp = BasePackage::packageNameToRegexp($pattern);
if (preg_match($patternRegexp, $package->getName())) {
return true;
}
}

return false;
}

private function warnAboutNonMatchingUpdateAllowList(Request $request)
{
foreach ($this->updateAllowList as $pattern => $void) {
$patternRegexp = BasePackage::packageNameToRegexp($pattern);

 foreach ($request->getLockedRepository()->getPackages() as $package) {
if (preg_match($patternRegexp, $package->getName())) {
continue 2;
}
}

 foreach ($request->getRequires() as $packageName => $constraint) {
if (preg_match($patternRegexp, $packageName)) {
continue 2;
}
}
if (strpos($pattern, '*') !== false) {
$this->io->writeError('<warning>Pattern "' . $pattern . '" listed for update does not match any locked packages.</warning>');
} else {
$this->io->writeError('<warning>Package "' . $pattern . '" listed for update is not locked.</warning>');
}
}
}





private function unfixPackage(Request $request, $name)
{

 foreach ($request->getLockedRepository()->getPackages() as $lockedPackage) {
if (!($lockedPackage instanceof AliasPackage) && $lockedPackage->getName() === $name) {
if (false !== $index = array_search($lockedPackage, $this->packages, true)) {
$request->unfixPackage($lockedPackage);
unset($this->packages[$index]);
if (isset($this->aliasMap[spl_object_hash($lockedPackage)])) {
foreach ($this->aliasMap[spl_object_hash($lockedPackage)] as $aliasIndex => $aliasPackage) {
$request->unfixPackage($aliasPackage);
unset($this->packages[$aliasIndex]);
}
unset($this->aliasMap[spl_object_hash($lockedPackage)]);
}
}
}
}

if (

 $this->skippedLoad[$name] !== $name

 && isset($this->skippedLoad[$this->skippedLoad[$name]])
) {
$this->unfixPackage($request, $this->skippedLoad[$name]);
}

unset($this->skippedLoad[$name]);
unset($this->loadedNames[$name]);
}
}

