<?php










namespace Symfony\Component\Process\Pipes;






abstract class AbstractPipes implements PipesInterface
{
public $pipes = array();

private $inputBuffer = '';
private $input;
private $blocked = true;
private $lastError;




public function __construct($input)
{
if (\is_resource($input)) {
$this->input = $input;
} elseif (\is_string($input)) {
$this->inputBuffer = $input;
} else {
$this->inputBuffer = (string) $input;
}
}




public function close()
{
foreach ($this->pipes as $pipe) {
fclose($pipe);
}
$this->pipes = array();
}






protected function hasSystemCallBeenInterrupted()
{
$lastError = $this->lastError;
$this->lastError = null;


 return null !== $lastError && false !== stripos($lastError, 'interrupted system call');
}




protected function unblock()
{
if (!$this->blocked) {
return;
}

foreach ($this->pipes as $pipe) {
stream_set_blocking($pipe, 0);
}
if (null !== $this->input) {
stream_set_blocking($this->input, 0);
}

$this->blocked = false;
}




protected function write()
{
if (!isset($this->pipes[0])) {
return;
}
$input = $this->input;
$r = $e = array();
$w = array($this->pipes[0]);


 if (false === @stream_select($r, $w, $e, 0, 0)) {
return;
}

foreach ($w as $stdin) {
if (isset($this->inputBuffer[0])) {
$written = fwrite($stdin, $this->inputBuffer);
$this->inputBuffer = substr($this->inputBuffer, $written);
if (isset($this->inputBuffer[0])) {
return array($this->pipes[0]);
}
}

if ($input) {
for (;;) {
$data = fread($input, self::CHUNK_SIZE);
if (!isset($data[0])) {
break;
}
$written = fwrite($stdin, $data);
$data = substr($data, $written);
if (isset($data[0])) {
$this->inputBuffer = $data;

return array($this->pipes[0]);
}
}
if (feof($input)) {

 
 $this->input = null;
}
}
}


 if (null === $this->input && !isset($this->inputBuffer[0])) {
fclose($this->pipes[0]);
unset($this->pipes[0]);
} elseif (!$w) {
return array($this->pipes[0]);
}
}




public function handleError($type, $msg)
{
$this->lastError = $msg;
}
}
