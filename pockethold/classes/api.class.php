<?php

namespace Pockethold;
use Pockethold\Pockethold;

class Api extends Pockethold {

    public function listen($ear) {
        $allowed = array('status','prepare1','flarum','bazaar','cleanup','log', 'progress');
        if(!in_array($ear,$allowed)) {
            parent::phlog('Ajax Blocked:',$request,'ajax.log');
            return "Invalid";
        } else {
            parent::phlog('Ajax Allowed:',$request,'ajax.log');
            $this->process($request);
        }
    }

    public function process($process){

      $output = "Sucess";

      return $output;

    }

}
