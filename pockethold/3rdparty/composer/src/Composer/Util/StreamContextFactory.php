<?php











namespace Composer\Util;

use Composer\Composer;
use Composer\CaBundle\CaBundle;
use Composer\Downloader\TransportException;
use Psr\Log\LoggerInterface;







final class StreamContextFactory
{










public static function getContext($url, array $defaultOptions = array(), array $defaultParams = array())
{
$options = array('http' => array(

 'follow_location' => 1,
'max_redirects' => 20,
));

$options = array_replace_recursive($options, self::initOptions($url, $defaultOptions));
unset($defaultOptions['http']['header']);
$options = array_replace_recursive($options, $defaultOptions);

if (isset($options['http']['header'])) {
$options['http']['header'] = self::fixHttpHeaderField($options['http']['header']);
}

return stream_context_create($options, $defaultParams);
}







public static function initOptions($url, array $options)
{

 if (!isset($options['http']['header'])) {
$options['http']['header'] = array();
}
if (is_string($options['http']['header'])) {
$options['http']['header'] = explode("\r\n", $options['http']['header']);
}


 if ((PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') && (!empty($_SERVER['HTTP_PROXY']) || !empty($_SERVER['http_proxy']))) {
$proxy = parse_url(!empty($_SERVER['http_proxy']) ? $_SERVER['http_proxy'] : $_SERVER['HTTP_PROXY']);
}


 if (!empty($_SERVER['CGI_HTTP_PROXY'])) {
$proxy = parse_url($_SERVER['CGI_HTTP_PROXY']);
}


 if (preg_match('{^https://}i', $url) && (!empty($_SERVER['HTTPS_PROXY']) || !empty($_SERVER['https_proxy']))) {
$proxy = parse_url(!empty($_SERVER['https_proxy']) ? $_SERVER['https_proxy'] : $_SERVER['HTTPS_PROXY']);
}


 if (!empty($_SERVER['NO_PROXY']) || !empty($_SERVER['no_proxy']) && parse_url($url, PHP_URL_HOST)) {
$pattern = new NoProxyPattern(!empty($_SERVER['no_proxy']) ? $_SERVER['no_proxy'] : $_SERVER['NO_PROXY']);
if ($pattern->test($url)) {
unset($proxy);
}
}

if (!empty($proxy)) {
$proxyURL = isset($proxy['scheme']) ? $proxy['scheme'] . '://' : '';
$proxyURL .= isset($proxy['host']) ? $proxy['host'] : '';

if (isset($proxy['port'])) {
$proxyURL .= ":" . $proxy['port'];
} elseif ('http://' == substr($proxyURL, 0, 7)) {
$proxyURL .= ":80";
} elseif ('https://' == substr($proxyURL, 0, 8)) {
$proxyURL .= ":443";
}


 $proxyURL = str_replace(array('http://', 'https://'), array('tcp://', 'ssl://'), $proxyURL);

if (0 === strpos($proxyURL, 'ssl:') && !extension_loaded('openssl')) {
throw new \RuntimeException('You must enable the openssl extension to use a proxy over https');
}

$options['http']['proxy'] = $proxyURL;


 switch (parse_url($url, PHP_URL_SCHEME)) {
case 'http': 
 $reqFullUriEnv = getenv('HTTP_PROXY_REQUEST_FULLURI');
if ($reqFullUriEnv === false || $reqFullUriEnv === '' || (strtolower($reqFullUriEnv) !== 'false' && (bool) $reqFullUriEnv)) {
$options['http']['request_fulluri'] = true;
}
break;
case 'https': 
 $reqFullUriEnv = getenv('HTTPS_PROXY_REQUEST_FULLURI');
if (strtolower($reqFullUriEnv) !== 'false' && (bool) $reqFullUriEnv) {
$options['http']['request_fulluri'] = true;
}
break;
}


 if ('https' === parse_url($url, PHP_URL_SCHEME)) {
$options['ssl']['SNI_enabled'] = true;
if (PHP_VERSION_ID < 50600) {
$options['ssl']['SNI_server_name'] = parse_url($url, PHP_URL_HOST);
}
}


 if (isset($proxy['user'])) {
$auth = rawurldecode($proxy['user']);
if (isset($proxy['pass'])) {
$auth .= ':' . rawurldecode($proxy['pass']);
}
$auth = base64_encode($auth);

$options['http']['header'][] = "Proxy-Authorization: Basic {$auth}";
}
}

if (defined('HHVM_VERSION')) {
$phpVersion = 'HHVM ' . HHVM_VERSION;
} else {
$phpVersion = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
}

if (extension_loaded('curl')) {
$curl = curl_version();
$httpVersion = 'curl '.$curl['version'];
} else {
$httpVersion = 'streams';
}

if (!isset($options['http']['header']) || false === stripos(implode('', $options['http']['header']), 'user-agent')) {
$options['http']['header'][] = sprintf(
'User-Agent: Composer/%s (%s; %s; %s; %s%s)',
Composer::getVersion(),
function_exists('php_uname') ? php_uname('s') : 'Unknown',
function_exists('php_uname') ? php_uname('r') : 'Unknown',
$phpVersion,
$httpVersion,
getenv('CI') ? '; CI' : ''
);
}

return $options;
}






public static function getTlsDefaults(array $options, LoggerInterface $logger = null)
{
$ciphers = implode(':', array(
'ECDHE-RSA-AES128-GCM-SHA256',
'ECDHE-ECDSA-AES128-GCM-SHA256',
'ECDHE-RSA-AES256-GCM-SHA384',
'ECDHE-ECDSA-AES256-GCM-SHA384',
'DHE-RSA-AES128-GCM-SHA256',
'DHE-DSS-AES128-GCM-SHA256',
'kEDH+AESGCM',
'ECDHE-RSA-AES128-SHA256',
'ECDHE-ECDSA-AES128-SHA256',
'ECDHE-RSA-AES128-SHA',
'ECDHE-ECDSA-AES128-SHA',
'ECDHE-RSA-AES256-SHA384',
'ECDHE-ECDSA-AES256-SHA384',
'ECDHE-RSA-AES256-SHA',
'ECDHE-ECDSA-AES256-SHA',
'DHE-RSA-AES128-SHA256',
'DHE-RSA-AES128-SHA',
'DHE-DSS-AES128-SHA256',
'DHE-RSA-AES256-SHA256',
'DHE-DSS-AES256-SHA',
'DHE-RSA-AES256-SHA',
'AES128-GCM-SHA256',
'AES256-GCM-SHA384',
'AES128-SHA256',
'AES256-SHA256',
'AES128-SHA',
'AES256-SHA',
'AES',
'CAMELLIA',
'DES-CBC3-SHA',
'!aNULL',
'!eNULL',
'!EXPORT',
'!DES',
'!RC4',
'!MD5',
'!PSK',
'!aECDH',
'!EDH-DSS-DES-CBC3-SHA',
'!EDH-RSA-DES-CBC3-SHA',
'!KRB5-DES-CBC3-SHA',
));







$defaults = array(
'ssl' => array(
'ciphers' => $ciphers,
'verify_peer' => true,
'verify_depth' => 7,
'SNI_enabled' => true,
'capture_peer_cert' => true,
),
);

if (isset($options['ssl'])) {
$defaults['ssl'] = array_replace_recursive($defaults['ssl'], $options['ssl']);
}





if (!isset($defaults['ssl']['cafile']) && !isset($defaults['ssl']['capath'])) {
$result = CaBundle::getSystemCaRootBundlePath($logger);

if (is_dir($result)) {
$defaults['ssl']['capath'] = $result;
} else {
$defaults['ssl']['cafile'] = $result;
}
}

if (isset($defaults['ssl']['cafile']) && (!is_readable($defaults['ssl']['cafile']) || !CaBundle::validateCaFile($defaults['ssl']['cafile'], $logger))) {
throw new TransportException('The configured cafile was not valid or could not be read.');
}

if (isset($defaults['ssl']['capath']) && (!is_dir($defaults['ssl']['capath']) || !is_readable($defaults['ssl']['capath']))) {
throw new TransportException('The configured capath was not valid or could not be read.');
}




if (PHP_VERSION_ID >= 50413) {
$defaults['ssl']['disable_compression'] = true;
}

return $defaults;
}











private static function fixHttpHeaderField($header)
{
if (!is_array($header)) {
$header = explode("\r\n", $header);
}
uasort($header, function ($el) {
return stripos($el, 'content-type') === 0 ? 1 : -1;
});

return $header;
}
}
