<?php
/*
use Composer\Command\CreateProjectCommand;
use Composer\Command\RequireCommand;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Pockethold\pockethold;
*/

// Is it really needed?
const GITHUB_TOKEN = 'ec785da935d5535e151f7b3386190265f00e8fe2';

if ( !defined('ABSPATH') )
{
    define('ABSPATH', dirname(__FILE__) . '/');
}
$tmppath = (ABSPATH . 'pockethold/');

require_once('classes/pockethold.class.php');
require_once('classes/api.class.php');
