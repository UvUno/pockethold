<?php
//Static Class class loader.
use Composer\Command\CreateProjectCommand;
use Composer\Command\RequireCommand;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

const GITHUB_TOKEN = 'ec785da935d5535e151f7b3386190265f00e8fe2';

require_once('classes/pockethold.class.php');
