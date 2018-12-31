<?php

namespace Pockethold\Api;
use Pockethold\Pockethold;

class Api extends Pockethold {

    public function listen($ear) {
        $allowed = array('status','prepare1','flarum','bazaar','cleanup','log', 'progress');
        if(!in_array($ear,$allowed)) {
            $this->phlog('Ajax Blocked:',$request,'ajax.log');
            echo "Invalid";
        } else {
            $this->phlog('Ajax Allowed:',$request,'ajax.log');
            $this->process($request);
        }
    }

    public function process(){



    }

}
