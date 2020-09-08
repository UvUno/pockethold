<?php











namespace Composer\Repository;

use Composer\Package\PackageInterface;






class CompositeRepository implements RepositoryInterface
{




private $repositories;





public function __construct(array $repositories)
{
$this->repositories = array();
foreach ($repositories as $repo) {
$this->addRepository($repo);
}
}

public function getRepoName()
{
return 'composite repo ('.implode(', ', array_map(function ($repo) { return $repo->getRepoName(); }, $this->repositories)).')';
}






public function getRepositories()
{
return $this->repositories;
}




public function hasPackage(PackageInterface $package)
{
foreach ($this->repositories as $repository) {

if ($repository->hasPackage($package)) {
return true;
}
}

return false;
}




public function findPackage($name, $constraint)
{
foreach ($this->repositories as $repository) {

$package = $repository->findPackage($name, $constraint);
if (null !== $package) {
return $package;
}
}

return null;
}




public function findPackages($name, $constraint = null)
{
$packages = array();
foreach ($this->repositories as $repository) {

$packages[] = $repository->findPackages($name, $constraint);
}

return $packages ? call_user_func_array('array_merge', $packages) : array();
}




public function loadPackages(array $packageMap, array $acceptableStabilities, array $stabilityFlags)
{
$packages = array();
$namesFound = array();
foreach ($this->repositories as $repository) {

$result = $repository->loadPackages($packageMap, $acceptableStabilities, $stabilityFlags);
$packages[] = $result['packages'];
$namesFound[] = $result['namesFound'];
}

return array(
'packages' => $packages ? call_user_func_array('array_merge', $packages) : array(),
'namesFound' => $namesFound ? array_unique(call_user_func_array('array_merge', $namesFound)) : array(),
);
}




public function search($query, $mode = 0, $type = null)
{
$matches = array();
foreach ($this->repositories as $repository) {

$matches[] = $repository->search($query, $mode, $type);
}

return $matches ? call_user_func_array('array_merge', $matches) : array();
}




public function getPackages()
{
$packages = array();
foreach ($this->repositories as $repository) {

$packages[] = $repository->getPackages();
}

return $packages ? call_user_func_array('array_merge', $packages) : array();
}




public function getProviders($packageName)
{
$results = array();
foreach ($this->repositories as $repository) {

$results[] = $repository->getProviders($packageName);
}

return $results ? call_user_func_array('array_merge', $results) : array();
}




public function removePackage(PackageInterface $package)
{
foreach ($this->repositories as $repository) {

$repository->removePackage($package);
}
}




public function count()
{
$total = 0;
foreach ($this->repositories as $repository) {

$total += $repository->count();
}

return $total;
}





public function addRepository(RepositoryInterface $repository)
{
if ($repository instanceof self) {
foreach ($repository->getRepositories() as $repo) {
$this->addRepository($repo);
}
} else {
$this->repositories[] = $repository;
}
}
}
