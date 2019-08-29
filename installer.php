<?php
/*
This File is part of the Pockethold 3rdparty flarum installer.
*/

// Run Installer
if(isset($_REQUEST['ajax']) && !empty($_REQUEST["ajax"])) {
    require_once("pockethold/loader.php");
    if ( !defined('ABSPATH') )
    {
        define('ABSPATH', dirname(__FILE__) . '/');
    }
    $tmppath = (ABSPATH . 'pockethold/');

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
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
		<title>Pockethold: 3rdparty Flarum Installer</title>
    </head>
    <body>
    <div class="container">
        <div class="jumbotron" style="background-color: transparent;">

          <?php
          if (!version_compare(phpversion(), '7.1.0', '>=')) {
            ?>
            <div class="container text-center">

                <img style="margin: auto;" class="img-responsive" alt="Pockethold" src="pockethold/assets/logo.png"/>
                <p style="max-width: 460px; margin:auto;">Pockethold is a 3rd party no shell Flarum downloader.</p>
                <div class="alert alert-danger" role="alert">Flarum requires PHP 7.1 or greater. Your versions is <?php echo phpversion(); ?></div>

            </div>
            <?php
          } else {
          ?>
            <div class="container text-center">

                <img style="margin: auto;" class="img-responsive" alt="Pockethold"
                     src="pockethold/assets/logo.png"/>
                <p style="max-width: 460px; margin:auto;">Pockethold is a 3rd party no shell Flarum downloader.</p>

                <div id="progressdiv" style="margin-top:50px;">
                    <p style="max-width: 460px; margin:50px auto auto auto;">
                        <button id="checkingbtn" class="instal1 btn btn-default btn-lg" role="button" disabled>Getting Status<i class="fa fa-cog fa-spin"></i></button>
                    </p>
                </div>

            </div>
          <?php } ?>
        </div>
    </div>
    <script type="text/javascript" src="pockethold/assets/pockethold.js"></script>
    </body>
    </html>

    <?php
}
?>
