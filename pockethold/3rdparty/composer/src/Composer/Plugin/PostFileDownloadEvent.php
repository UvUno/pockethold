<?php











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Composer\Package\PackageInterface;






class PostFileDownloadEvent extends Event
{




private $fileName;




private $checksum;




private $url;




private $package;










public function __construct($name, $fileName, $checksum, $url, PackageInterface $package)
{
parent::__construct($name);
$this->fileName = $fileName;
$this->checksum = $checksum;
$this->url = $url;
$this->package = $package;
}






public function getFileName()
{
return $this->fileName;
}






public function getChecksum() {
return $this->checksum;
}






public function getUrl() {
return $this->url;
}







public function getPackage() {
return $this->package;
}

}
