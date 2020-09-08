<?php











namespace Composer\Package\Version;

use Composer\Config;
use Composer\Repository\Vcs\HgDriver;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser as SemverVersionParser;
use Composer\Util\Git as GitUtil;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;
use Composer\Util\Svn as SvnUtil;
use Composer\Util\Platform;
use Composer\Package\Version\VersionParser;








class VersionGuesser
{



private $config;




private $process;




private $versionParser;






public function __construct(Config $config, ProcessExecutor $process, SemverVersionParser $versionParser)
{
$this->config = $config;
$this->process = $process;
$this->versionParser = $versionParser;
}







public function guessVersion(array $packageConfig, $path)
{
if (!function_exists('proc_open')) {
return null;
}

$versionData = $this->guessGitVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

$versionData = $this->guessHgVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

$versionData = $this->guessFossilVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

$versionData = $this->guessSvnVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

return null;
}

private function postprocess(array $versionData)
{
if (!empty($versionData['feature_version']) && $versionData['feature_version'] === $versionData['version'] && $versionData['feature_pretty_version'] === $versionData['feature_pretty_version']) {
unset($versionData['feature_version'], $versionData['feature_pretty_version']);
}

if ('-dev' === substr($versionData['version'], -4) && preg_match('{\.9{7}}', $versionData['version'])) {
$versionData['pretty_version'] = preg_replace('{(\.9{7})+}', '.x', $versionData['version']);
}

if (!empty($versionData['feature_version']) && '-dev' === substr($versionData['feature_version'], -4) && preg_match('{\.9{7}}', $versionData['feature_version'])) {
$versionData['feature_pretty_version'] = preg_replace('{(\.9{7})+}', '.x', $versionData['feature_version']);
}

return $versionData;
}

private function guessGitVersion(array $packageConfig, $path)
{
GitUtil::cleanEnv();
$commit = null;
$version = null;
$prettyVersion = null;
$featureVersion = null;
$featurePrettyVersion = null;
$isDetached = false;


 if (0 === $this->process->execute('git branch --no-color --no-abbrev -v', $output, $path)) {
$branches = array();
$isFeatureBranch = false;


 foreach ($this->process->splitLines($output) as $branch) {
if ($branch && preg_match('{^(?:\* ) *(\(no branch\)|\(detached from \S+\)|\(HEAD detached at \S+\)|\S+) *([a-f0-9]+) .*$}', $branch, $match)) {
if ($match[1] === '(no branch)' || substr($match[1], 0, 10) === '(detached ' || substr($match[1], 0, 17) === '(HEAD detached at') {
$version = 'dev-' . $match[2];
$prettyVersion = $version;
$isFeatureBranch = true;
$isDetached = true;
} else {
$version = $this->versionParser->normalizeBranch($match[1]);
$prettyVersion = 'dev-' . $match[1];
$isFeatureBranch = $this->isFeatureBranch($packageConfig, $match[1]);
}

if ($match[2]) {
$commit = $match[2];
}
}

if ($branch && !preg_match('{^ *[^/]+/HEAD }', $branch)) {
if (preg_match('{^(?:\* )? *(\S+) *([a-f0-9]+) .*$}', $branch, $match)) {
$branches[] = $match[1];
}
}
}

if ($isFeatureBranch) {
$featureVersion = $version;
$featurePrettyVersion = $prettyVersion;


 $result = $this->guessFeatureVersion($packageConfig, $version, $branches, 'git rev-list %candidate%..%branch%', $path);
$version = $result['version'];
$prettyVersion = $result['pretty_version'];
}
}

if (!$version || $isDetached) {
$result = $this->versionFromGitTags($path);
if ($result) {
$version = $result['version'];
$prettyVersion = $result['pretty_version'];
$featureVersion = null;
$featurePrettyVersion = null;
}
}

if (!$commit) {
$command = 'git log --pretty="%H" -n1 HEAD'.GitUtil::getNoShowSignatureFlag($this->process);
if (0 === $this->process->execute($command, $output, $path)) {
$commit = trim($output) ?: null;
}
}

if ($featureVersion) {
return array('version' => $version, 'commit' => $commit, 'pretty_version' => $prettyVersion, 'feature_version' => $featureVersion, 'feature_pretty_version' => $featurePrettyVersion);
}

return array('version' => $version, 'commit' => $commit, 'pretty_version' => $prettyVersion);
}

private function versionFromGitTags($path)
{

 if (0 === $this->process->execute('git describe --exact-match --tags', $output, $path)) {
try {
$version = $this->versionParser->normalize(trim($output));

return array('version' => $version, 'pretty_version' => trim($output));
} catch (\Exception $e) {
}
}

return null;
}

private function guessHgVersion(array $packageConfig, $path)
{

 if (0 === $this->process->execute('hg branch', $output, $path)) {
$branch = trim($output);
$version = $this->versionParser->normalizeBranch($branch);
$isFeatureBranch = 0 === strpos($version, 'dev-');

if (VersionParser::DEFAULT_BRANCH_ALIAS === $version) {
return array('version' => $version, 'commit' => null, 'pretty_version' => 'dev-'.$branch);
}

if (!$isFeatureBranch) {
return array('version' => $version, 'commit' => null, 'pretty_version' => $version);
}


 $io = new NullIO();
$driver = new HgDriver(array('url' => $path), $io, $this->config, new HttpDownloader($io, $this->config), $this->process);
$branches = array_keys($driver->getBranches());


 $result = $this->guessFeatureVersion($packageConfig, $version, $branches, 'hg log -r "not ancestors(\'%candidate%\') and ancestors(\'%branch%\')" --template "{node}\\n"', $path);
$result['commit'] = '';
$result['feature_version'] = $version;
$result['feature_pretty_version'] = $version;

return $result;
}
}

private function guessFeatureVersion(array $packageConfig, $version, array $branches, $scmCmdline, $path)
{
$prettyVersion = $version;


 
 if (!isset($packageConfig['extra']['branch-alias'][$version])
|| strpos(json_encode($packageConfig), '"self.version"')
) {
$branch = preg_replace('{^dev-}', '', $version);
$length = PHP_INT_MAX;


 if (!$this->isFeatureBranch($packageConfig, $branch)) {
return array('version' => $version, 'pretty_version' => $prettyVersion);
}


 
 rsort($branches, defined('SORT_NATURAL') ? SORT_NATURAL : SORT_REGULAR);

foreach ($branches as $candidate) {

 if ($candidate === $branch || $this->isFeatureBranch($packageConfig, $candidate)) {
continue;
}

$cmdLine = str_replace(array('%candidate%', '%branch%'), array($candidate, $branch), $scmCmdline);
if (0 !== $this->process->execute($cmdLine, $output, $path)) {
continue;
}

if (strlen($output) < $length) {
$length = strlen($output);
$version = $this->versionParser->normalizeBranch($candidate);
$prettyVersion = 'dev-' . $candidate;
}
}
}

return array('version' => $version, 'pretty_version' => $prettyVersion);
}

private function isFeatureBranch(array $packageConfig, $branchName)
{
$nonFeatureBranches = '';
if (!empty($packageConfig['non-feature-branches'])) {
$nonFeatureBranches = implode('|', $packageConfig['non-feature-branches']);
}

return !preg_match('{^(' . $nonFeatureBranches . '|master|main|latest|next|current|support|tip|trunk|default|develop|\d+\..+)$}', $branchName, $match);
}

private function guessFossilVersion(array $packageConfig, $path)
{
$version = null;
$prettyVersion = null;


 if (0 === $this->process->execute('fossil branch list', $output, $path)) {
$branch = trim($output);
$version = $this->versionParser->normalizeBranch($branch);
$prettyVersion = 'dev-' . $branch;
}


 if (0 === $this->process->execute('fossil tag list', $output, $path)) {
try {
$version = $this->versionParser->normalize(trim($output));
$prettyVersion = trim($output);
} catch (\Exception $e) {
}
}

return array('version' => $version, 'commit' => '', 'pretty_version' => $prettyVersion);
}

private function guessSvnVersion(array $packageConfig, $path)
{
SvnUtil::cleanEnv();


 if (0 === $this->process->execute('svn info --xml', $output, $path)) {
$trunkPath = isset($packageConfig['trunk-path']) ? preg_quote($packageConfig['trunk-path'], '#') : 'trunk';
$branchesPath = isset($packageConfig['branches-path']) ? preg_quote($packageConfig['branches-path'], '#') : 'branches';
$tagsPath = isset($packageConfig['tags-path']) ? preg_quote($packageConfig['tags-path'], '#') : 'tags';

$urlPattern = '#<url>.*/(' . $trunkPath . '|(' . $branchesPath . '|' . $tagsPath . ')/(.*))</url>#';

if (preg_match($urlPattern, $output, $matches)) {
if (isset($matches[2]) && ($branchesPath === $matches[2] || $tagsPath === $matches[2])) {

 $version = $this->versionParser->normalizeBranch($matches[3]);
$prettyVersion = 'dev-' . $matches[3];

return array('version' => $version, 'commit' => '', 'pretty_version' => $prettyVersion);
}

$prettyVersion = trim($matches[1]);
if ($prettyVersion === 'trunk') {
$version = 'dev-trunk';
} else {
$version = $this->versionParser->normalize($prettyVersion);
}

return array('version' => $version, 'commit' => '', 'pretty_version' => $prettyVersion);
}
}
}
}
