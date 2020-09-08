<?php











namespace Composer\Repository;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\Version\VersionParser;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Util\Filesystem;
use Composer\Util\Url;
use Composer\Util\Git as GitUtil;




































class PathRepository extends ArrayRepository implements ConfigurableRepositoryInterface
{



private $loader;




private $versionGuesser;




private $url;




private $repoConfig;




private $process;




private $options;








public function __construct(array $repoConfig, IOInterface $io, Config $config)
{
if (!isset($repoConfig['url'])) {
throw new \RuntimeException('You must specify the `url` configuration for the path repository');
}

$this->loader = new ArrayLoader(null, true);
$this->url = Platform::expandPath($repoConfig['url']);
$this->process = new ProcessExecutor($io);
$this->versionGuesser = new VersionGuesser($config, $this->process, new VersionParser());
$this->repoConfig = $repoConfig;
$this->options = isset($repoConfig['options']) ? $repoConfig['options'] : array();
if (!isset($this->options['relative'])) {
$filesystem = new Filesystem();
$this->options['relative'] = !$filesystem->isAbsolutePath($this->url);
}

parent::__construct();
}

public function getRepoName()
{
return 'path repo ('.Url::sanitize($this->repoConfig['url']).')';
}

public function getRepoConfig()
{
return $this->repoConfig;
}






protected function initialize()
{
parent::initialize();

$urlMatches = $this->getUrlMatches();

if (empty($urlMatches)) {
if (preg_match('{[*{}]}', $this->url)) {
$url = $this->url;
while (preg_match('{[*{}]}', $url)) {
$url = dirname($url);
}

 if (is_dir($url)) {
return;
}
}

throw new \RuntimeException('The `url` supplied for the path (' . $this->url . ') repository does not exist');
}

foreach ($urlMatches as $url) {
$path = realpath($url) . DIRECTORY_SEPARATOR;
$composerFilePath = $path.'composer.json';

if (!file_exists($composerFilePath)) {
continue;
}

$json = file_get_contents($composerFilePath);
$package = JsonFile::parseJson($json, $composerFilePath);
$package['dist'] = array(
'type' => 'path',
'url' => $url,
'reference' => sha1($json . serialize($this->options)),
);
$package['transport-options'] = $this->options;


 if (!isset($package['version']) && ($rootVersion = getenv('COMPOSER_ROOT_VERSION'))) {
if (
0 === $this->process->execute('git rev-parse HEAD', $ref1, $path)
&& 0 === $this->process->execute('git rev-parse HEAD', $ref2)
&& $ref1 === $ref2
) {
$package['version'] = $rootVersion;
}
}

$output = '';
if (is_dir($path . DIRECTORY_SEPARATOR . '.git') && 0 === $this->process->execute('git log -n1 --pretty=%H'.GitUtil::getNoShowSignatureFlag($this->process), $output, $path)) {
$package['dist']['reference'] = trim($output);
}

if (!isset($package['version'])) {
$versionData = $this->versionGuesser->guessVersion($package, $path);
if (is_array($versionData) && $versionData['pretty_version']) {

 if (!empty($versionData['feature_pretty_version'])) {
$package['version'] = $versionData['feature_pretty_version'];
$this->addPackage($this->loader->load($package));
}

$package['version'] = $versionData['pretty_version'];
} else {
$package['version'] = 'dev-master';
}
}

$package = $this->loader->load($package);
$this->addPackage($package);
}
}






private function getUrlMatches()
{
$flags = GLOB_MARK | GLOB_ONLYDIR;

if (defined('GLOB_BRACE')) {
$flags |= GLOB_BRACE;
} elseif (strpos($this->url, '{') !== false || strpos($this->url, '}') !== false) {
throw new \RuntimeException('The operating system does not support GLOB_BRACE which is required for the url '. $this->url);
}


 return array_map(function ($val) {
return rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $val), '/');
}, glob($this->url, $flags));
}
}
