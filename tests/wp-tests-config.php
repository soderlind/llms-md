<?php

declare(strict_types=1);

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'wordpress_tests');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'root');
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', getenv('WP_TESTS_DB_PASSWORD') ?: '');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: '127.0.0.1');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8');
}

if (!defined('DB_COLLATE')) {
    define('DB_COLLATE', '');
}

if (!defined('WP_TESTS_DOMAIN')) {
    define('WP_TESTS_DOMAIN', getenv('WP_TESTS_DOMAIN') ?: 'example.org');
}

if (!defined('WP_TESTS_EMAIL')) {
    define('WP_TESTS_EMAIL', getenv('WP_TESTS_EMAIL') ?: 'admin@example.org');
}

if (!defined('WP_TESTS_TITLE')) {
    define('WP_TESTS_TITLE', getenv('WP_TESTS_TITLE') ?: 'Test Blog');
}

if (!defined('WP_PHP_BINARY')) {
    define('WP_PHP_BINARY', getenv('WP_PHP_BINARY') ?: PHP_BINARY);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

$table_prefix = 'wptests_';

$wp_root = getenv('WP_TESTS_WP_PATH');
if (!$wp_root) {
    $wp_root = dirname(__DIR__, 4);
}

define('ABSPATH', rtrim((string) $wp_root, '/\\') . '/');
