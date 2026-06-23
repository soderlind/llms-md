<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class LLMS_MD_Request_URIMatcher_Test extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when('home_url')->alias(static function (string $path = '/'): string {
            if ($path === '/') {
                return 'http://plugins.local/subsite14/';
            }

            return 'http://plugins.local/subsite14' . $path;
        });

        Functions\when('wp_parse_url')->alias(static function (string $url, int $component = -1) {
            if ($component === -1) {
                return parse_url($url);
            }

            return parse_url($url, $component);
        });
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_matches_subsite_llms_md_path(): void {
        $instance = new LLMS_MD_Plugin();
        $method = new ReflectionMethod($instance, 'request_uri_matches_llms_md');
        $method->setAccessible(true);

        $this->assertTrue((bool) $method->invoke($instance, '/subsite14/llms.md'));
    }

    public function test_matches_subsite_double_slash_llms_md_path(): void {
        $instance = new LLMS_MD_Plugin();
        $method = new ReflectionMethod($instance, 'request_uri_matches_llms_md');
        $method->setAccessible(true);

        $this->assertTrue((bool) $method->invoke($instance, '/subsite14//llms.md'));
    }

    public function test_does_not_match_non_llms_paths(): void {
        $instance = new LLMS_MD_Plugin();
        $method = new ReflectionMethod($instance, 'request_uri_matches_llms_md');
        $method->setAccessible(true);

        $this->assertFalse((bool) $method->invoke($instance, '/subsite14/other.md'));
    }
}
