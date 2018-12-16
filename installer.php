<?php
use Composer\Command\CreateProjectCommand;
use Composer\Command\RequireCommand;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

const GITHUB_TOKEN = 'ec785da935d5535e151f7b3386190265f00e8fe2';

/**
 * Class pockethold
 */
class Pockethold {

    var $tpath;
    var $ipath;

    public function __construct($installpath, $temppath)
    {

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
     * @param $type - Event type
     * @param $msg - Log Description
     * @param $filename - Name of logfile
     */
    public function phlog($type, $msg, $filename)
    {
        //Get timestamp
        $ltime = date("D M j G:i:s");
        //combine message
        $log = $ltime . ': ' . $type . ' ' . $msg . "\n";
        //Insert into Log
        file_put_contents($this->tpath . $filename, $log, FILE_APPEND | LOCK_EX);
    }

    /**
     * phstatus
     * @return string - Current Status
     */
    public function phstatus()
    {

        $i = "prepare1";

        if ( file_exists($this->tpath . 'composer/vendor/autoload.php')
        && file_exists($this->tpath . 'unpack.done') ) {

            if ( file_exists($this->tpath . 'bazaar.done' ) ) {
                $i = "cleanup";
                return $i;
            }
            if ( file_exists($this->tpath . 'bazaar.start' ) ) {
                $i = "waiting";
                return $i;
            }
            if ( file_exists($this->tpath . 'flarum.done' ) ) {
                $i = "bazaar";
                return $i;
            }
            if ( file_exists($this->tpath . 'flarum.start' ) ) {
                $i = "waiting";
                return $i;
            }
            if ( file_exists($this->tpath . 'unpack.done' ) ) {
                $i = "flarum";
                return $i;
            }

        }
        return $i;
    }

    private function phgetfile($src)
    {
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

    private function getComposer()
    {
        touch($this->tpath . 'unpack.start');
        if ( !file_exists($this->tpath . 'composer.phar') ) {
            $this->phgetfile('https://github.com/composer/composer/releases/download/1.7.2/composer.phar');
        }
        $composer = new Phar($this->tpath . "composer.phar");
        $composer->extractTo($this->tpath . 'composer/');
        touch($this->tpath . 'unpack.done');
    }

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
    private function rmove($src, $dest)
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

    private function phcomposer($command, $taskname)
    {
        touch($this->tpath . $taskname .'.log');
        touch($this->tpath . $taskname . '.start');
        $ini_get_option_details = ini_get_all();
        if ( $ini_get_option_details['memory_limit']['access'] & INI_USER ) {
            ini_set('memory_limit', '1G');
        } else {
            die("Not enough memory!");
        }

        ignore_user_abort(true);
        set_time_limit(1100);
        require_once($this->tpath . 'composer/vendor/autoload.php');

        $this->phlog('Composer:', 'Starting Create-Project ' . $taskname, 'install.log');
        putenv('COMPOSER_HOME=' . $this->tpath);
        putenv('COMPOSER_NO_INTERACTION=true');
        putenv('COMPOSER_PROCESS_TIMEOUT=1000');

        $application = new Application();
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => 'config',
            'github-oauth.github.com' => GITHUB_TOKEN
        ]);
        $application->run($input);
        $application->setAutoExit(false);
        $input = new StringInput($command);
        // Trying to output
        $output = new StreamOutput(fopen($this->tpath . $taskname .'.log', 'a', false));

        $application->run($input, $output);
        unset($input);
        unset($application);
        touch($this->tpath . $taskname . '.done');
        return 'done';
    }

    public function listen($request)
    {
        $allowed = array('status','prepare1','flarum','bazaar','cleanup','log', 'progress');
        if(!in_array($request,$allowed)) {
            $this->phlog('Ajax Blocked:',$request,'ajax.log');
            echo "Invalid";
        } else {
            $this->phlog('Ajax Allowed:',$request,'ajax.log');
            $this->process($request);
        }
    }

    public function process($request)
    {
        $status = $this->phstatus();
        if ($request == $status) {
            if ($request == 'prepare1') {
                echo 'Initiated';
                $this->getComposer();
            } elseif ($request == 'flarum') {
                echo 'Initiated';
                $this->phcomposer('create-project flarum/flarum ./flarumtemp --stability=beta --prefer-dist --no-progress -n', 'flarum');
            } elseif ($request == 'bazaar') {
                echo 'Initiated';
                chdir("flarumtemp");
                $this->phcomposer('require "flagrow/bazaar:*" --prefer-dist --no-progress -n -o', 'bazaar');
            } elseif ($request == 'cleanup') {
                echo 'Initiated';
                $this->cleanup();
            }
        } elseif ($request == 'status') {
            echo $status;
        } elseif ($request == 'progress') {
            $logfile = "Console output not ready yet";
            if( file_exists($this->tpath . 'flarum.start' )){
                $logfile = "flarum.log";
            }
            if( file_exists($this->tpath . 'bazaar.start' )){
                $logfile = "bazaar.log";
            }
            if ( $logfile !== "Console output not ready yet"){
                echo $this->composerProgress($logfile);
            } else
             echo $logfile;
        }
    }

    private function cleanup() {

        $this->rmove($this->ipath . "flarumtemp", $this->ipath);
        //Removes temporary directory
        $this->rrmdir($this->tpath);
        //Removes installer.php
        unlink($this->ipath . 'installer.php');
        echo "Complete";
    }

    /**
     * composerProgress($file) - Returns amount of finished vendors and the total. Composer output.
     *
     * @param $file
     * @return string
     */
    public function composerProgress($file){
        $log_file = file_get_contents($this->tpath . $file);
        $result  = "<pre id='consoleoutput' style='white-space: pre-wrap; text-align:left; height: 300px; max-height: 300px; overflow:auto; color:#fff;'>" . $log_file . "</pre>";
        return $result;
    }

}
