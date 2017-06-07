<?php

require_once("pockethold.class.php");

if(isset($_REQUEST['ajax']) && !empty($_REQUEST["ajax"])) {

    if ( !defined('ABSPATH') )
    {
        define('ABSPATH', dirname(__FILE__) . '/');
    }
    $tmppath = (ABSPATH . 'temp/');

    $pockethold = new pockethold(ABSPATH, $tmppath);

    if($_REQUEST['ajax'] == 'status'){

        $status = $pockethold->phstatus();
        $pockethold->phlog('Status: ', $status, 'install.log');
        echo $status;

    }elseif($_REQUEST['ajax'] == 'prepare'){

        $pockethold->prepare();
        $pockethold->phlog('Status: ', 'Composer is Unpacked', 'install.log');

    elseif($_REQUEST['ajax'] == 'composer'){
        touch($pockethold->tpath . 'composer.log');
        touch($pockethold->tpath . 'compose.start');
        $pockethold->phcomposer('create-project flarum/flarum ./flarumtemp --stability=beta --no-progress --no-dev --ignore-platform-reqs');
        touch($pockethold->tpath . 'compose.done');
    }elseif($_REQUEST['ajax'] == "bazaar" ){
        touch($pockethold->tpath . 'bazaar.start');
        chdir("flarumtemp");
        $pockethold->phcomposer('require flagrow/bazaar');
        touch($pockethold->tpath . 'bazaar.done');

    }elseif($_REQUEST['ajax'] == 'cleanup'){

        $pockethold->rmove($pockethold->ipath . "flarum", $pockethold->ipath);


        //Removes temporary directory
        $pockethold->rrmdir($pockethold->tpath);
        //Removes installer.php

        // TODO: Prepare on compile time
        // unlink(__FILE__);
        echo "Complete";

    }elseif($_REQUEST['ajax'] == 'progress'){
        $pockethold->phlog('Status: ', $tmppath, 'install.log');
        $linecount = $pockethold->phlines($tmppath . 'composer.log');
        $pockethold->phlog('Status: ', "composer.log is currently $linecount long", 'install.log');
        echo $linecount;
    } else {
        die();
    }

} else {
    die();
}
