<?php

declare(strict_types=1);

final class LLMSMD_Plugin_Test extends WP_UnitTestCase {
    public function test_rewrite_rule_registered_for_llms_md(): void {
        do_action('init');

        global $wp_rewrite;
        $rules = $wp_rewrite->wp_rewrite_rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('llms\\.md$', $rules);
        $this->assertSame('index.php?llmsmd=1', $rules['llms\\.md$']);
    }

    public function test_query_var_is_registered(): void {
        do_action('init');

        $query_vars = apply_filters('query_vars', []);
        $this->assertContains('llmsmd', $query_vars, 'Expected llmsmd query var to be registered.');
    }

    public function test_connector_gate_filter_can_force_missing_connector_state(): void {
        add_filter('llmsmd_ai_connector_configured', '__return_false');

        $plugin = new ReflectionClass('LLMSMD_Plugin');
        $method = $plugin->getMethod('is_connector_configured');
        $method->setAccessible(true);

        $instance_property = $plugin->getProperty('instance');
        $instance_property->setAccessible(true);
        $instance = $instance_property->getValue();

        $this->assertFalse($method->invoke($instance));

        remove_filter('llmsmd_ai_connector_configured', '__return_false');
    }

    public function test_preview_payload_admin_action_updates_option_and_redirects(): void {
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $captured_redirect = '';
        add_filter('llmsmd_exit_after_redirect', '__return_false');
        $redirect_filter = static function (string $location) use (&$captured_redirect): string {
            $captured_redirect = $location;
            return $location;
        };
        add_filter('wp_redirect', $redirect_filter);

        $_REQUEST['_wpnonce'] = wp_create_nonce('llmsmd_preview_payload');

        do_action('admin_post_llmsmd_preview_payload');

        $preview = get_option('llmsmd_admin_payload_preview', '');
        $this->assertIsString($preview);
        $this->assertNotSame('', $preview);

        $preview_decoded = json_decode($preview, true);
        $this->assertIsArray($preview_decoded);
        $this->assertArrayHasKey('items_count', $preview_decoded);
        $this->assertStringContainsString('llmsmd_notice=preview_ready', $captured_redirect);

        unset($_REQUEST['_wpnonce']);
        remove_filter('llmsmd_exit_after_redirect', '__return_false');
        remove_filter('wp_redirect', $redirect_filter);
    }

    public function test_request_uri_matcher_accepts_subdirectory_multisite_double_slash_path(): void {
        $plugin_ref = new ReflectionClass('LLMSMD_Plugin');
        $method = $plugin_ref->getMethod('request_uri_matches_llms_md');
        $method->setAccessible(true);

        $instance_property = $plugin_ref->getProperty('instance');
        $instance_property->setAccessible(true);
        $instance = $instance_property->getValue();

        $home_url_filter = static function (string $url, string $path): string {
            if ($path === '/') {
                return 'http://plugins.local/subsite14/';
            }

            return $url;
        };

        add_filter('home_url', $home_url_filter, 10, 2);

        $this->assertTrue((bool) $method->invoke($instance, '/subsite14//llms.md'));
        $this->assertTrue((bool) $method->invoke($instance, '/subsite14/llms.md'));
        $this->assertFalse((bool) $method->invoke($instance, '/subsite14/other.md'));

        remove_filter('home_url', $home_url_filter, 10);
    }

    public function test_parse_request_early_hook_is_registered(): void {
        $plugin_ref = new ReflectionClass('LLMSMD_Plugin');
        $instance_property = $plugin_ref->getProperty('instance');
        $instance_property->setAccessible(true);
        $instance = $instance_property->getValue();

        $this->assertNotFalse(
            has_action('parse_request', [$instance, 'maybe_serve_llms_md_early'])
        );
    }
}
