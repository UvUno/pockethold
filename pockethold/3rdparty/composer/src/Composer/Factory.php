<?php











namespace Composer;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use Composer\Package\Archiver;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use Composer\Util\Silencer;
use Composer\Plugin\PluginEvents;
use Composer\EventDispatcher\Event;
use Seld\JsonLint\DuplicateKeyException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Autoload\AutoloadGenerator;
use Composer\Package\Version\VersionParser;
use Composer\Downloader\TransportException;
use Seld\JsonLint\JsonParser;









class Factory
{




protected static function getHomeDir()
{
$home = getenv('COMPOSER_HOME');
if ($home) {
return $home;
}

if (Platform::isWindows()) {
if (!getenv('APPDATA')) {
throw new \RuntimeException('The APPDATA or COMPOSER_HOME environment variable must be set for composer to run correctly');
}

return rtrim(strtr(getenv('APPDATA'), '\\', '/'), '/') . '/Composer';
}

$userDir = self::getUserDir();
$dirs = array();

if (self::useXdg()) {

 $xdgConfig = getenv('XDG_CONFIG_HOME');
if (!$xdgConfig) {
$xdgConfig = $userDir . '/.config';
}

$dirs[] = $xdgConfig . '/composer';
}

$dirs[] = $userDir . '/.composer';


 foreach ($dirs as $dir) {
if (is_dir($dir)) {
return $dir;
}
}


 return $dirs[0];
}





protected static function getCacheDir($home)
{
$cacheDir = getenv('COMPOSER_CACHE_DIR');
if ($cacheDir) {
return $cacheDir;
}

$homeEnv = getenv('COMPOSER_HOME');
if ($homeEnv) {
return $homeEnv . '/cache';
}

if (Platform::isWindows()) {
if ($cacheDir = getenv('LOCALAPPDATA')) {
$cacheDir .= '/Composer';
} else {
$cacheDir = $home . '/cache';
}

return rtrim(strtr($cacheDir, '\\', '/'), '/');
}

$userDir = self::getUserDir();
if ($home === $userDir . '/.composer' && is_dir($home . '/cache')) {
return $home . '/cache';
}

if (self::useXdg()) {
$xdgCache = getenv('XDG_CACHE_HOME') ?: $userDir . '/.cache';

return $xdgCache . '/composer';
}

return $home . '/cache';
}





protected static function getDataDir($home)
{
$homeEnv = getenv('COMPOSER_HOME');
if ($homeEnv) {
return $homeEnv;
}

if (Platform::isWindows()) {
return strtr($home, '\\', '/');
}

$userDir = self::getUserDir();
if ($home !== $userDir . '/.composer' && self::useXdg()) {
$xdgData = getenv('XDG_DATA_HOME') ?: $userDir . '/.local/share';

return $xdgData . '/composer';
}

return $home;
}





public static function createConfig(IOInterface $io = null, $cwd = null)
{
$cwd = $cwd ?: getcwd();

$config = new Config(true, $cwd);


 $home = self::getHomeDir();
$config->merge(array('config' => array(
'home' => $home,
'cache-dir' => self::getCacheDir($home),
'data-dir' => self::getDataDir($home),
)));


 $file = new JsonFile($config->get('home').'/config.json');
if ($file->exists()) {
if ($io && $io->isDebug()) {
$io->writeError('Loading config file ' . $file->getPath());
}
$config->merge($file->read());
}
$config->setConfigSource(new JsonConfigSource($file));

$htaccessProtect = (bool) $config->get('htaccess-protect');
if ($htaccessProtect) {

 
 
 $dirs = array($config->get('home'), $config->get('cache-dir'), $config->get('data-dir'));
foreach ($dirs as $dir) {
if (!file_exists($dir . '/.htaccess')) {
if (!is_dir($dir)) {
Silencer::call('mkdir', $dir, 0777, true);
}
Silencer::call('file_put_contents', $dir . '/.htaccess', 'Deny from all');
}
}
}


 $file = new JsonFile($config->get('home').'/auth.json');
if ($file->exists()) {
if ($io && $io->isDebug()) {
$io->writeError('Loading config file ' . $file->getPath());
}
$config->merge(array('config' => $file->read()));
}
$config->setAuthConfigSource(new JsonConfigSource($file, true));


 if ($composerAuthEnv = getenv('COMPOSER_AUTH')) {
$authData = json_decode($composerAuthEnv, true);

if (null === $authData) {
throw new \UnexpectedValueException('COMPOSER_AUTH environment variable is malformed, should be a valid JSON object');
}

if ($io && $io->isDebug()) {
$io->writeError('Loading auth config from COMPOSER_AUTH');
}
$config->merge(array('config' => $authData));
}

return $config;
}

public static function getComposerFile()
{
return trim(getenv('COMPOSER')) ?: './composer.json';
}

public static function getLockFile($composerFile)
{
return "json" === pathinfo($composerFile, PATHINFO_EXTENSION)
? substr($composerFile, 0, -4).'lock'
: $composerFile . '.lock';
}

public static function createAdditionalStyles()
{
return array(
'highlight' => new OutputFormatterStyle('red'),
'warning' => new OutputFormatterStyle('black', 'yellow'),
);
}






public static function createOutput()
{
$styles = self::createAdditionalStyles();
$formatter = new OutputFormatter(false, $styles);

return new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
}




public static function createDefaultRepositories(IOInterface $io = null, Config $config = null, RepositoryManager $rm = null)
{
return RepositoryFactory::defaultRepos($io, $config, $rm);
}













public function createComposer(IOInterface $io, $localConfig = null, $disablePlugins = false, $cwd = null, $fullLoad = true)
{
$cwd = $cwd ?: getcwd();


 if (null === $localConfig) {
$localConfig = static::getComposerFile();
}

if (is_string($localConfig)) {
$composerFile = $localConfig;

$file = new JsonFile($localConfig, null, $io);

if (!$file->exists()) {
if ($localConfig === './composer.json' || $localConfig === 'composer.json') {
$message = 'Composer could not find a composer.json file in '.$cwd;
} else {
$message = 'Composer could not find the config file: '.$localConfig;
}
$instructions = 'To initialize a project, please create a composer.json file as described in the https://getcomposer.org/ "Getting Started" section';
throw new \InvalidArgumentException($message.PHP_EOL.$instructions);
}

$file->validateSchema(JsonFile::LAX_SCHEMA);
$jsonParser = new JsonParser;
try {
$jsonParser->parse(file_get_contents($localConfig), JsonParser::DETECT_KEY_CONFLICTS);
} catch (DuplicateKeyException $e) {
$details = $e->getDetails();
$io->writeError('<warning>Key '.$details['key'].' is a duplicate in '.$localConfig.' at line '.$details['line'].'</warning>');
}

$localConfig = $file->read();
}


 $config = static::createConfig($io, $cwd);
$config->merge($localConfig);
if (isset($composerFile)) {
$io->writeError('Loading config file ' . $composerFile, true, IOInterface::DEBUG);
$config->setConfigSource(new JsonConfigSource(new JsonFile(realpath($composerFile), null, $io)));

$localAuthFile = new JsonFile(dirname(realpath($composerFile)) . '/auth.json', null, $io);
if ($localAuthFile->exists()) {
$io->writeError('Loading config file ' . $localAuthFile->getPath(), true, IOInterface::DEBUG);
$config->merge(array('config' => $localAuthFile->read()));
$config->setAuthConfigSource(new JsonConfigSource($localAuthFile, true));
}
}

$vendorDir = $config->get('vendor-dir');


 $composer = new Composer();
$composer->setConfig($config);

if ($fullLoad) {

 $io->loadConfiguration($config);
}

$httpDownloader = self::createHttpDownloader($io, $config);
$process = new ProcessExecutor($io);
$loop = new Loop($httpDownloader, $process);
$composer->setLoop($loop);


 $dispatcher = new EventDispatcher($composer, $io, $process);
$composer->setEventDispatcher($dispatcher);


 $rm = RepositoryFactory::manager($io, $config, $httpDownloader, $dispatcher, $process);
$composer->setRepositoryManager($rm);


 
 if (!$fullLoad && !isset($localConfig['version'])) {
$localConfig['version'] = '1.0.0';
}


 $parser = new VersionParser;
$guesser = new VersionGuesser($config, $process, $parser);
$loader = $this->loadRootPackage($rm, $config, $parser, $guesser, $io);
$package = $loader->load($localConfig, 'Composer\Package\RootPackage', $cwd);
$composer->setPackage($package);


 $this->addLocalRepository($io, $rm, $vendorDir, $package);


 $im = $this->createInstallationManager($loop, $io, $dispatcher);
$composer->setInstallationManager($im);

if ($fullLoad) {

 $dm = $this->createDownloadManager($io, $config, $httpDownloader, $process, $dispatcher);
$composer->setDownloadManager($dm);


 $generator = new AutoloadGenerator($dispatcher, $io);
$composer->setAutoloadGenerator($generator);


 $am = $this->createArchiveManager($config, $dm, $loop);
$composer->setArchiveManager($am);
}


 $this->createDefaultInstallers($im, $composer, $io, $process);

if ($fullLoad) {
$globalComposer = null;
if (realpath($config->get('home')) !== $cwd) {
$globalComposer = $this->createGlobalComposer($io, $config, $disablePlugins);
}

$pm = $this->createPluginManager($io, $composer, $globalComposer, $disablePlugins);
$composer->setPluginManager($pm);

$pm->loadInstalledPlugins();
}


 if ($fullLoad && isset($composerFile)) {
$lockFile = self::getLockFile($composerFile);

$locker = new Package\Locker($io, new JsonFile($lockFile, null, $io), $im, file_get_contents($composerFile), $process);
$composer->setLocker($locker);
}

if ($fullLoad) {
$initEvent = new Event(PluginEvents::INIT);
$composer->getEventDispatcher()->dispatch($initEvent->getName(), $initEvent);


 
 if ($rm->getLocalRepository()) {
$this->purgePackages($rm->getLocalRepository(), $im);
}
}

return $composer;
}






public static function createGlobal(IOInterface $io, $disablePlugins = false)
{
$factory = new static();

return $factory->createGlobalComposer($io, static::createConfig($io), $disablePlugins, true);
}





protected function addLocalRepository(IOInterface $io, RepositoryManager $rm, $vendorDir, RootPackageInterface $rootPackage)
{
$rm->setLocalRepository(new Repository\InstalledFilesystemRepository(new JsonFile($vendorDir.'/composer/installed.json', null, $io), true, $rootPackage));
}





protected function createGlobalComposer(IOInterface $io, Config $config, $disablePlugins, $fullLoad = false)
{
$composer = null;
try {
$composer = $this->createComposer($io, $config->get('home') . '/composer.json', $disablePlugins, $config->get('home'), $fullLoad);
} catch (\Exception $e) {
$io->writeError('Failed to initialize global composer: '.$e->getMessage(), true, IOInterface::DEBUG);
}

return $composer;
}







public function createDownloadManager(IOInterface $io, Config $config, HttpDownloader $httpDownloader, ProcessExecutor $process, EventDispatcher $eventDispatcher = null)
{
$cache = null;
if ($config->get('cache-files-ttl') > 0) {
$cache = new Cache($io, $config->get('cache-files-dir'), 'a-z0-9_./');
}

$fs = new Filesystem($process);

$dm = new Downloader\DownloadManager($io, false, $fs);
switch ($preferred = $config->get('preferred-install')) {
case 'dist':
$dm->setPreferDist(true);
break;
case 'source':
$dm->setPreferSource(true);
break;
case 'auto':
default:

 break;
}

if (is_array($preferred)) {
$dm->setPreferences($preferred);
}

$dm->setDownloader('git', new Downloader\GitDownloader($io, $config, $process, $fs));
$dm->setDownloader('svn', new Downloader\SvnDownloader($io, $config, $process, $fs));
$dm->setDownloader('fossil', new Downloader\FossilDownloader($io, $config, $process, $fs));
$dm->setDownloader('hg', new Downloader\HgDownloader($io, $config, $process, $fs));
$dm->setDownloader('perforce', new Downloader\PerforceDownloader($io, $config, $process, $fs));
$dm->setDownloader('zip', new Downloader\ZipDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('rar', new Downloader\RarDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('tar', new Downloader\TarDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('gzip', new Downloader\GzipDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('xz', new Downloader\XzDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('phar', new Downloader\PharDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('file', new Downloader\FileDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('path', new Downloader\PathDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));

return $dm;
}






public function createArchiveManager(Config $config, Downloader\DownloadManager $dm, Loop $loop)
{
$am = new Archiver\ArchiveManager($dm, $loop);
$am->addArchiver(new Archiver\ZipArchiver);
$am->addArchiver(new Archiver\PharArchiver);

return $am;
}








protected function createPluginManager(IOInterface $io, Composer $composer, Composer $globalComposer = null, $disablePlugins = false)
{
return new Plugin\PluginManager($io, $composer, $globalComposer, $disablePlugins);
}




public function createInstallationManager(Loop $loop, IOInterface $io, EventDispatcher $eventDispatcher = null)
{
return new Installer\InstallationManager($loop, $io, $eventDispatcher);
}






protected function createDefaultInstallers(Installer\InstallationManager $im, Composer $composer, IOInterface $io, ProcessExecutor $process = null)
{
$fs = new Filesystem($process);
$binaryInstaller = new Installer\BinaryInstaller($io, rtrim($composer->getConfig()->get('bin-dir'), '/'), $composer->getConfig()->get('bin-compat'), $fs);

$im->addInstaller(new Installer\LibraryInstaller($io, $composer, null, $fs, $binaryInstaller));
$im->addInstaller(new Installer\PluginInstaller($io, $composer, $fs, $binaryInstaller));
$im->addInstaller(new Installer\MetapackageInstaller($io));
}





protected function purgePackages(WritableRepositoryInterface $repo, Installer\InstallationManager $im)
{
foreach ($repo->getPackages() as $package) {
if (!$im->isPackageInstalled($repo, $package)) {
$repo->removePackage($package);
}
}
}

protected function loadRootPackage(RepositoryManager $rm, Config $config, VersionParser $parser, VersionGuesser $guesser, IOInterface $io)
{
return new Package\Loader\RootPackageLoader($rm, $config, $parser, $guesser, $io);
}








public static function create(IOInterface $io, $config = null, $disablePlugins = false)
{
$factory = new static();

return $factory->createComposer($io, $config, $disablePlugins);
}







public static function createHttpDownloader(IOInterface $io, Config $config, $options = array())
{
static $warned = false;
$disableTls = false;
if ($config && $config->get('disable-tls') === true) {
if (!$warned) {
$io->writeError('<warning>You are running Composer with SSL/TLS protection disabled.</warning>');
}
$warned = true;
$disableTls = true;
} elseif (!extension_loaded('openssl')) {
throw new Exception\NoSslException('The openssl extension is required for SSL/TLS protection but is not available. '
. 'If you can not enable the openssl extension, you can disable this error, at your own risk, by setting the \'disable-tls\' option to true.');
}
$httpDownloaderOptions = array();
if ($disableTls === false) {
if ($config && $config->get('cafile')) {
$httpDownloaderOptions['ssl']['cafile'] = $config->get('cafile');
}
if ($config && $config->get('capath')) {
$httpDownloaderOptions['ssl']['capath'] = $config->get('capath');
}
$httpDownloaderOptions = array_replace_recursive($httpDownloaderOptions, $options);
}
try {
$httpDownloader = new HttpDownloader($io, $config, $httpDownloaderOptions, $disableTls);
} catch (TransportException $e) {
if (false !== strpos($e->getMessage(), 'cafile')) {
$io->write('<error>Unable to locate a valid CA certificate file. You must set a valid \'cafile\' option.</error>');
$io->write('<error>A valid CA certificate file is required for SSL/TLS protection.</error>');
if (PHP_VERSION_ID < 50600) {
$io->write('<error>It is recommended you upgrade to PHP 5.6+ which can detect your system CA file automatically.</error>');
}
$io->write('<error>You can disable this error, at your own risk, by setting the \'disable-tls\' option to true.</error>');
}
throw $e;
}

return $httpDownloader;
}




private static function useXdg()
{
foreach (array_keys($_SERVER) as $key) {
if (substr($key, 0, 4) === 'XDG_') {
return true;
}
}

if (is_dir('/etc/xdg')) {
return true;
}

return false;
}





private static function getUserDir()
{
$home = getenv('HOME');
if (!$home) {
throw new \RuntimeException('The HOME or COMPOSER_HOME environment variable must be set for composer to run correctly');
}

return rtrim(strtr($home, '\\', '/'), '/');
}
}
