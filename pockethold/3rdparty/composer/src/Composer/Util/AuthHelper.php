<?php











namespace Composer\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Downloader\TransportException;




class AuthHelper
{
protected $io;
protected $config;
private $displayedOriginAuthentications = array();

public function __construct(IOInterface $io, Config $config)
{
$this->io = $io;
$this->config = $config;
}





public function storeAuth($origin, $storeAuth)
{
$store = false;
$configSource = $this->config->getAuthConfigSource();
if ($storeAuth === true) {
$store = $configSource;
} elseif ($storeAuth === 'prompt') {
$answer = $this->io->askAndValidate(
'Do you want to store credentials for '.$origin.' in '.$configSource->getName().' ? [Yn] ',
function ($value) {
$input = strtolower(substr(trim($value), 0, 1));
if (in_array($input, array('y','n'))) {
return $input;
}
throw new \RuntimeException('Please answer (y)es or (n)o');
},
null,
'y'
);

if ($answer === 'y') {
$store = $configSource;
}
}
if ($store) {
$store->addConfigSetting(
'http-basic.'.$origin,
$this->io->getAuthentication($origin)
);
}
}










public function promptAuthIfNeeded($url, $origin, $statusCode, $reason = null, $headers = array())
{
$storeAuth = false;
$retry = false;

if (in_array($origin, $this->config->get('github-domains'), true)) {
$gitHubUtil = new GitHub($this->io, $this->config, null);
$message = "\n";

$rateLimited = $gitHubUtil->isRateLimited($headers);
if ($rateLimited) {
$rateLimit = $gitHubUtil->getRateLimit($headers);
if ($this->io->hasAuthentication($origin)) {
$message = 'Review your configured GitHub OAuth token or enter a new one to go over the API rate limit.';
} else {
$message = 'Create a GitHub OAuth token to go over the API rate limit.';
}

$message = sprintf(
'GitHub API limit (%d calls/hr) is exhausted, could not fetch '.$url.'. '.$message.' You can also wait until %s for the rate limit to reset.',
$rateLimit['limit'],
$rateLimit['reset']
)."\n";
} else {
$message .= 'Could not fetch '.$url.', please ';
if ($this->io->hasAuthentication($origin)) {
$message .= 'review your configured GitHub OAuth token or enter a new one to access private repos';
} else {
$message .= 'create a GitHub OAuth token to access private repos';
}
}

if (!$gitHubUtil->authorizeOAuth($origin)
&& (!$this->io->isInteractive() || !$gitHubUtil->authorizeOAuthInteractively($origin, $message))
) {
throw new TransportException('Could not authenticate against '.$origin, 401);
}
} elseif (in_array($origin, $this->config->get('gitlab-domains'), true)) {
$message = "\n".'Could not fetch '.$url.', enter your ' . $origin . ' credentials ' .($statusCode === 401 ? 'to access private repos' : 'to go over the API rate limit');
$gitLabUtil = new GitLab($this->io, $this->config, null);

if ($this->io->hasAuthentication($origin) && ($auth = $this->io->getAuthentication($origin)) && in_array($auth['password'], array('gitlab-ci-token', 'private-token', 'oauth2'), true)) {
throw new TransportException("Invalid credentials for '" . $url . "', aborting.", $statusCode);
}

if (!$gitLabUtil->authorizeOAuth($origin)
&& (!$this->io->isInteractive() || !$gitLabUtil->authorizeOAuthInteractively(parse_url($url, PHP_URL_SCHEME), $origin, $message))
) {
throw new TransportException('Could not authenticate against '.$origin, 401);
}
} elseif ($origin === 'bitbucket.org') {
$askForOAuthToken = true;
if ($this->io->hasAuthentication($origin)) {
$auth = $this->io->getAuthentication($origin);
if ($auth['username'] !== 'x-token-auth') {
$bitbucketUtil = new Bitbucket($this->io, $this->config);
$accessToken = $bitbucketUtil->requestToken($origin, $auth['username'], $auth['password']);
if (!empty($accessToken)) {
$this->io->setAuthentication($origin, 'x-token-auth', $accessToken);
$askForOAuthToken = false;
}
} else {
throw new TransportException('Could not authenticate against ' . $origin, 401);
}
}

if ($askForOAuthToken) {
$message = "\n".'Could not fetch ' . $url . ', please create a bitbucket OAuth token to ' . (($statusCode === 401 || $statusCode === 403) ? 'access private repos' : 'go over the API rate limit');
$bitBucketUtil = new Bitbucket($this->io, $this->config);
if (! $bitBucketUtil->authorizeOAuth($origin)
&& (! $this->io->isInteractive() || !$bitBucketUtil->authorizeOAuthInteractively($origin, $message))
) {
throw new TransportException('Could not authenticate against ' . $origin, 401);
}
}
} else {

 if ($statusCode === 404) {
return;
}


 if (!$this->io->isInteractive()) {
if ($statusCode === 401) {
$message = "The '" . $url . "' URL required authentication.\nYou must be using the interactive console to authenticate";
} elseif ($statusCode === 403) {
$message = "The '" . $url . "' URL could not be accessed: " . $reason;
} else {
$message = "Unknown error code '" . $statusCode . "', reason: " . $reason;
}

throw new TransportException($message, $statusCode);
}

 if ($this->io->hasAuthentication($origin)) {
throw new TransportException("Invalid credentials for '" . $url . "', aborting.", $statusCode);
}

$this->io->writeError('    Authentication required (<info>'.$origin.'</info>):');
$username = $this->io->ask('      Username: ');
$password = $this->io->askAndHideAnswer('      Password: ');
$this->io->setAuthentication($origin, $username, $password);
$storeAuth = $this->config->get('store-auths');
}

$retry = true;

return array('retry' => $retry, 'storeAuth' => $storeAuth);
}







public function addAuthenticationHeader(array $headers, $origin, $url)
{
if ($this->io->hasAuthentication($origin)) {
$authenticationDisplayMessage = null;
$auth = $this->io->getAuthentication($origin);
if ($auth['password'] === 'bearer') {
$headers[] = 'Authorization: Bearer '.$auth['username'];
} elseif ('github.com' === $origin && 'x-oauth-basic' === $auth['password']) {

 if (preg_match('{^https?://api\.github\.com/}', $url)) {
$headers[] = 'Authorization: token '.$auth['username'];
$authenticationDisplayMessage = 'Using GitHub token authentication';
}
} elseif (in_array($origin, $this->config->get('gitlab-domains'), true)) {
if ($auth['password'] === 'oauth2') {
$headers[] = 'Authorization: Bearer '.$auth['username'];
$authenticationDisplayMessage = 'Using GitLab OAuth token authentication';
} elseif ($auth['password'] === 'private-token' || $auth['password'] === 'gitlab-ci-token') {
$headers[] = 'PRIVATE-TOKEN: '.$auth['username'];
$authenticationDisplayMessage = 'Using GitLab private token authentication';
}
} elseif (
'bitbucket.org' === $origin
&& $url !== Bitbucket::OAUTH2_ACCESS_TOKEN_URL
&& 'x-token-auth' === $auth['username']
) {
if (!$this->isPublicBitBucketDownload($url)) {
$headers[] = 'Authorization: Bearer ' . $auth['password'];
$authenticationDisplayMessage = 'Using Bitbucket OAuth token authentication';
}
} else {
$authStr = base64_encode($auth['username'] . ':' . $auth['password']);
$headers[] = 'Authorization: Basic '.$authStr;
$authenticationDisplayMessage = 'Using HTTP basic authentication with username "' . $auth['username'] . '"';
}

if ($authenticationDisplayMessage && !in_array($origin, $this->displayedOriginAuthentications, true)) {
$this->io->writeError($authenticationDisplayMessage, true, IOInterface::DEBUG);
$this->displayedOriginAuthentications[] = $origin;
}
}

return $headers;
}








public function isPublicBitBucketDownload($urlToBitBucketFile)
{
$domain = parse_url($urlToBitBucketFile, PHP_URL_HOST);
if (strpos($domain, 'bitbucket.org') === false) {

 
 return true;
}

$path = parse_url($urlToBitBucketFile, PHP_URL_PATH);


 
 $pathParts = explode('/', $path);

return count($pathParts) >= 4 && $pathParts[3] == 'downloads';
}
}
