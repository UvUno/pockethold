<?php











namespace Composer\Util\Http;

use Composer\Json\JsonFile;

class Response
{
private $request;
private $code;
private $headers;
private $body;

public function __construct(array $request, $code, array $headers, $body)
{
if (!isset($request['url'])) {
throw new \LogicException('url key missing from request array');
}
$this->request = $request;
$this->code = (int) $code;
$this->headers = $headers;
$this->body = $body;
}

public function getStatusCode()
{
return $this->code;
}




public function getStatusMessage()
{
$value = null;
foreach ($this->headers as $header) {
if (preg_match('{^HTTP/\S+ \d+}i', $header)) {

 
 $value = $header;
}
}

return $value;
}

public function getHeaders()
{
return $this->headers;
}

public function getHeader($name)
{
return self::findHeaderValue($this->headers, $name);
}

public function getBody()
{
return $this->body;
}

public function decodeJson()
{
return JsonFile::parseJson($this->body, $this->request['url']);
}

public function collect()
{
$this->request = $this->code = $this->headers = $this->body = null;
}






public static function findHeaderValue(array $headers, $name)
{
$value = null;
foreach ($headers as $header) {
if (preg_match('{^'.preg_quote($name).':\s*(.+?)\s*$}i', $header, $match)) {
$value = $match[1];
} elseif (preg_match('{^HTTP/}i', $header)) {

 
 
 
 $value = null;
}
}

return $value;
}
}
