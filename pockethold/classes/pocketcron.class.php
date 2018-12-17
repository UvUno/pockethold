<?php
namespace pockethold\cron;

class pocketcron {

  var $tpath;
  var $ipath;

  public function __construct($installpath, $temppath){

    $this->ipath = $installpath;
    $this->tpath = $temppath;
    $this->lpath = $temppath . 'log/';

    $pockethold = new Pockethold($this->ipath, $this->tpath);


  $pocketh

  }

}
