<?php











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Composer\Util\HttpDownloader;






class PreFileDownloadEvent extends Event
{



private $httpDownloader;




private $processedUrl;




private $type;




private $context;










public function __construct($name, HttpDownloader $httpDownloader, $processedUrl, $type, $context = null)
{
parent::__construct($name);
$this->httpDownloader = $httpDownloader;
$this->processedUrl = $processedUrl;
$this->type = $type;
$this->context = $context;
}




public function getHttpDownloader()
{
return $this->httpDownloader;
}






public function getProcessedUrl()
{
return $this->processedUrl;
}






public function setProcessedUrl($processedUrl)
{
$this->processedUrl = $processedUrl;
}






public function getType()
{
return $this->type;
}








public function getContext()
{
return $this->context;
}
}
