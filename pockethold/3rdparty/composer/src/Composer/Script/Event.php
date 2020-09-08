<?php











namespace Composer\Script;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\Event as BaseEvent;







class Event extends BaseEvent
{



private $composer;




private $io;




private $devMode;




private $originatingEvent;











public function __construct($name, Composer $composer, IOInterface $io, $devMode = false, array $args = array(), array $flags = array())
{
parent::__construct($name, $args, $flags);
$this->composer = $composer;
$this->io = $io;
$this->devMode = $devMode;
$this->originatingEvent = null;
}






public function getComposer()
{
return $this->composer;
}






public function getIO()
{
return $this->io;
}






public function isDevMode()
{
return $this->devMode;
}






public function getOriginatingEvent()
{
return $this->originatingEvent;
}







public function setOriginatingEvent(BaseEvent $event)
{
$this->originatingEvent = $this->calculateOriginatingEvent($event);

return $this;
}







private function calculateOriginatingEvent(BaseEvent $event)
{
if ($event instanceof Event && $event->getOriginatingEvent()) {
return $this->calculateOriginatingEvent($event->getOriginatingEvent());
}

return $event;
}
}
