<?php











namespace Composer\Repository;

use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\StabilityFilter;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\Constraint;






class ArrayRepository implements RepositoryInterface
{

protected $packages;




protected $packageMap;

public function __construct(array $packages = array())
{
foreach ($packages as $package) {
$this->addPackage($package);
}
}

public function getRepoName()
{
return 'array repo (defining '.$this->count().' package'.($this->count() > 1 ? 's' : '').')';
}




public function loadPackages(array $packageMap, array $acceptableStabilities, array $stabilityFlags)
{
$packages = $this->getPackages();

$result = array();
$namesFound = array();
foreach ($packages as $package) {
if (array_key_exists($package->getName(), $packageMap)) {
if (
(!$packageMap[$package->getName()] || $packageMap[$package->getName()]->matches(new Constraint('==', $package->getVersion())))
&& StabilityFilter::isPackageAcceptable($acceptableStabilities, $stabilityFlags, $package->getNames(), $package->getStability())
) {

 $result[spl_object_hash($package)] = $package;

 if ($package instanceof AliasPackage && !isset($result[spl_object_hash($package->getAliasOf())])) {
$result[spl_object_hash($package->getAliasOf())] = $package->getAliasOf();
}
}

$namesFound[$package->getName()] = true;
}
}


 foreach ($packages as $package) {
if ($package instanceof AliasPackage) {
if (isset($result[spl_object_hash($package->getAliasOf())])) {
$result[spl_object_hash($package)] = $package;
}
}
}

return array('namesFound' => array_keys($namesFound), 'packages' => $result);
}




public function findPackage($name, $constraint)
{
$name = strtolower($name);

if (!$constraint instanceof ConstraintInterface) {
$versionParser = new VersionParser();
$constraint = $versionParser->parseConstraints($constraint);
}

foreach ($this->getPackages() as $package) {
if ($name === $package->getName()) {
$pkgConstraint = new Constraint('==', $package->getVersion());
if ($constraint->matches($pkgConstraint)) {
return $package;
}
}
}

return null;
}




public function findPackages($name, $constraint = null)
{

 $name = strtolower($name);
$packages = array();

if (null !== $constraint && !$constraint instanceof ConstraintInterface) {
$versionParser = new VersionParser();
$constraint = $versionParser->parseConstraints($constraint);
}

foreach ($this->getPackages() as $package) {
if ($name === $package->getName()) {
if (null === $constraint || $constraint->matches(new Constraint('==', $package->getVersion()))) {
$packages[] = $package;
}
}
}

return $packages;
}




public function search($query, $mode = 0, $type = null)
{
$regex = '{(?:'.implode('|', preg_split('{\s+}', $query)).')}i';

$matches = array();
foreach ($this->getPackages() as $package) {
$name = $package->getName();
if (isset($matches[$name])) {
continue;
}
if (preg_match($regex, $name)
|| ($mode === self::SEARCH_FULLTEXT && $package instanceof CompletePackageInterface && preg_match($regex, implode(' ', (array) $package->getKeywords()) . ' ' . $package->getDescription()))
) {
if (null !== $type && $package->getType() !== $type) {
continue;
}

$matches[$name] = array(
'name' => $package->getPrettyName(),
'description' => $package instanceof CompletePackageInterface ? $package->getDescription() : null,
);
}
}

return array_values($matches);
}




public function hasPackage(PackageInterface $package)
{
if ($this->packageMap === null) {
$this->packageMap = array();
foreach ($this->getPackages() as $repoPackage) {
$this->packageMap[$repoPackage->getUniqueName()] = $repoPackage;
}
}

return isset($this->packageMap[$package->getUniqueName()]);
}






public function addPackage(PackageInterface $package)
{
if (null === $this->packages) {
$this->initialize();
}
$package->setRepository($this);
$this->packages[] = $package;

if ($package instanceof AliasPackage) {
$aliasedPackage = $package->getAliasOf();
if (null === $aliasedPackage->getRepository()) {
$this->addPackage($aliasedPackage);
}
}


 $this->packageMap = null;
}




public function getProviders($packageName)
{
$result = array();

foreach ($this->getPackages() as $candidate) {
if (isset($result[$candidate->getName()])) {
continue;
}
foreach ($candidate->getProvides() as $link) {
if ($packageName === $link->getTarget()) {
$result[$candidate->getName()] = array(
'name' => $candidate->getName(),
'description' => $candidate->getDescription(),
'type' => $candidate->getType(),
);
continue 2;
}
}
}

return $result;
}

protected function createAliasPackage(PackageInterface $package, $alias, $prettyAlias)
{
return new AliasPackage($package instanceof AliasPackage ? $package->getAliasOf() : $package, $alias, $prettyAlias);
}






public function removePackage(PackageInterface $package)
{
$packageId = $package->getUniqueName();

foreach ($this->getPackages() as $key => $repoPackage) {
if ($packageId === $repoPackage->getUniqueName()) {
array_splice($this->packages, $key, 1);


 $this->packageMap = null;

return;
}
}
}




public function getPackages()
{
if (null === $this->packages) {
$this->initialize();
}

return $this->packages;
}






public function count()
{
if (null === $this->packages) {
$this->initialize();
}

return count($this->packages);
}




protected function initialize()
{
$this->packages = array();
}
}
