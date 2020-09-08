<?php











namespace Composer\Repository;

use Composer\Package\PackageInterface;
use Composer\Package\BasePackage;






class FilterRepository implements RepositoryInterface
{
private $only = array();
private $exclude = array();
private $canonical = true;
private $repo;

public function __construct(RepositoryInterface $repo, array $options)
{
if (isset($options['only'])) {
if (!is_array($options['only'])) {
throw new \InvalidArgumentException('"only" key for repository '.$repo->getRepoName().' should be an array');
}
$this->only = '{^'.implode('|', array_map(function ($val) {
return BasePackage::packageNameToRegexp($val, '%s');
}, $options['only'])) .'$}iD';
}
if (isset($options['exclude'])) {
if (!is_array($options['exclude'])) {
throw new \InvalidArgumentException('"exclude" key for repository '.$repo->getRepoName().' should be an array');
}
$this->exclude = '{^'.implode('|', array_map(function ($val) {
return BasePackage::packageNameToRegexp($val, '%s');
}, $options['exclude'])) .'$}iD';
}
if ($this->exclude && $this->only) {
throw new \InvalidArgumentException('Only one of "only" and "exclude" can be specified for repository '.$repo->getRepoName());
}
if (isset($options['canonical'])) {
if (!is_bool($options['canonical'])) {
throw new \InvalidArgumentException('"canonical" key for repository '.$repo->getRepoName().' should be a boolean');
}
$this->canonical = $options['canonical'];
}

$this->repo = $repo;
}

public function getRepoName()
{
return $this->repo->getRepoName();
}






public function getRepository()
{
return $this->repo;
}




public function hasPackage(PackageInterface $package)
{
return $this->repo->hasPackage($package);
}




public function findPackage($name, $constraint)
{
if (!$this->isAllowed($name)) {
return null;
}

return $this->repo->findPackage($name, $constraint);
}




public function findPackages($name, $constraint = null)
{
if (!$this->isAllowed($name)) {
return array();
}

return $this->repo->findPackages($name, $constraint);
}




public function loadPackages(array $packageMap, array $acceptableStabilities, array $stabilityFlags)
{
foreach ($packageMap as $name => $constraint) {
if (!$this->isAllowed($name)) {
unset($packageMap[$name]);
}
}

if (!$packageMap) {
return array('namesFound' => array(), 'packages' => array());
}

$result = $this->repo->loadPackages($packageMap, $acceptableStabilities, $stabilityFlags);
if (!$this->canonical) {
$result['namesFound'] = array();
}

return $result;
}




public function search($query, $mode = 0, $type = null)
{
$result = array();

foreach ($this->repo->search($query, $mode, $type) as $package) {
if ($this->isAllowed($package['name'])) {
$result[] = $package;
}
}

return $result;
}




public function getPackages()
{
$result = array();
foreach ($this->repo->getPackages() as $package) {
if ($this->isAllowed($package->getName())) {
$result[] = $package;
}
}

return $result;
}




public function getProviders($packageName)
{
$result = array();
foreach ($this->repo->getProviders($packageName) as $provider) {
if ($this->isAllowed($provider['name'])) {
$result[] = $provider;
}
}

return $result;
}




public function removePackage(PackageInterface $package)
{
return $this->repo->removePackage($package);
}




public function count()
{
if ($this->repo->count() > 0) {
return count($this->getPackages());
}

return 0;
}

private function isAllowed($name)
{
if (!$this->only && !$this->exclude) {
return true;
}

if ($this->only) {
return (bool) preg_match($this->only, $name);
}

return !preg_match($this->exclude, $name);
}
}
