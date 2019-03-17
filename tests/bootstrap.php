<?php
/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */

use Cake\Core\Configure;

require dirname(__DIR__) . '/vendor/autoload.php';
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception("Cannot find the root of the application, unable to run tests");
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);
if (file_exists($root . '/config/bootstrap.php')) {
    require $root . '/config/bootstrap.php';
    return;
}

require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';
