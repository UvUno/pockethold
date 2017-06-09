<?php
/**
 * Pockethold - Flarum Web Installer.
 * Downloads composer, runs create-project flarum/flarum and remove itself.
 * Author: Andre Herberth
 * License: MIT
 * DISCLAIMER: THIS IS DIRTY. USE WITH CARE.
 * Credits: Luceos for his advice, and some good code.
 *          ibrahimk157 for listening to my rants, and testing my very unstable code.
 */

use Composer\Command\CreateProjectCommand;
use Composer\Command\RequireCommand;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;


/**
 * Class pockethold
 */
class pockethold {

    const GITHUB_TOKEN = 'ec785da935d5535e151f7b3386190265f00e8fe2';
    var $tpath;
    var $ipath;

    public function __construct($installpath, $temppath){

        //Add validation here of correct URL here?
        $this->tpath = $temppath;
        $this->ipath = $installpath;
        if ( !file_exists($this->tpath) )
        {
            mkdir($this->tpath);
        }
    }

    /**
     * phlog - For logging things.
     * @param $type
     * @param $msg
     * @param $filename
     */
    public function phlog($type, $msg, $filename) {
        //Get timestamp
        $ltime = date("D M j G:i:s");
        //combine message
        $log = $ltime . ': ' . $type . ' ' . $msg . "\n";
        //Insert into Log
        file_put_contents($this->tpath . $filename, $log, FILE_APPEND | LOCK_EX);
    }

    public function phstatus(){

        $i = "prepare";

        if ( file_exists($this->tpath . 'vendor/autoload.php') ) {
            $i = "composer";
        }
        if ( file_exists($this->tpath . 'compose.start') ) {
            $i = "waiting1";
        }
        if ( file_exists($this->tpath . 'compose.done') ) {
            $i = "cleanup1";
        }
        if ( file_exists($this->tpath . 'bazaar.start') ) {
            $i = "waiting2";
        }
        if ( file_exists($this->tpath . 'bazaar.done') ) {
            $i = "cleanup2";
        }
        return $i;

    }

    private function phgetfile($src){
        if ( !file_put_contents($this->tpath . 'composer.phar', fopen($src, 'r')) ) {
            //Shamelessly stolen, and herby credited, from Luceos's flarum installer proof of concept.
            $c = curl_init($src);
            curl_setopt_array($c, [
                CURLOPT_RETURNTRANSFER => true
            ]);
            $phar = curl_exec($c);
            curl_close($c);
            file_put_contents($this->tpath . 'composer.phar', $phar);
            unset($phar);
        }

    }

    public function prepare(){
        if ( !file_exists($this->tpath . 'composer.phar') ) {
            $this->phgetfile('https://getcomposer.org/composer.phar');
        }
        $composer = new Phar($this->tpath . "composer.phar");
        $composer->extractTo($this->tpath);
    }

    /**
     * Count lines of file.
     * @param $file
     * @return int
     */
    public function phlines ($file){
        $lines = 0;
        $file = fopen( $file, 'r');

        while( !feof( $file) ) {

            fgets($file);

            $lines++;
        }

        fclose( $file);
        return $lines;
    }

    /**
     * getfile($src, $dest) - downloads a file and saves it on the web server.
     *
     * @param $dir
     */
    function rrmdir($dir)
    {
        if ( is_dir($dir) ) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ( $object != "." && $object != ".." ) {
                    if ( is_dir($dir . "/" . $object) )
                        $this->rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Recursively move files from one directory to another
     *
     * @param String $src - Source of files being moved
     * @param String $dest - Destination of files being moved
     * @return NULL
     */
    function rmove($src, $dest)
    {

        // If source is not a directory stop processing
        if ( !is_dir($src) ) return false;

        // If the destination directory does not exist create it
        if ( !is_dir($dest) ) {
            if ( !mkdir($dest) ) {
                // If the destination directory could not be created stop processing
                return false;
            }
        }

        // Open the source directory to read in files
        $i = new DirectoryIterator($src);
        foreach ($i as $f) {
            if ( $f->isFile() ) {
                rename($f->getRealPath(), "$dest/" . $f->getFilename());
            } else if ( !$f->isDot() && $f->isDir() ) {
                $this->rmove($f->getRealPath(), "$dest/$f");
                unlink($f->getRealPath());
            }
        }
        unlink($src);
    }

    public function phcomposer($command){

        $ini_get_option_details = ini_get_all();
        if ( $ini_get_option_details['memory_limit']['access'] & INI_USER ) {
            ini_set('memory_limit', '1G');
        } else {
            die("Not enough memory!");
        }

        ignore_user_abort(true);
        set_time_limit(1100);

        require_once($this->tpath . 'vendor/autoload.php');
        $this->phlog('Composer:', 'Starting Create-Project', $this->tpath . 'install.log');
        putenv('COMPOSER_HOME=' . $this->tpath);
        putenv('COMPOSER_NO_INTERACTION=true');
        putenv('COMPOSER_PROCESS_TIMEOUT=1000');

        $application = new Application();
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => 'config',
            'github-oauth.github.com' => static::GITHUB_TOKEN
        ]);
        $application->run($input);
        $application->setAutoExit(false);
        //$input = new StringInput('create-project flarum/flarum ./flarum --stability=beta --no-progress --no-dev --ignore-platform-reqs');
        $input = new StringInput($command);
        // Trying to output
        $output = new StreamOutput(fopen($this->tpath . 'composer.log', 'a', false));

        $application->run($input, $output);
        unset($input);
        unset($application);

        return 'done';
    }


}
