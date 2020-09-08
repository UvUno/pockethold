<?php











namespace Composer\Repository;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\PluginInterface;
use Composer\Util\ProcessExecutor;
use Composer\Util\Silencer;
use Composer\Util\Platform;
use Composer\XdebugHandler\XdebugHandler;
use Symfony\Component\Process\ExecutableFinder;




class PlatformRepository extends ArrayRepository
{
const PLATFORM_PACKAGE_REGEX = '{^(?:php(?:-64bit|-ipv6|-zts|-debug)?|hhvm|(?:ext|lib)-[a-z0-9](?:[_.-]?[a-z0-9]+)*|composer-(?:plugin|runtime)-api)$}iD';

private static $hhvmVersion;
private $versionParser;








private $overrides = array();

private $process;

public function __construct(array $packages = array(), array $overrides = array(), ProcessExecutor $process = null)
{
$this->process = $process;
foreach ($overrides as $name => $version) {
$this->overrides[strtolower($name)] = array('name' => $name, 'version' => $version);
}
parent::__construct($packages);
}

public function getRepoName()
{
return 'platform repo';
}

protected function initialize()
{
parent::initialize();

$this->versionParser = new VersionParser();


 
 foreach ($this->overrides as $override) {

 if (!preg_match(self::PLATFORM_PACKAGE_REGEX, $override['name'])) {
throw new \InvalidArgumentException('Invalid platform package name in config.platform: '.$override['name']);
}

$this->addOverriddenPackage($override);
}

$prettyVersion = PluginInterface::PLUGIN_API_VERSION;
$version = $this->versionParser->normalize($prettyVersion);
$composerPluginApi = new CompletePackage('composer-plugin-api', $version, $prettyVersion);
$composerPluginApi->setDescription('The Composer Plugin API');
$this->addPackage($composerPluginApi);

$prettyVersion = Composer::RUNTIME_API_VERSION;
$version = $this->versionParser->normalize($prettyVersion);
$composerRuntimeApi = new CompletePackage('composer-runtime-api', $version, $prettyVersion);
$composerRuntimeApi->setDescription('The Composer Runtime API');
$this->addPackage($composerRuntimeApi);

try {
$prettyVersion = PHP_VERSION;
$version = $this->versionParser->normalize($prettyVersion);
} catch (\UnexpectedValueException $e) {
$prettyVersion = preg_replace('#^([^~+-]+).*$#', '$1', PHP_VERSION);
$version = $this->versionParser->normalize($prettyVersion);
}

$php = new CompletePackage('php', $version, $prettyVersion);
$php->setDescription('The PHP interpreter');
$this->addPackage($php);

if (PHP_DEBUG) {
$phpdebug = new CompletePackage('php-debug', $version, $prettyVersion);
$phpdebug->setDescription('The PHP interpreter, with debugging symbols');
$this->addPackage($phpdebug);
}

if (defined('PHP_ZTS') && PHP_ZTS) {
$phpzts = new CompletePackage('php-zts', $version, $prettyVersion);
$phpzts->setDescription('The PHP interpreter, with Zend Thread Safety');
$this->addPackage($phpzts);
}

if (PHP_INT_SIZE === 8) {
$php64 = new CompletePackage('php-64bit', $version, $prettyVersion);
$php64->setDescription('The PHP interpreter, 64bit');
$this->addPackage($php64);
}


 
 if (defined('AF_INET6') || Silencer::call('inet_pton', '::') !== false) {
$phpIpv6 = new CompletePackage('php-ipv6', $version, $prettyVersion);
$phpIpv6->setDescription('The PHP interpreter, with IPv6 support');
$this->addPackage($phpIpv6);
}

$loadedExtensions = get_loaded_extensions();


 foreach ($loadedExtensions as $name) {
if (in_array($name, array('standard', 'Core'))) {
continue;
}

$reflExt = new \ReflectionExtension($name);
$prettyVersion = $reflExt->getVersion();
$this->addExtension($name, $prettyVersion);
}


 if (!in_array('xdebug', $loadedExtensions, true) && ($prettyVersion = XdebugHandler::getSkippedVersion())) {
$this->addExtension('xdebug', $prettyVersion);
}


 
 
 foreach ($loadedExtensions as $name) {
$prettyVersion = null;
$description = 'The '.$name.' PHP library';
switch ($name) {
case 'curl':
$curlVersion = curl_version();
$prettyVersion = $curlVersion['version'];
break;

case 'iconv':
$prettyVersion = ICONV_VERSION;
break;

case 'intl':
$name = 'ICU';
if (defined('INTL_ICU_VERSION')) {
$prettyVersion = INTL_ICU_VERSION;
} else {
$reflector = new \ReflectionExtension('intl');

ob_start();
$reflector->info();
$output = ob_get_clean();

preg_match('/^ICU version => (.*)$/m', $output, $matches);
$prettyVersion = $matches[1];
}

break;

case 'imagick':
$imagick = new \Imagick();
$imageMagickVersion = $imagick->getVersion();

 
 preg_match('/^ImageMagick ([\d.]+)(?:-(\d+))?/', $imageMagickVersion['versionString'], $matches);
if (isset($matches[2])) {
$prettyVersion = "{$matches[1]}.{$matches[2]}";
} else {
$prettyVersion = $matches[1];
}
break;

case 'libxml':
$prettyVersion = LIBXML_DOTTED_VERSION;
break;

case 'openssl':
$prettyVersion = preg_replace_callback('{^(?:OpenSSL|LibreSSL)?\s*([0-9.]+)([a-z]*).*}i', function ($match) {
if (empty($match[2])) {
return $match[1];
}


 

if (!preg_match('{^z*[a-z]$}', $match[2])) {

 return 0;
}

$len = strlen($match[2]);
$patchVersion = ($len - 1) * 26; 
 $patchVersion += ord($match[2][$len - 1]) - 96;

return $match[1].'.'.$patchVersion;
}, OPENSSL_VERSION_TEXT);

$description = OPENSSL_VERSION_TEXT;
break;

case 'pcre':
$prettyVersion = preg_replace('{^(\S+).*}', '$1', PCRE_VERSION);
break;

case 'uuid':
$prettyVersion = phpversion('uuid');
break;

case 'xsl':
$prettyVersion = LIBXSLT_DOTTED_VERSION;
break;

case 'zip':
if (defined('ZipArchive::LIBZIP_VERSION')) {
$prettyVersion = \ZipArchive::LIBZIP_VERSION;
} else {
continue 2;
}

default:

 continue 2;
}

try {
$version = $this->versionParser->normalize($prettyVersion);
} catch (\UnexpectedValueException $e) {
continue;
}

$lib = new CompletePackage('lib-'.$name, $version, $prettyVersion);
$lib->setDescription($description);
$this->addPackage($lib);
}

if ($hhvmVersion = self::getHHVMVersion($this->process)) {
try {
$prettyVersion = $hhvmVersion;
$version = $this->versionParser->normalize($prettyVersion);
} catch (\UnexpectedValueException $e) {
$prettyVersion = preg_replace('#^([^~+-]+).*$#', '$1', $hhvmVersion);
$version = $this->versionParser->normalize($prettyVersion);
}

$hhvm = new CompletePackage('hhvm', $version, $prettyVersion);
$hhvm->setDescription('The HHVM Runtime (64bit)');
$this->addPackage($hhvm);
}
}




public function addPackage(PackageInterface $package)
{

 if (isset($this->overrides[$package->getName()])) {
$overrider = $this->findPackage($package->getName(), '*');
if ($package->getVersion() === $overrider->getVersion()) {
$actualText = 'same as actual';
} else {
$actualText = 'actual: '.$package->getPrettyVersion();
}
$overrider->setDescription($overrider->getDescription().', '.$actualText);

return;
}


 if (isset($this->overrides['php']) && 0 === strpos($package->getName(), 'php-')) {
$overrider = $this->addOverriddenPackage($this->overrides['php'], $package->getPrettyName());
if ($package->getVersion() === $overrider->getVersion()) {
$actualText = 'same as actual';
} else {
$actualText = 'actual: '.$package->getPrettyVersion();
}
$overrider->setDescription($overrider->getDescription().', '.$actualText);

return;
}

parent::addPackage($package);
}

private function addOverriddenPackage(array $override, $name = null)
{
$version = $this->versionParser->normalize($override['version']);
$package = new CompletePackage($name ?: $override['name'], $version, $override['version']);
$package->setDescription('Package overridden via config.platform');
$package->setExtra(array('config.platform' => true));
parent::addPackage($package);

return $package;
}







private function addExtension($name, $prettyVersion)
{
$extraDescription = null;

try {
$version = $this->versionParser->normalize($prettyVersion);
} catch (\UnexpectedValueException $e) {
$extraDescription = ' (actual version: '.$prettyVersion.')';
if (preg_match('{^(\d+\.\d+\.\d+(?:\.\d+)?)}', $prettyVersion, $match)) {
$prettyVersion = $match[1];
} else {
$prettyVersion = '0';
}
$version = $this->versionParser->normalize($prettyVersion);
}

$packageName = $this->buildPackageName($name);
$ext = new CompletePackage($packageName, $version, $prettyVersion);
$ext->setDescription('The '.$name.' PHP extension'.$extraDescription);
$this->addPackage($ext);
}





private function buildPackageName($name)
{
return 'ext-' . str_replace(' ', '-', $name);
}

private static function getHHVMVersion(ProcessExecutor $process = null)
{
if (null !== self::$hhvmVersion) {
return self::$hhvmVersion ?: null;
}

self::$hhvmVersion = defined('HHVM_VERSION') ? HHVM_VERSION : null;
if (self::$hhvmVersion === null && !Platform::isWindows()) {
self::$hhvmVersion = false;
$finder = new ExecutableFinder();
$hhvmPath = $finder->find('hhvm');
if ($hhvmPath !== null) {
$process = $process ?: new ProcessExecutor();
$exitCode = $process->execute(
ProcessExecutor::escape($hhvmPath).
' --php -d hhvm.jit=0 -r "echo HHVM_VERSION;" 2>/dev/null',
self::$hhvmVersion
);
if ($exitCode !== 0) {
self::$hhvmVersion = false;
}
}
}
}
}
