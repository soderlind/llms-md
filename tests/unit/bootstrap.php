<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}

if (!defined('LLMSMD_DISABLE_BOOTSTRAP')) {
    define('LLMSMD_DISABLE_BOOTSTRAP', true);
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook_name, $value, ...$args) {
        unset($hook_name, $args);
        return $value;
    }
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/llms-md.php';
