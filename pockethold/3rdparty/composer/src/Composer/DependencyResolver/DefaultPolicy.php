<?php











namespace Composer\DependencyResolver;

use Composer\Package\PackageInterface;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Semver\Constraint\Constraint;





class DefaultPolicy implements PolicyInterface
{
private $preferStable;
private $preferLowest;

public function __construct($preferStable = false, $preferLowest = false)
{
$this->preferStable = $preferStable;
$this->preferLowest = $preferLowest;
}

public function versionCompare(PackageInterface $a, PackageInterface $b, $operator)
{
if ($this->preferStable && ($stabA = $a->getStability()) !== ($stabB = $b->getStability())) {
return BasePackage::$stabilities[$stabA] < BasePackage::$stabilities[$stabB];
}

$constraint = new Constraint($operator, $b->getVersion());
$version = new Constraint('==', $a->getVersion());

return $constraint->matchSpecific($version, true);
}

public function selectPreferredPackages(Pool $pool, array $literals, $requiredPackage = null)
{
$packages = $this->groupLiteralsByName($pool, $literals);
$policy = $this;

foreach ($packages as &$nameLiterals) {
usort($nameLiterals, function ($a, $b) use ($policy, $pool, $requiredPackage) {
return $policy->compareByPriority($pool, $pool->literalToPackage($a), $pool->literalToPackage($b), $requiredPackage, true);
});
}

foreach ($packages as &$sortedLiterals) {
$sortedLiterals = $this->pruneToBestVersion($pool, $sortedLiterals);
$sortedLiterals = $this->pruneRemoteAliases($pool, $sortedLiterals);
}

$selected = \call_user_func_array('array_merge', array_values($packages));


 usort($selected, function ($a, $b) use ($policy, $pool, $requiredPackage) {
return $policy->compareByPriority($pool, $pool->literalToPackage($a), $pool->literalToPackage($b), $requiredPackage);
});

return $selected;
}

protected function groupLiteralsByName(Pool $pool, $literals)
{
$packages = array();
foreach ($literals as $literal) {
$packageName = $pool->literalToPackage($literal)->getName();

if (!isset($packages[$packageName])) {
$packages[$packageName] = array();
}
$packages[$packageName][] = $literal;
}

return $packages;
}




public function compareByPriority(Pool $pool, PackageInterface $a, PackageInterface $b, $requiredPackage = null, $ignoreReplace = false)
{

 if ($a->getName() === $b->getName()) {
$aAliased = $a instanceof AliasPackage;
$bAliased = $b instanceof AliasPackage;
if ($aAliased && !$bAliased) {
return -1; 
 }
if (!$aAliased && $bAliased) {
return 1; 
 }
}

if (!$ignoreReplace) {

 if ($this->replaces($a, $b)) {
return 1; 
 }
if ($this->replaces($b, $a)) {
return -1; 
 }


 
 if ($requiredPackage && false !== ($pos = strpos($requiredPackage, '/'))) {
$requiredVendor = substr($requiredPackage, 0, $pos);

$aIsSameVendor = substr($a->getName(), 0, $pos) === $requiredVendor;
$bIsSameVendor = substr($b->getName(), 0, $pos) === $requiredVendor;

if ($bIsSameVendor !== $aIsSameVendor) {
return $aIsSameVendor ? -1 : 1;
}
}
}


 if ($a->id === $b->id) {
return 0;
}

return ($a->id < $b->id) ? -1 : 1;
}











protected function replaces(PackageInterface $source, PackageInterface $target)
{
foreach ($source->getReplaces() as $link) {
if ($link->getTarget() === $target->getName()


 ) {
return true;
}
}

return false;
}

protected function pruneToBestVersion(Pool $pool, $literals)
{
$operator = $this->preferLowest ? '<' : '>';
$bestLiterals = array($literals[0]);
$bestPackage = $pool->literalToPackage($literals[0]);
foreach ($literals as $i => $literal) {
if (0 === $i) {
continue;
}

$package = $pool->literalToPackage($literal);

if ($this->versionCompare($package, $bestPackage, $operator)) {
$bestPackage = $package;
$bestLiterals = array($literal);
} elseif ($this->versionCompare($package, $bestPackage, '==')) {
$bestLiterals[] = $literal;
}
}

return $bestLiterals;
}






protected function pruneRemoteAliases(Pool $pool, array $literals)
{
$hasLocalAlias = false;

foreach ($literals as $literal) {
$package = $pool->literalToPackage($literal);

if ($package instanceof AliasPackage && $package->isRootPackageAlias()) {
$hasLocalAlias = true;
break;
}
}

if (!$hasLocalAlias) {
return $literals;
}

$selected = array();
foreach ($literals as $literal) {
$package = $pool->literalToPackage($literal);

if ($package instanceof AliasPackage && $package->isRootPackageAlias()) {
$selected[] = $literal;
}
}

return $selected;
}
}
