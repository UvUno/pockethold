<?php











namespace Composer\DependencyResolver;

use Composer\Package\AliasPackage;
use Composer\Package\Version\VersionParser;
use Composer\Semver\CompilingMatcher;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Package\PackageInterface;







class Pool implements \Countable
{
protected $packages = array();
protected $packageByName = array();
protected $versionParser;
protected $providerCache = array();
protected $unacceptableFixedPackages;

public function __construct(array $packages = array(), array $unacceptableFixedPackages = array())
{
$this->versionParser = new VersionParser;
$this->setPackages($packages);
$this->unacceptableFixedPackages = $unacceptableFixedPackages;
}

private function setPackages(array $packages)
{
$id = 1;

foreach ($packages as $package) {
$this->packages[] = $package;

$package->id = $id++;

foreach ($package->getNames() as $provided) {
$this->packageByName[$provided][] = $package;
}
}
}







public function packageById($id)
{
return $this->packages[$id - 1];
}




public function count()
{
return \count($this->packages);
}









public function whatProvides($name, ConstraintInterface $constraint = null)
{
$key = (string) $constraint;
if (isset($this->providerCache[$name][$key])) {
return $this->providerCache[$name][$key];
}

return $this->providerCache[$name][$key] = $this->computeWhatProvides($name, $constraint);
}




private function computeWhatProvides($name, $constraint)
{
if (!isset($this->packageByName[$name])) {
return array();
}

$matches = array();

foreach ($this->packageByName[$name] as $candidate) {
if ($this->match($candidate, $name, $constraint)) {
$matches[] = $candidate;
}
}

return $matches;
}

public function literalToPackage($literal)
{
$packageId = abs($literal);

return $this->packageById($packageId);
}

public function literalToPrettyString($literal, $installedMap)
{
$package = $this->literalToPackage($literal);

if (isset($installedMap[$package->id])) {
$prefix = ($literal > 0 ? 'keep' : 'remove');
} else {
$prefix = ($literal > 0 ? 'install' : 'don\'t install');
}

return $prefix.' '.$package->getPrettyString();
}










public function match($candidate, $name, ConstraintInterface $constraint = null)
{
$candidateName = $candidate->getName();
$candidateVersion = $candidate->getVersion();

if ($candidateName === $name) {
if ($constraint === null || CompilingMatcher::match($constraint, Constraint::OP_EQ, $candidateVersion)) {
return true;
}

return false;
}

$provides = $candidate->getProvides();
$replaces = $candidate->getReplaces();


 if (isset($replaces[0]) || isset($provides[0])) {
foreach ($provides as $link) {
if ($link->getTarget() === $name && ($constraint === null || $constraint->matches($link->getConstraint()))) {
return true;
}
}

foreach ($replaces as $link) {
if ($link->getTarget() === $name && ($constraint === null || $constraint->matches($link->getConstraint()))) {
return true;
}
}

return false;
}

if (isset($provides[$name]) && ($constraint === null || $constraint->matches($provides[$name]->getConstraint()))) {
return true;
}

if (isset($replaces[$name]) && ($constraint === null || $constraint->matches($replaces[$name]->getConstraint()))) {
return true;
}

return false;
}

public function isUnacceptableFixedPackage(PackageInterface $package)
{
return \in_array($package, $this->unacceptableFixedPackages, true);
}

public function __toString()
{
$str = "Pool:\n";

foreach ($this->packages as $package) {
$str .= '- '.str_pad($package->id, 6, ' ', STR_PAD_LEFT).': '.$package->getName()."\n";
}

return $str;
}
}
