<?php
$rootPath = dirname(__FILE__) . "/../../";
$confPath = $rootPath . "lib/confs/Conf.php";
$pathToAutoload = realpath(__DIR__ . '/../../src/vendor/autoload.php');

require_once $confPath;
require_once $pathToAutoload;

if ($argc > 1) {
    $password = $argv[1];
} else {
    echo "Please enter password when prompted.\n";
    $password = readline();
}


$hasher = new \OrangeHRM\Core\Utility\PasswordHash();
$hash = $hasher->hash($password);
print $hash . "\n";