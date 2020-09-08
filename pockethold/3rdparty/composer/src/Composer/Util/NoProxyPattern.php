<?php











namespace Composer\Util;

use stdClass;




class NoProxyPattern
{



protected $hostNames = array();




protected $rules = array();




protected $noproxy;




public function __construct($pattern)
{
$this->hostNames = preg_split('{[\s,]+}', $pattern, null, PREG_SPLIT_NO_EMPTY);
$this->noproxy = empty($this->hostNames) || '*' === $this->hostNames[0];
}








public function test($url)
{
if ($this->noproxy) {
return true;
}

if (!$urlData = $this->getUrlData($url)) {
return false;
}

foreach ($this->hostNames as $index => $hostName) {
if ($this->match($index, $hostName, $urlData)) {
return true;
}
}

return false;
}








protected function getUrlData($url)
{
if (!$host = parse_url($url, PHP_URL_HOST)) {
return false;
}

$port = parse_url($url, PHP_URL_PORT);

if (empty($port)) {
switch (parse_url($url, PHP_URL_SCHEME)) {
case 'http':
$port = 80;
break;
case 'https':
$port = 443;
break;
}
}

$hostName = $host . ($port ? ':' . $port : '');
list($host, $port, $err) = $this->splitHostPort($hostName);

if ($err || !$this->ipCheckData($host, $ipdata)) {
return false;
}

return $this->makeData($host, $port, $ipdata);
}










protected function match($index, $hostName, $url)
{
if (!$rule = $this->getRule($index, $hostName)) {

 return false;
}

if ($rule->ipdata) {

 if (!$url->ipdata) {
return false;
}

if ($rule->ipdata->netmask) {
return $this->matchRange($rule->ipdata, $url->ipdata);
}

$match = $rule->ipdata->ip === $url->ipdata->ip;
} else {

 $haystack = substr($url->name, - strlen($rule->name));
$match = stripos($haystack, $rule->name) === 0;
}

if ($match && $rule->port) {
$match = $rule->port === $url->port;
}

return $match;
}









protected function matchRange(stdClass $network, stdClass $target)
{
$net = unpack('C*', $network->ip);
$mask = unpack('C*', $network->netmask);
$ip = unpack('C*', $target->ip);

for ($i = 1; $i < 17; ++$i) {
if (($net[$i] & $mask[$i]) !== ($ip[$i] & $mask[$i])) {
return false;
}
}

return true;
}









private function getRule($index, $hostName)
{
if (array_key_exists($index, $this->rules)) {
return $this->rules[$index];
}

$this->rules[$index] = null;
list($host, $port, $err) = $this->splitHostPort($hostName);

if ($err || !$this->ipCheckData($host, $ipdata, true)) {
return null;
}

$this->rules[$index] = $this->makeData($host, $port, $ipdata);

return $this->rules[$index];
}










private function ipCheckData($host, &$ipdata, $allowPrefix = false)
{
$ipdata = null;
$netmask = null;
$prefix = null;
$modified = false;


 if (strpos($host, '/') !== false) {
list($host, $prefix) = explode('/', $host);

if (!$allowPrefix || !$this->validateInt($prefix, 0, 128)) {
return false;
}
$prefix = (int) $prefix;
$modified = true;
}


 if (!filter_var($host, FILTER_VALIDATE_IP)) {
return !$modified;
}

list($ip, $size) = $this->ipGetAddr($host);

if ($prefix !== null) {

 if ($prefix > $size * 8) {
return false;
}

list($ip, $netmask) = $this->ipGetNetwork($ip, $size, $prefix);
}

$ipdata = $this->makeIpData($ip, $size, $netmask);

return true;
}











private function ipGetAddr($host)
{
$ip = inet_pton($host);
$size = strlen($ip);
$mapped = $this->ipMapTo6($ip, $size);

return array($mapped, $size);
}









private function ipGetMask($prefix, $size)
{
$mask = '';

if ($ones = floor($prefix / 8)) {
$mask = str_repeat(chr(255), $ones);
}

if ($remainder = $prefix % 8) {
$mask .= chr(0xff ^ (0xff >> $remainder));
}

$mask = str_pad($mask, $size, chr(0));

return $this->ipMapTo6($mask, $size);
}










private function ipGetNetwork($rangeIp, $size, $prefix)
{
$netmask = $this->ipGetMask($prefix, $size);


 $mask = unpack('C*', $netmask);
$ip = unpack('C*', $rangeIp);
$net = '';

for ($i = 1; $i < 17; ++$i) {
$net .= chr($ip[$i] & $mask[$i]);
}

return array($net, $netmask);
}









private function ipMapTo6($binary, $size)
{
if ($size === 4) {
$prefix = str_repeat(chr(0), 10) . str_repeat(chr(255), 2);
$binary = $prefix . $binary;
}

return $binary;
}










private function makeData($host, $port, $ipdata)
{
return (object) array(
'host' => $host,
'name' => '.' . ltrim($host, '.'),
'port' => $port,
'ipdata' => $ipdata,
);
}










private function makeIpData($ip, $size, $netmask)
{
return (object) array(
'ip' => $ip,
'size' => $size,
'netmask' => $netmask,
);
}








private function splitHostPort($hostName)
{

 $error = array('', '', true);
$port = 0;
$ip6 = '';


 if ($hostName[0] === '[') {
$index = strpos($hostName, ']');


 if (false === $index || $index < 3) {
return $error;
}

$ip6 = substr($hostName, 1, $index - 1);
$hostName = substr($hostName, $index + 1);

if (strpbrk($hostName, '[]') !== false
|| substr_count($hostName, ':') > 1) {
return $error;
}
}

if (substr_count($hostName, ':') === 1) {
$index = strpos($hostName, ':');
$port = substr($hostName, $index + 1);
$hostName = substr($hostName, 0, $index);

if (!$this->validateInt($port, 1, 65535)) {
return $error;
}

$port = (int) $port;
}

$host = $ip6 . $hostName;

return array($host, $port, false);
}








private function validateInt($int, $min, $max)
{
$options = array(
'options' => array(
'min_range' => $min,
'max_range' => $max)
);

return false !== filter_var($int, FILTER_VALIDATE_INT, $options);
}
}
