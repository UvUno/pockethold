<?php










namespace Symfony\Component\Process\Pipes;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;











class WindowsPipes extends AbstractPipes
{
private $files = array();
private $fileHandles = array();
private $readBytes = array(
Process::STDOUT => 0,
Process::STDERR => 0,
);
private $disableOutput;

public function __construct($disableOutput, $input)
{
$this->disableOutput = (bool) $disableOutput;

if (!$this->disableOutput) {

 
 
 
 $pipes = array(
Process::STDOUT => Process::OUT,
Process::STDERR => Process::ERR,
);
$tmpCheck = false;
$tmpDir = sys_get_temp_dir();
$lastError = 'unknown reason';
set_error_handler(function ($type, $msg) use (&$lastError) { $lastError = $msg; });
for ($i = 0;; ++$i) {
foreach ($pipes as $pipe => $name) {
$file = sprintf('%s\\sf_proc_%02X.%s', $tmpDir, $i, $name);
if (file_exists($file) && !unlink($file)) {
continue 2;
}
$h = fopen($file, 'xb');
if (!$h) {
$error = $lastError;
if ($tmpCheck || $tmpCheck = unlink(tempnam(false, 'sf_check_'))) {
continue;
}
restore_error_handler();
throw new RuntimeException(sprintf('A temporary file could not be opened to write the process output: %s', $error));
}
if (!$h || !$this->fileHandles[$pipe] = fopen($file, 'rb')) {
continue 2;
}
if (isset($this->files[$pipe])) {
unlink($this->files[$pipe]);
}
$this->files[$pipe] = $file;
}
break;
}
restore_error_handler();
}

parent::__construct($input);
}

public function __destruct()
{
$this->close();
$this->removeFiles();
}




public function getDescriptors()
{
if ($this->disableOutput) {
$nullstream = fopen('NUL', 'c');

return array(
array('pipe', 'r'),
$nullstream,
$nullstream,
);
}


 
 
 return array(
array('pipe', 'r'),
array('file', 'NUL', 'w'),
array('file', 'NUL', 'w'),
);
}




public function getFiles()
{
return $this->files;
}




public function readAndWrite($blocking, $close = false)
{
$this->unblock();
$w = $this->write();
$read = $r = $e = array();

if ($blocking) {
if ($w) {
@stream_select($r, $w, $e, 0, Process::TIMEOUT_PRECISION * 1E6);
} elseif ($this->fileHandles) {
usleep(Process::TIMEOUT_PRECISION * 1E6);
}
}
foreach ($this->fileHandles as $type => $fileHandle) {
$data = stream_get_contents($fileHandle, -1, $this->readBytes[$type]);

if (isset($data[0])) {
$this->readBytes[$type] += strlen($data);
$read[$type] = $data;
}
if ($close) {
fclose($fileHandle);
unset($this->fileHandles[$type]);
}
}

return $read;
}




public function areOpen()
{
return $this->pipes && $this->fileHandles;
}




public function close()
{
parent::close();
foreach ($this->fileHandles as $handle) {
fclose($handle);
}
$this->fileHandles = array();
}









public static function create(Process $process, $input)
{
return new static($process->isOutputDisabled(), $input);
}




private function removeFiles()
{
foreach ($this->files as $filename) {
if (file_exists($filename)) {
@unlink($filename);
}
}
$this->files = array();
}
}
