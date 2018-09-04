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
     * @param $type
     * @param $msg
     * @param $filename
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

    public function phstatus()
    {

        $i = "prepare1";

        if ( file_exists($this->tpath . 'vendor/autoload.php')
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

    public function getComposer()
    {
        touch($this->tpath . 'unpack.start');
        if ( !file_exists($this->tpath . 'composer.phar') ) {
            $this->phgetfile('https://getcomposer.org/composer.phar');
        }
        $composer = new Phar($this->tpath . "composer.phar");
        $composer->extractTo($this->tpath);
        touch($this->tpath . 'unpack.done');
    }

    /**
     * Count lines of file.
     * @param $file
     * @return int
     */
    public function phlines ($file)
    {
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

    public function phcomposer($command, $taskname)
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
        require_once($this->tpath . 'vendor/autoload.php');

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

    function listen($request)
    {
        $allowed = array('status','prepare1','flarum','bazaar','cleanup','log', progress);
        if(!in_array($request,$allowed)) {
            $this->phlog('Ajax Blocked:',$request,'ajax.log');
            echo "Invalid";
        } else {
            $this->phlog('Ajax Allowed:',$request,'ajax.log');
            $this->process($request);
        }
    }

    function process($request)
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
                $this->phcomposer('require flagrow/bazaar --prefer-dist -n -o', 'bazaar');
            } elseif ($request == 'cleanup') {
                echo 'Initiated';
                $this->cleanup();
            }
        } elseif ($request == 'status') {
            echo $status;
        } elseif ($request == 'progress') {
            echo $this->composerProgress("composer.log");
        }
    }
    function cleanup() {

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
     */
    function composerProgress($file){
        $log_file = file_get_contents($this->tpath . $file);
        $result  = "<pre>" . $log_file . "</pre>";
        return $result;
    }

}

if(isset($_REQUEST['ajax']) && !empty($_REQUEST["ajax"])) {
    if ( !defined('ABSPATH') )
    {
        define('ABSPATH', dirname(__FILE__) . '/');
    }
    $tmppath = (ABSPATH . 'temp/');

    // Listen for Ajax Calls
    $ear = new Pockethold(ABSPATH, $tmppath);
    echo $ear->listen($_REQUEST['ajax']);
}
else {
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css"
              integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M"
              crossorigin="anonymous">
		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"
			  integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN"
			  crossorigin="anonymous">
        <script
                src="https://code.jquery.com/jquery-3.2.1.min.js"
                integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
                crossorigin="anonymous"></script>
		<title>Pockethold: Flarum Installer</title>
    </head>
    <body>
    <div class="container">
        <div class="jumbotron" style="background-color: transparent;">
            <div class="container text-center">

                <img style="margin: auto;" class="img-responsive" alt="Pockethold"
                     src=" data:image/png;base64,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"/>
                <p style="max-width: 460px; margin:auto;">Pockethold is a 3rd party no shell Flarum downloader.</p>

                <div id="progressdiv" style="padding-top:2em;">
                    <p style="max-width: 460px; margin:50px auto auto auto;"><button id="checkingbtn" class="instal1 btn btn-default btn-lg" role="button" disabled>Checking Status <i class="fa fa-cog fa-spin"></i></button></p>
                </div>
				<div id="progressbar" style="margin-top:10px;background-color: #eceeef;"><div id="progressbar-actual" class="progress-bar" role="progressbar" style="width: 0%;height:1px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div>
            </div>
        </div>
    </div>
    <script>
        /*
         * Requires jQuery 3
         * */

        var ajaxurl = window.location.href;
        var timer;
        var count = 0;
        var fmsg = "<h2 class='instal1'>Install failed</h2>";

        var waiting = "<button class='instal1 btn btn-default btn-lg' disabled>Working Please wait <i class='fa fa-cog fa-spin'></i></button><div id='progress'></div>";
        var prepare1 = '<span class="instal1"><span id="prepare1btn" class="instal1 btn btn-primary btn-lg" role="button">Step 1: Download Composer</span></span>';

        var flarum = '<span id="flarumbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 2: Download Flarum</span>';
        var bazaar = '<span class="instal1"><span id="bazaarbtn" class="cleanup btn btn-primary btn-lg" role="button">Step 2: Download Bazaar</span></span>';
        var cleanup = '<span id="cleanupbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 3: Start Flarum Installer</span>';

        function getProgress(url) {
            $.ajax({
                url: url,
                data: {ajax: "progress"},
                type: 'get'
            })
                .done(function (data) {
                    $("#progress").html(data);
                })

        };

        function status(url) {
            timer = setTimeout(function () {
                $.ajax({
                    url: url,
                    data: {ajax: "status"},
                    type: 'get'
                })
                    .done(function (data) {
                        $("#progressdiv").html(window[data]);
                        if (data === "waiting"){
                            getProgress(url);
                        }
                        status(url);
                    })
            }, 5000);
        };
        // Runs at startup.
        setInterval(status(ajaxurl),5000);

        //On Click Prepare unpack composer
        $(document).ready(function () {
            $(document).on("click", "#prepare1btn", function () {
                $("#progressdiv").html(waiting);
                return $.post(ajaxurl, {ajax: "prepare1"});
            })
        });
        //On Click Flarum
        $(document).ready(function () {
            $(document).on("click", "#flarumbtn", function () {
                $("#progressdiv").html(waiting);
                return $.post(ajaxurl, {ajax: "flarum"});
            })
        });
        //On Click Bazaar
        $(document).ready(function () {
            $(document).on("click", "#bazaarbtn", function () {
                $("#progressdiv").html(waiting);
                return $.post(ajaxurl, {ajax: "bazaar"});
            })
        });
        //On Click Cleanup
        $(document).ready(function () {
            $(document).on("click", "#cleanupbtn", function () {
                $("#progressdiv").html('<button class="instal1 btn btn-default btn-lg" disabled>Removing Installer<i class="fa fa-cog fa-spin"></i></button>');
                return $.post(ajaxurl, {ajax: "cleanup"})
                    .done(function() {
                        window.setTimeout(window.location.href = "./",10000);
                    });
            })
        });
    </script>
    </body>
    </html>

    <?php
}