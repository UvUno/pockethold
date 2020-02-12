<?php
use Composer\Command\CreateProjectCommand;
use Composer\Command\RequireCommand;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

class Pockethold {

    var $tpath;
    var $ipath;

    public function __construct($installpath, $temppath)
    {

        //Add validation here of correct URL here?
        $this->tpath = $temppath;
        $this->ipath = $installpath;
        $this->lpath = $temppath . 'log/';
        if ( !file_exists($this->tpath) )
        {
            mkdir($this->tpath);
        }
        if ( !file_exists($this->lpath) )
        {
            mkdir($this->lpath);
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
        file_put_contents($this->lpath . $filename, $log, FILE_APPEND | LOCK_EX);
    }

    /**
     * phstatus
     * @return string - Current Status
     */

    public function phresponse($status, $response, $addition1 = NULL, $addition2 = NULL){
      $preparray = array("status" => $status,
                  "response" => $response,
                  "addition1" => $addition1,
                  "addition2" => $addition2);
      return $preparray;
    }


    public function phstatus()
    {

if(!file_exists($this->tpath . '3rdparty/composer/vendor/autoload.php')) {
  return $this->phresponse('Error', 'Composer is missing. Verify if Pockethold is uploaded in it\'s entirety.', NULL, NULL);
}



        $i = "flarum";

        if ( file_exists($this->tpath . '3rdparty/composer/vendor/autoload.php') ) {
            // $i = "flarum";
            $i = "prepare";
            if ( file_exists($this->lpath . 'flarum.done' ) ) {
                $i = "flarum";
            }
            if ( file_exists($this->lpath . 'flarum.start' ) ) {
                $i = "installing";
                return $i;
            }
        }
        return $i;
    }

    /**
     * rrmdir - Recursively remove directory
     * @param $dir - Event type
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
     * rmove - Recursively move files from one directory to another
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
        touch($this->lpath . $taskname .'.log');
        touch($this->lpath . $taskname . '.start');
        $ini_get_option_details = ini_get_all();
        if ( $ini_get_option_details['memory_limit']['access'] & INI_USER ) {
            ini_set('memory_limit', '1G');
        } else {
            $this->phlog('Composer:', 'Die: Not enough memory', 'install.log');
            die("Not enough memory!");
        }

        ignore_user_abort(true);
        set_time_limit(1100);
        require_once($this->tpath . '3rdparty/composer/vendor/autoload.php');

        $this->phlog('Composer:', 'Started ' . $taskname, 'install.log');
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
        $output = new StreamOutput(fopen($this->lpath . $taskname .'.log', 'a', false));

        $application->run($input, $output);
        unset($input);
        unset($application);
        touch($this->lpath . $taskname . '.done');
        return 'done';
    }

    public function listen($request)
    {
        $allowed = array('status','prepare1','install','cleanup');
        if(!in_array($request,$allowed)) {
            $this->phlog('Ajax Blocked:',$request,'ajax.log');

        } else {

            $this->process();
        }
    }

    public function process($request)
    {
        $status = $this->phstatus();
        if ($request == $status) {
            if ($request == 'prepare') {

            } elseif ($request == 'install') {
                echo 'Initiated';
                $this->phcomposer('install --stability=beta --prefer-dist --no-progress --no-interaction', 'install');
            } elseif ($request == 'cleanup') {
                echo 'Initiated';
                $this->cleanup();
            }
        } elseif ($request == 'status') {
            header('Content-Type: application/json;charset=utf-8');
            echo json_encode($this->phstatus());

        }
    }

    private function cleanup() {
        /*
        // Workign on error messages.
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
          return 'Flarum not downloaded correctly';
        }
        */
        // $this->rmove($this->tpath . "download/", $this->ipath);
        $this->rmove($this->tpath . "3rdparty/flarum/", $this->ipath);
        $this->rrmdir($this->ipath . 'public/');
        unlink($this->ipath . "installer.php");
        $this->rrmdir($this->tpath);
        echo "Complete";
    }

    /**
     * composerProgress($file) - Returns amount of finished vendors and the total. Composer output.
     *
     * @param $file
     * @return string
     */
    public function composerProgress($file){

        if ( !file_exists($this->lpath . $file) ) {
          return 'Waiting for Logfile';
        }
        $log_file = file_get_contents($this->lpath . $file);
        return $log_file;
    }

   public function checkRequirements() {
      /*
      Apache (with mod_rewrite enabled) or Nginx
      PHP 7.1+ with the following extensions: curl, dom, gd, json, mbstring, openssl, pdo_mysql, tokenizer, zip
      MySQL 5.6+ or MariaDB 10.0.5+


      https://github.com/PrestaShop/php-ps-info
      */


     function_exists('curl_version'); // Check Curl
     class_exists('DOMDocument'); // Check Dom
     function_exists('gd_info'); // CheckGD
     function_exists('json_encode'); // Check Json


   }

}
