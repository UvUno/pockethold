<?php











namespace Composer\Package\Archiver;

use Composer\Downloader\DownloadManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;
use Composer\Util\Loop;
use Composer\Json\JsonFile;





class ArchiveManager
{

protected $downloadManager;

protected $loop;




protected $archivers = array();




protected $overwriteFiles = true;




public function __construct(DownloadManager $downloadManager, Loop $loop)
{
$this->downloadManager = $downloadManager;
$this->loop = $loop;
}




public function addArchiver(ArchiverInterface $archiver)
{
$this->archivers[] = $archiver;
}








public function setOverwriteFiles($overwriteFiles)
{
$this->overwriteFiles = $overwriteFiles;

return $this;
}








public function getPackageFilename(PackageInterface $package)
{
if ($package->getArchiveName()) {
$baseName = $package->getArchiveName();
} else {
$baseName = preg_replace('#[^a-z0-9-_]#i', '-', $package->getName());
}
$nameParts = array($baseName);

if (preg_match('{^[a-f0-9]{40}$}', $package->getDistReference())) {
array_push($nameParts, $package->getDistReference(), $package->getDistType());
} else {
array_push($nameParts, $package->getPrettyVersion(), $package->getDistReference());
}

if ($package->getSourceReference()) {
$nameParts[] = substr(sha1($package->getSourceReference()), 0, 6);
}

$name = implode('-', array_filter($nameParts, function ($p) {
return !empty($p);
}));

return str_replace('/', '-', $name);
}














public function archive(PackageInterface $package, $format, $targetDir, $fileName = null, $ignoreFilters = false)
{
if (empty($format)) {
throw new \InvalidArgumentException('Format must be specified');
}


 $usableArchiver = null;
foreach ($this->archivers as $archiver) {
if ($archiver->supports($format, $package->getSourceType())) {
$usableArchiver = $archiver;
break;
}
}


 if (null === $usableArchiver) {
throw new \RuntimeException(sprintf('No archiver found to support %s format', $format));
}

$filesystem = new Filesystem();

if ($package instanceof RootPackageInterface) {
$sourcePath = realpath('.');
} else {

 $sourcePath = sys_get_temp_dir().'/composer_archive'.uniqid();
$filesystem->ensureDirectoryExists($sourcePath);

try {

 $promise = $this->downloadManager->download($package, $sourcePath);
$this->loop->wait(array($promise));
$this->downloadManager->install($package, $sourcePath);
} catch (\Exception $e) {
$filesystem->removeDirectory($sourcePath);
throw $e;
}


 if (file_exists($composerJsonPath = $sourcePath.'/composer.json')) {
$jsonFile = new JsonFile($composerJsonPath);
$jsonData = $jsonFile->read();
if (!empty($jsonData['archive']['name'])) {
$package->setArchiveName($jsonData['archive']['name']);
}
if (!empty($jsonData['archive']['exclude'])) {
$package->setArchiveExcludes($jsonData['archive']['exclude']);
}
}
}

if (null === $fileName) {
$packageName = $this->getPackageFilename($package);
} else {
$packageName = $fileName;
}


 $filesystem->ensureDirectoryExists($targetDir);
$target = realpath($targetDir).'/'.$packageName.'.'.$format;
$filesystem->ensureDirectoryExists(dirname($target));

if (!$this->overwriteFiles && file_exists($target)) {
return $target;
}


 $tempTarget = sys_get_temp_dir().'/composer_archive'.uniqid().'.'.$format;
$filesystem->ensureDirectoryExists(dirname($tempTarget));

$archivePath = $usableArchiver->archive($sourcePath, $tempTarget, $format, $package->getArchiveExcludes(), $ignoreFilters);
$filesystem->rename($archivePath, $target);


 if (!$package instanceof RootPackageInterface) {
$filesystem->removeDirectory($sourcePath);
}
$filesystem->remove($tempTarget);

return $target;
}
}
