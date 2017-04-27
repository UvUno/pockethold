<?php

/**
 * Flarum Web Composer Setup
 * Downloads composer, runs create-project flarum/flarum and remove itself.
 * Author: Andre Herberth
 * License: MIT
 * DISCLAIMER: THIS IS DIRTY. USE WITH CARE. SUGGESTIONS ARE WANTED. It was written as a
 */
ini_set("memory_limit", "256M"); // Try to increase memory to needed levels

if ( !defined('ABSPATH') ) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

$tmppath = (ABSPATH . 'temp/');

if ( !file_exists($tmppath) ) {
    mkdir($tmppath);
}

//While Loop to check for composer
// If not installed, check for .phar.
// If phar is not here. Download and run update
//echo nl2br("Checking Composer Status");
// echo str_pad('',4096)."\n";

while (!file_exists($tmppath . 'vendor/autoload.php')) {
    if ( !file_exists($tmppath . 'composer.phar') ) {
        file_put_contents($tmppath . 'composer.phar', fopen("https://getcomposer.org/composer.phar", 'r'));
    } elseif ( file_exists($tmppath . "composer.phar") ) {
        $composer = new Phar($tmppath . "composer.phar");
        $composer->extractTo($tmppath);
    }

}


/** @noinspection PhpIncludeInspection */
require_once($tmppath . 'vendor/autoload.php');

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Composer\Console\Application;
use Composer\Command\UpdateCommand;
use Composer\Command\CreateProjectCommand;
use Composer\IO\IOInterface;

//Create the application and run it with the commands
$application = new Application();
$input = new StringInput('create-project flarum/flarum --stability=beta');
$application->setAutoExit(false);
$application->run($input);
unset($input);
unset($application);

rmove(ABSPATH . 'flarum', ABSPATH);
removeinstaller($tmppath);


// **************** Own Helper Functions

/**
 * Removes installer, temp and redirects to ./ to start forum install.
 *
 * @param $temp
 */
function removeinstaller($temp)
{
    rrmdir($temp);
    unlink(__FILE__);

    header("Location: ./");
}


// **************** 3rd party Helper Functions
/**
 * Recursively delete files
 * Credits: http://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-files-sub-dir
 * @param String $dir - Source of files being moved
 */
function rrmdir($dir)
{
    if ( is_dir($dir) ) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ( $object != "." && $object != ".." ) {
                if ( is_dir($dir . "/" . $object) )
                    rrmdir($dir . "/" . $object);
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
            rmove($f->getRealPath(), "$dest/$f");
            unlink($f->getRealPath());
        }
    }
    unlink($src);
    return null;

}
