<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');
}

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $vendor_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
    if (file_exists($vendor_tests_dir . '/includes/functions.php')) {
        $_tests_dir = $vendor_tests_dir;
    } else {
        $_tests_dir = '/tmp/wordpress-tests-lib';
    }
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, "Could not find WordPress test suite in WP_TESTS_DIR.\n");
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__) . '/llms-md.php';
});

require $_tests_dir . '/includes/bootstrap.php';
