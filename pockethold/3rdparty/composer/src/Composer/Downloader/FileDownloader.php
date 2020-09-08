<?php











namespace Composer\Downloader;

use Composer\Config;
use Composer\Cache;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Comparer\Comparer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;
use Composer\Util\Url as UrlUtil;
use Composer\Util\ProcessExecutor;
use Composer\Downloader\TransportException;
use React\Promise\PromiseInterface;









class FileDownloader implements DownloaderInterface, ChangeReportInterface
{

protected $io;

protected $config;

protected $httpDownloader;

protected $filesystem;

protected $cache;

protected $eventDispatcher;

protected $process;



public $lastCacheWrites = array();
private $additionalCleanupPaths = array();











public function __construct(IOInterface $io, Config $config, HttpDownloader $httpDownloader, EventDispatcher $eventDispatcher = null, Cache $cache = null, Filesystem $filesystem = null, ProcessExecutor $process = null)
{
$this->io = $io;
$this->config = $config;
$this->eventDispatcher = $eventDispatcher;
$this->httpDownloader = $httpDownloader;
$this->cache = $cache;
$this->process = $process ?: new ProcessExecutor($io);
$this->filesystem = $filesystem ?: new Filesystem($this->process);

if ($this->cache && $this->cache->gcIsNecessary()) {
$this->cache->gc($config->get('cache-files-ttl'), $config->get('cache-files-maxsize'));
}
}




public function getInstallationSource()
{
return 'dist';
}




public function download(PackageInterface $package, $path, PackageInterface $prevPackage = null, $output = true)
{
if (!$package->getDistUrl()) {
throw new \InvalidArgumentException('The given package is missing url information');
}

$retries = 3;
$urls = $package->getDistUrls();
foreach ($urls as $index => $url) {
$processedUrl = $this->processUrl($package, $url);
$urls[$index] = array(
'base' => $url,
'processed' => $processedUrl,
'cacheKey' => $this->getCacheKey($package, $processedUrl)
);
}

$fileName = $this->getFileName($package, $path);
$this->filesystem->ensureDirectoryExists($path);
$this->filesystem->ensureDirectoryExists(dirname($fileName));

$io = $this->io;
$cache = $this->cache;
$httpDownloader = $this->httpDownloader;
$eventDispatcher = $this->eventDispatcher;
$filesystem = $this->filesystem;
$self = $this;

$accept = null;
$reject = null;
$download = function () use ($io, $output, $httpDownloader, $cache, $eventDispatcher, $package, $fileName, &$urls, &$accept, &$reject) {
$url = reset($urls);

if ($eventDispatcher) {
$preFileDownloadEvent = new PreFileDownloadEvent(PluginEvents::PRE_FILE_DOWNLOAD, $httpDownloader, $url['processed'], 'package', $package);
$eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);
$url['processed'] = $preFileDownloadEvent->getProcessedUrl();
}

$checksum = $package->getDistSha1Checksum();
$cacheKey = $url['cacheKey'];


 if ($cache && (!$checksum || $checksum === $cache->sha1($cacheKey)) && $cache->copyTo($cacheKey, $fileName)) {
if ($output) {
$io->writeError("  - Loading <info>" . $package->getName() . "</info> (<comment>" . $package->getFullPrettyVersion() . "</comment>) from cache", true, IOInterface::VERY_VERBOSE);
}
$result = \React\Promise\resolve($fileName);
} else {
if ($output) {
$io->writeError("  - Downloading <info>" . $package->getName() . "</info> (<comment>" . $package->getFullPrettyVersion() . "</comment>)");
}

$result = $httpDownloader->addCopy($url['processed'], $fileName, $package->getTransportOptions())
->then($accept, $reject);
}

return $result->then(function ($result) use ($fileName, $checksum, $url, $package, $eventDispatcher) {

 
 
 if (null === $result) {
return $fileName;
}

if (!file_exists($fileName)) {
throw new \UnexpectedValueException($url['base'].' could not be saved to '.$fileName.', make sure the'
.' directory is writable and you have internet connectivity');
}

if ($checksum && hash_file('sha1', $fileName) !== $checksum) {
throw new \UnexpectedValueException('The checksum verification of the file failed (downloaded from '.$url['base'].')');
}

if ($eventDispatcher) {
$postFileDownloadEvent = new PostFileDownloadEvent(PluginEvents::POST_FILE_DOWNLOAD, $fileName, $checksum, $url['processed'], $package);
$eventDispatcher->dispatch($postFileDownloadEvent->getName(), $postFileDownloadEvent);
}

return $fileName;
});
};

$accept = function ($response) use ($cache, $package, $fileName, $self, &$urls) {
$url = reset($urls);
$cacheKey = $url['cacheKey'];

if ($cache) {
$self->lastCacheWrites[$package->getName()] = $cacheKey;
$cache->copyFrom($cacheKey, $fileName);
}

$response->collect();

return $fileName;
};

$reject = function ($e) use ($io, &$urls, $download, $fileName, $package, &$retries, $filesystem, $self) {

 if (file_exists($fileName)) {
$filesystem->unlink($fileName);
}
$self->clearLastCacheWrite($package);

if ($e instanceof TransportException) {

 if ((0 !== $e->getCode() && !in_array($e->getCode(), array(500, 502, 503, 504))) || !$retries) {
$retries = 0;
}
}


 if ($e instanceof TransportException && $e->getStatusCode() === 499) {
$retries = 0;
$urls = array();
}

if ($retries) {
usleep(500000);
$retries--;

return $download();
}

array_shift($urls);
if ($urls) {
if ($io->isDebug()) {
$io->writeError('    Failed downloading '.$package->getName().': ['.get_class($e).'] '.$e->getCode().': '.$e->getMessage());
$io->writeError('    Trying the next URL for '.$package->getName());
} elseif (count($urls)) {
$io->writeError('    Failed downloading '.$package->getName().', trying the next URL ('.$e->getCode().': '.$e->getMessage().')');
}

$retries = 3;
usleep(100000);

return $download();
}

throw $e;
};

return $download();
}




public function prepare($type, PackageInterface $package, $path, PackageInterface $prevPackage = null)
{
}




public function cleanup($type, PackageInterface $package, $path, PackageInterface $prevPackage = null)
{
$fileName = $this->getFileName($package, $path);
if (file_exists($fileName)) {
$this->filesystem->unlink($fileName);
}

$dirsToCleanUp = array(
$this->config->get('vendor-dir').'/composer/',
$this->config->get('vendor-dir'),
$path,
);

if (isset($this->additionalCleanupPaths[$package->getName()])) {
foreach ($this->additionalCleanupPaths[$package->getName()] as $path) {
$this->filesystem->remove($path);
}
}

foreach ($dirsToCleanUp as $dir) {
if (is_dir($dir) && $this->filesystem->isDirEmpty($dir)) {
$this->filesystem->removeDirectory($dir);
}
}
}




public function install(PackageInterface $package, $path, $output = true)
{
if ($output) {
$this->io->writeError("  - " . InstallOperation::format($package));
}

$this->filesystem->emptyDirectory($path);
$this->filesystem->ensureDirectoryExists($path);
$this->filesystem->rename($this->getFileName($package, $path), $path . pathinfo(parse_url($package->getDistUrl(), PHP_URL_PATH), PATHINFO_BASENAME));
}





public function clearLastCacheWrite(PackageInterface $package)
{
if ($this->cache && isset($this->lastCacheWrites[$package->getName()])) {
$this->cache->remove($this->lastCacheWrites[$package->getName()]);
unset($this->lastCacheWrites[$package->getName()]);
}
}





public function addCleanupPath(PackageInterface $package, $path)
{
$this->additionalCleanupPaths[$package->getName()][] = $path;
}





public function removeCleanupPath(PackageInterface $package, $path)
{
if (isset($this->additionalCleanupPaths[$package->getName()])) {
$idx = array_search($path, $this->additionalCleanupPaths[$package->getName()]);
if (false !== $idx) {
unset($this->additionalCleanupPaths[$package->getName()][$idx]);
}
}
}




public function update(PackageInterface $initial, PackageInterface $target, $path)
{
$this->io->writeError("  - " . UpdateOperation::format($initial, $target) . ": ", false);

$promise = $this->remove($initial, $path, false);
if (!$promise instanceof PromiseInterface) {
$promise = \React\Promise\resolve();
}
$self = $this;
$io = $this->io;

return $promise->then(function () use ($self, $target, $path, $io) {
$promise = $self->install($target, $path, false);
$io->writeError('');

return $promise;
});
}




public function remove(PackageInterface $package, $path, $output = true)
{
if ($output) {
$this->io->writeError("  - " . UninstallOperation::format($package));
}
if (!$this->filesystem->removeDirectory($path)) {
throw new \RuntimeException('Could not completely delete '.$path.', aborting.');
}
}








protected function getFileName(PackageInterface $package, $path)
{
return rtrim($this->config->get('vendor-dir').'/composer/tmp-'.md5($package.spl_object_hash($package)).'.'.pathinfo(parse_url($package->getDistUrl(), PHP_URL_PATH), PATHINFO_EXTENSION), '.');
}









protected function processUrl(PackageInterface $package, $url)
{
if (!extension_loaded('openssl') && 0 === strpos($url, 'https:')) {
throw new \RuntimeException('You must enable the openssl extension to download files via https');
}

if ($package->getDistReference()) {
$url = UrlUtil::updateDistReference($this->config, $url, $package->getDistReference());
}

return $url;
}

private function getCacheKey(PackageInterface $package, $processedUrl)
{

 
 
 
 $cacheKey = sha1($processedUrl);

return $package->getName().'/'.$cacheKey.'.'.$package->getDistType();
}





public function getLocalChanges(PackageInterface $package, $targetDir)
{
$prevIO = $this->io;

$this->io = new NullIO;
$this->io->loadConfiguration($this->config);
$e = null;
$output = '';

try {
if (is_dir($targetDir.'_compare')) {
$this->filesystem->removeDirectory($targetDir.'_compare');
}

$this->download($package, $targetDir.'_compare', null, false);
$this->httpDownloader->wait();
$this->install($package, $targetDir.'_compare', false);
$this->process->wait();

$comparer = new Comparer();
$comparer->setSource($targetDir.'_compare');
$comparer->setUpdate($targetDir);
$comparer->doCompare();
$output = $comparer->getChanged(true, true);
$this->filesystem->removeDirectory($targetDir.'_compare');
} catch (\Exception $e) {
}

$this->io = $prevIO;

if ($e) {
throw $e;
}

return trim($output);
}
}
