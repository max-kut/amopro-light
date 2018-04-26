<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 17.03.18
 * Time: 0:40
 */
ini_set('phar.readonly', 0);

$phar = new \Phar('AmoPRO.phar',
    \FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
    'amopro.phar');
$phar->setDefaultStub('autoloader.php');
$phar->buildFromDirectory(__DIR__ . DIRECTORY_SEPARATOR.'src');