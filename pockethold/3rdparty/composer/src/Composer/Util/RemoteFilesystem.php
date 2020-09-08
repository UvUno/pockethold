<?php











namespace Composer\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Downloader\TransportException;
use Composer\CaBundle\CaBundle;
use Composer\Util\HttpDownloader;
use Composer\Util\Http\Response;







class RemoteFilesystem
{
private $io;
private $config;
private $scheme;
private $bytesMax;
private $originUrl;
private $fileUrl;
private $fileName;
private $retry;
private $progress;
private $lastProgress;
private $options = array();
private $peerCertificateMap = array();
private $disableTls = false;
private $retryAuthFailure;
private $lastHeaders;
private $storeAuth;
private $authHelper;
private $degradedMode = false;
private $redirects;
private $maxRedirects = 20;










public function __construct(IOInterface $io, Config $config, array $options = array(), $disableTls = false, AuthHelper $authHelper = null)
{
$this->io = $io;


 
 if ($disableTls === false) {
$this->options = StreamContextFactory::getTlsDefaults($options, $io);
} else {
$this->disableTls = true;
}


 $this->options = array_replace_recursive($this->options, $options);
$this->config = $config;
$this->authHelper = isset($authHelper) ? $authHelper : new AuthHelper($io, $config);
}












public function copy($originUrl, $fileUrl, $fileName, $progress = true, $options = array())
{
return $this->get($originUrl, $fileUrl, $options, $fileName, $progress);
}











public function getContents($originUrl, $fileUrl, $progress = true, $options = array())
{
return $this->get($originUrl, $fileUrl, $options, null, $progress);
}






public function getOptions()
{
return $this->options;
}






public function setOptions(array $options)
{
$this->options = array_replace_recursive($this->options, $options);
}






public function isTlsDisabled()
{
return $this->disableTls === true;
}






public function getLastHeaders()
{
return $this->lastHeaders;
}





public static function findStatusCode(array $headers)
{
$value = null;
foreach ($headers as $header) {
if (preg_match('{^HTTP/\S+ (\d+)}i', $header, $match)) {

 
 $value = (int) $match[1];
}
}

return $value;
}





public function findStatusMessage(array $headers)
{
$value = null;
foreach ($headers as $header) {
if (preg_match('{^HTTP/\S+ \d+}i', $header)) {

 
 $value = $header;
}
}

return $value;
}















protected function get($originUrl, $fileUrl, $additionalOptions = array(), $fileName = null, $progress = true)
{
$this->scheme = parse_url($fileUrl, PHP_URL_SCHEME);
$this->bytesMax = 0;
$this->originUrl = $originUrl;
$this->fileUrl = $fileUrl;
$this->fileName = $fileName;
$this->progress = $progress;
$this->lastProgress = null;
$this->retryAuthFailure = true;
$this->lastHeaders = array();
$this->redirects = 1; 

$tempAdditionalOptions = $additionalOptions;
if (isset($tempAdditionalOptions['retry-auth-failure'])) {
$this->retryAuthFailure = (bool) $tempAdditionalOptions['retry-auth-failure'];

unset($tempAdditionalOptions['retry-auth-failure']);
}

$isRedirect = false;
if (isset($tempAdditionalOptions['redirects'])) {
$this->redirects = $tempAdditionalOptions['redirects'];
$isRedirect = true;

unset($tempAdditionalOptions['redirects']);
}

$options = $this->getOptionsForUrl($originUrl, $tempAdditionalOptions);
unset($tempAdditionalOptions);

$origFileUrl = $fileUrl;

if (isset($options['gitlab-token'])) {
$fileUrl .= (false === strpos($fileUrl, '?') ? '?' : '&') . 'access_token='.$options['gitlab-token'];
unset($options['gitlab-token']);
}

if (isset($options['http'])) {
$options['http']['ignore_errors'] = true;
}

if ($this->degradedMode && substr($fileUrl, 0, 26) === 'http://repo.packagist.org/') {

 $fileUrl = 'http://' . gethostbyname('repo.packagist.org') . substr($fileUrl, 20);
$degradedPackagist = true;
}

$ctx = StreamContextFactory::getContext($fileUrl, $options, array('notification' => array($this, 'callbackGet')));

$actualContextOptions = stream_context_get_options($ctx);
$usingProxy = !empty($actualContextOptions['http']['proxy']) ? ' using proxy ' . $actualContextOptions['http']['proxy'] : '';
$this->io->writeError((substr($origFileUrl, 0, 4) === 'http' ? 'Downloading ' : 'Reading ') . Url::sanitize($origFileUrl) . $usingProxy, true, IOInterface::DEBUG);
unset($origFileUrl, $actualContextOptions);


 if ((!preg_match('{^http://(repo\.)?packagist\.org/p/}', $fileUrl) || (false === strpos($fileUrl, '$') && false === strpos($fileUrl, '%24'))) && empty($degradedPackagist) && $this->config) {
$this->config->prohibitUrlByConfig($fileUrl, $this->io);
}

if ($this->progress && !$isRedirect) {
$this->io->writeError("Downloading (<comment>connecting...</comment>)", false);
}

$errorMessage = '';
$errorCode = 0;
$result = false;
set_error_handler(function ($code, $msg) use (&$errorMessage) {
if ($errorMessage) {
$errorMessage .= "\n";
}
$errorMessage .= preg_replace('{^file_get_contents\(.*?\): }', '', $msg);

return true;
});
$http_response_header = array();
try {
$result = $this->getRemoteContents($originUrl, $fileUrl, $ctx, $http_response_header);

if (!empty($http_response_header[0])) {
$statusCode = $this->findStatusCode($http_response_header);
if ($statusCode >= 400 && Response::findHeaderValue($http_response_header, 'content-type') === 'application/json') {
HttpDownloader::outputWarnings($this->io, $originUrl, json_decode($result, true));
}

if (in_array($statusCode, array(401, 403)) && $this->retryAuthFailure) {
$this->promptAuthAndRetry($statusCode, $this->findStatusMessage($http_response_header), $http_response_header);
}
}

$contentLength = !empty($http_response_header[0]) ? Response::findHeaderValue($http_response_header, 'content-length') : null;
if ($contentLength && Platform::strlen($result) < $contentLength) {

 $e = new TransportException('Content-Length mismatch, received '.Platform::strlen($result).' bytes out of the expected '.$contentLength);
$e->setHeaders($http_response_header);
$e->setStatusCode($this->findStatusCode($http_response_header));
$e->setResponse($result);
$this->io->writeError('Content-Length mismatch, received '.Platform::strlen($result).' out of '.$contentLength.' bytes: (' . base64_encode($result).')', true, IOInterface::DEBUG);

throw $e;
}

if (PHP_VERSION_ID < 50600 && !empty($options['ssl']['peer_fingerprint'])) {

 $params = stream_context_get_params($ctx);
$expectedPeerFingerprint = $options['ssl']['peer_fingerprint'];
$peerFingerprint = TlsHelper::getCertificateFingerprint($params['options']['ssl']['peer_certificate']);


 if ($expectedPeerFingerprint !== $peerFingerprint) {
throw new TransportException('Peer fingerprint did not match');
}
}
} catch (\Exception $e) {
if ($e instanceof TransportException && !empty($http_response_header[0])) {
$e->setHeaders($http_response_header);
$e->setStatusCode($this->findStatusCode($http_response_header));
}
if ($e instanceof TransportException && $result !== false) {
$e->setResponse($result);
}
$result = false;
}
if ($errorMessage && !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
$errorMessage = 'allow_url_fopen must be enabled in php.ini ('.$errorMessage.')';
}
restore_error_handler();
if (isset($e) && !$this->retry) {
if (!$this->degradedMode && false !== strpos($e->getMessage(), 'Operation timed out')) {
$this->degradedMode = true;
$this->io->writeError('');
$this->io->writeError(array(
'<error>'.$e->getMessage().'</error>',
'<error>Retrying with degraded mode, check https://getcomposer.org/doc/articles/troubleshooting.md#degraded-mode for more info</error>',
));

return $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);
}

throw $e;
}

$statusCode = null;
$contentType = null;
$locationHeader = null;
if (!empty($http_response_header[0])) {
$statusCode = $this->findStatusCode($http_response_header);
$contentType = Response::findHeaderValue($http_response_header, 'content-type');
$locationHeader = Response::findHeaderValue($http_response_header, 'location');
}


 if ($originUrl === 'bitbucket.org'
&& !$this->authHelper->isPublicBitBucketDownload($fileUrl)
&& substr($fileUrl, -4) === '.zip'
&& (!$locationHeader || substr(parse_url($locationHeader, PHP_URL_PATH), -4) !== '.zip')
&& $contentType && preg_match('{^text/html\b}i', $contentType)
) {
$result = false;
if ($this->retryAuthFailure) {
$this->promptAuthAndRetry(401);
}
}


 if ($statusCode === 404
&& $this->config && in_array($originUrl, $this->config->get('gitlab-domains'), true)
&& false !== strpos($fileUrl, 'archive.zip')
) {
$result = false;
if ($this->retryAuthFailure) {
$this->promptAuthAndRetry(401);
}
}


 $hasFollowedRedirect = false;
if ($statusCode >= 300 && $statusCode <= 399 && $statusCode !== 304 && $this->redirects < $this->maxRedirects) {
$hasFollowedRedirect = true;
$result = $this->handleRedirect($http_response_header, $additionalOptions, $result);
}


 if ($statusCode && $statusCode >= 400 && $statusCode <= 599) {
if (!$this->retry) {
if ($this->progress && !$isRedirect) {
$this->io->overwriteError("Downloading (<error>failed</error>)", false);
}

$e = new TransportException('The "'.$this->fileUrl.'" file could not be downloaded ('.$http_response_header[0].')', $statusCode);
$e->setHeaders($http_response_header);
$e->setResponse($result);
$e->setStatusCode($statusCode);
throw $e;
}
$result = false;
}

if ($this->progress && !$this->retry && !$isRedirect) {
$this->io->overwriteError("Downloading (".($result === false ? '<error>failed</error>' : '<comment>100%</comment>').")", false);
}


 if ($result && extension_loaded('zlib') && substr($fileUrl, 0, 4) === 'http' && !$hasFollowedRedirect) {
$contentEncoding = Response::findHeaderValue($http_response_header, 'content-encoding');
$decode = $contentEncoding && 'gzip' === strtolower($contentEncoding);

if ($decode) {
try {
if (PHP_VERSION_ID >= 50400) {
$result = zlib_decode($result);
} else {

 $result = file_get_contents('compress.zlib://data:application/octet-stream;base64,'.base64_encode($result));
}

if (!$result) {
throw new TransportException('Failed to decode zlib stream');
}
} catch (\Exception $e) {
if ($this->degradedMode) {
throw $e;
}

$this->degradedMode = true;
$this->io->writeError(array(
'',
'<error>Failed to decode response: '.$e->getMessage().'</error>',
'<error>Retrying with degraded mode, check https://getcomposer.org/doc/articles/troubleshooting.md#degraded-mode for more info</error>',
));

return $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);
}
}
}


 if (false !== $result && null !== $fileName && !$isRedirect) {
if ('' === $result) {
throw new TransportException('"'.$this->fileUrl.'" appears broken, and returned an empty 200 response');
}

$errorMessage = '';
set_error_handler(function ($code, $msg) use (&$errorMessage) {
if ($errorMessage) {
$errorMessage .= "\n";
}
$errorMessage .= preg_replace('{^file_put_contents\(.*?\): }', '', $msg);

return true;
});
$result = (bool) file_put_contents($fileName, $result);
restore_error_handler();
if (false === $result) {
throw new TransportException('The "'.$this->fileUrl.'" file could not be written to '.$fileName.': '.$errorMessage);
}
}


 if (false === $result && false !== strpos($errorMessage, 'Peer certificate') && PHP_VERSION_ID < 50600) {

 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 if (CaBundle::isOpensslParseSafe()) {
$certDetails = $this->getCertificateCnAndFp($this->fileUrl, $options);

if ($certDetails) {
$this->peerCertificateMap[$this->getUrlAuthority($this->fileUrl)] = $certDetails;

$this->retry = true;
}
} else {
$this->io->writeError('');
$this->io->writeError(sprintf(
'<error>Your version of PHP, %s, is affected by CVE-2013-6420 and cannot safely perform certificate validation, we strongly suggest you upgrade.</error>',
PHP_VERSION
));
}
}

if ($this->retry) {
$this->retry = false;

$result = $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);

if ($this->storeAuth && $this->config) {
$this->authHelper->storeAuth($this->originUrl, $this->storeAuth);
$this->storeAuth = false;
}

return $result;
}

if (false === $result) {
$e = new TransportException('The "'.$this->fileUrl.'" file could not be downloaded: '.$errorMessage, $errorCode);
if (!empty($http_response_header[0])) {
$e->setHeaders($http_response_header);
}

if (!$this->degradedMode && false !== strpos($e->getMessage(), 'Operation timed out')) {
$this->degradedMode = true;
$this->io->writeError('');
$this->io->writeError(array(
'<error>'.$e->getMessage().'</error>',
'<error>Retrying with degraded mode, check https://getcomposer.org/doc/articles/troubleshooting.md#degraded-mode for more info</error>',
));

return $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);
}

throw $e;
}

if (!empty($http_response_header[0])) {
$this->lastHeaders = $http_response_header;
}

return $result;
}










protected function getRemoteContents($originUrl, $fileUrl, $context, array &$responseHeaders = null)
{
$result = false;

try {
$e = null;
$result = file_get_contents($fileUrl, false, $context);
} catch (\Throwable $e) {
} catch (\Exception $e) {
}

$responseHeaders = isset($http_response_header) ? $http_response_header : array();

if (null !== $e) {
throw $e;
}

return $result;
}












protected function callbackGet($notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax)
{
switch ($notificationCode) {
case STREAM_NOTIFY_FAILURE:
if (400 === $messageCode) {

 
 throw new TransportException("The '" . $this->fileUrl . "' URL could not be accessed: " . $message, $messageCode);
}
break;

case STREAM_NOTIFY_FILE_SIZE_IS:
$this->bytesMax = $bytesMax;
break;

case STREAM_NOTIFY_PROGRESS:
if ($this->bytesMax > 0 && $this->progress) {
$progression = min(100, round($bytesTransferred / $this->bytesMax * 100));

if ((0 === $progression % 5) && 100 !== $progression && $progression !== $this->lastProgress) {
$this->lastProgress = $progression;
$this->io->overwriteError("Downloading (<comment>$progression%</comment>)", false);
}
}
break;

default:
break;
}
}

protected function promptAuthAndRetry($httpStatus, $reason = null, $headers = array())
{
$result = $this->authHelper->promptAuthIfNeeded($this->fileUrl, $this->originUrl, $httpStatus, $reason, $headers);

$this->storeAuth = $result['storeAuth'];
$this->retry = $result['retry'];

if ($this->retry) {
throw new TransportException('RETRY');
}
}

protected function getOptionsForUrl($originUrl, $additionalOptions)
{
$tlsOptions = array();


 if ($this->disableTls === false && PHP_VERSION_ID < 50600 && !stream_is_local($this->fileUrl)) {
$host = parse_url($this->fileUrl, PHP_URL_HOST);

if (PHP_VERSION_ID < 50304) {

 
 
 

if ($host === 'github.com' || $host === 'api.github.com') {
$host = '*.github.com';
}
}

$tlsOptions['ssl']['CN_match'] = $host;
$tlsOptions['ssl']['SNI_server_name'] = $host;

$urlAuthority = $this->getUrlAuthority($this->fileUrl);

if (isset($this->peerCertificateMap[$urlAuthority])) {

 $certMap = $this->peerCertificateMap[$urlAuthority];

$this->io->writeError('', true, IOInterface::DEBUG);
$this->io->writeError(sprintf(
'Using <info>%s</info> as CN for subjectAltName enabled host <info>%s</info>',
$certMap['cn'],
$urlAuthority
), true, IOInterface::DEBUG);

$tlsOptions['ssl']['CN_match'] = $certMap['cn'];
$tlsOptions['ssl']['peer_fingerprint'] = $certMap['fp'];
} elseif (!CaBundle::isOpensslParseSafe() && $host === 'repo.packagist.org') {

 $tlsOptions['ssl']['CN_match'] = 'packagist.org';
}
}

$headers = array();

if (extension_loaded('zlib')) {
$headers[] = 'Accept-Encoding: gzip';
}

$options = array_replace_recursive($this->options, $tlsOptions, $additionalOptions);
if (!$this->degradedMode) {

 
 $options['http']['protocol_version'] = 1.1;
$headers[] = 'Connection: close';
}

$headers = $this->authHelper->addAuthenticationHeader($headers, $originUrl, $this->fileUrl);

$options['http']['follow_location'] = 0;

if (isset($options['http']['header']) && !is_array($options['http']['header'])) {
$options['http']['header'] = explode("\r\n", trim($options['http']['header'], "\r\n"));
}
foreach ($headers as $header) {
$options['http']['header'][] = $header;
}

return $options;
}

private function handleRedirect(array $http_response_header, array $additionalOptions, $result)
{
if ($locationHeader = Response::findHeaderValue($http_response_header, 'location')) {
if (parse_url($locationHeader, PHP_URL_SCHEME)) {

 $targetUrl = $locationHeader;
} elseif (parse_url($locationHeader, PHP_URL_HOST)) {

 $targetUrl = $this->scheme.':'.$locationHeader;
} elseif ('/' === $locationHeader[0]) {

 $urlHost = parse_url($this->fileUrl, PHP_URL_HOST);


 $targetUrl = preg_replace('{^(.+(?://|@)'.preg_quote($urlHost).'(?::\d+)?)(?:[/\?].*)?$}', '\1'.$locationHeader, $this->fileUrl);
} else {

 
 $targetUrl = preg_replace('{^(.+/)[^/?]*(?:\?.*)?$}', '\1'.$locationHeader, $this->fileUrl);
}
}

if (!empty($targetUrl)) {
$this->redirects++;

$this->io->writeError('', true, IOInterface::DEBUG);
$this->io->writeError(sprintf('Following redirect (%u) %s', $this->redirects, Url::sanitize($targetUrl)), true, IOInterface::DEBUG);

$additionalOptions['redirects'] = $this->redirects;

return $this->get(parse_url($targetUrl, PHP_URL_HOST), $targetUrl, $additionalOptions, $this->fileName, $this->progress);
}

if (!$this->retry) {
$e = new TransportException('The "'.$this->fileUrl.'" file could not be downloaded, got redirect without Location ('.$http_response_header[0].')');
$e->setHeaders($http_response_header);
$e->setResponse($result);

throw $e;
}

return false;
}






private function getCertificateCnAndFp($url, $options)
{
if (PHP_VERSION_ID >= 50600) {
throw new \BadMethodCallException(sprintf(
'%s must not be used on PHP >= 5.6',
__METHOD__
));
}

$context = StreamContextFactory::getContext($url, $options, array('options' => array(
'ssl' => array(
'capture_peer_cert' => true,
'verify_peer' => false, 
 ), ),
));


 
 if (false === $handle = @fopen($url, 'rb', false, $context)) {
return;
}


 fclose($handle);
$handle = null;

$params = stream_context_get_params($context);

if (!empty($params['options']['ssl']['peer_certificate'])) {
$peerCertificate = $params['options']['ssl']['peer_certificate'];

if (TlsHelper::checkCertificateHost($peerCertificate, parse_url($url, PHP_URL_HOST), $commonName)) {
return array(
'cn' => $commonName,
'fp' => TlsHelper::getCertificateFingerprint($peerCertificate),
);
}
}
}

private function getUrlAuthority($url)
{
$defaultPorts = array(
'ftp' => 21,
'http' => 80,
'https' => 443,
'ssh2.sftp' => 22,
'ssh2.scp' => 22,
);

$scheme = parse_url($url, PHP_URL_SCHEME);

if (!isset($defaultPorts[$scheme])) {
throw new \InvalidArgumentException(sprintf(
'Could not get default port for unknown scheme: %s',
$scheme
));
}

$defaultPort = $defaultPorts[$scheme];
$port = parse_url($url, PHP_URL_PORT) ?: $defaultPort;

return parse_url($url, PHP_URL_HOST).':'.$port;
}
}
