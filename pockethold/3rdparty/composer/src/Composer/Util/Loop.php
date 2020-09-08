<?php











namespace Composer\Util;

use Composer\Util\HttpDownloader;
use React\Promise\Promise;
use Symfony\Component\Console\Helper\ProgressBar;




class Loop
{
private $httpDownloader;
private $processExecutor;
private $currentPromises;

public function __construct(HttpDownloader $httpDownloader = null, ProcessExecutor $processExecutor = null)
{
$this->httpDownloader = $httpDownloader;
if ($this->httpDownloader) {
$this->httpDownloader->enableAsync();
}
$this->processExecutor = $processExecutor;
if ($this->processExecutor) {
$this->processExecutor->enableAsync();
}
}

public function wait(array $promises, ProgressBar $progress = null)
{

$uncaught = null;

\React\Promise\all($promises)->then(
function () { },
function ($e) use (&$uncaught) {
$uncaught = $e;
}
);

$this->currentPromises = $promises;

if ($progress) {
$totalJobs = 0;
if ($this->httpDownloader) {
$totalJobs += $this->httpDownloader->countActiveJobs();
}
if ($this->processExecutor) {
$totalJobs += $this->processExecutor->countActiveJobs();
}
$progress->start($totalJobs);
}

while (true) {
$activeJobs = 0;

if ($this->httpDownloader) {
$activeJobs += $this->httpDownloader->countActiveJobs();
}
if ($this->processExecutor) {
$activeJobs += $this->processExecutor->countActiveJobs();
}

if ($progress) {
$progress->setProgress($progress->getMaxSteps() - $activeJobs);
}

if (!$activeJobs) {
break;
}

usleep(5000);
}

$this->currentPromises = null;
if ($uncaught) {
throw $uncaught;
}
}

public function abortJobs()
{
if ($this->currentPromises) {
foreach ($this->currentPromises as $promise) {
$promise->cancel();
}
}
}
}
