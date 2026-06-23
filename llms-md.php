<?php
/**
 * Plugin Name:       llms.md
 * Description:       Serves /llms.md from cached AI-generated site analysis.
 * Version:           0.3.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Plugin URI:        https://github.com/soderlind/llms-md
 * Author:            Per Søderlind
 * Author URI:        https://soederlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       llms-md
 */

if (!defined('ABSPATH')) {
    exit;
}

$llms_md_autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($llms_md_autoload)) {
    require_once $llms_md_autoload;
}

$llms_md_as = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
if (is_readable($llms_md_as)) {
    require_once $llms_md_as;
}

if ((!defined('LLMS_MD_DISABLE_BOOTSTRAP') || !LLMS_MD_DISABLE_BOOTSTRAP) && class_exists('\Soderlind\WordPress\GitHubUpdater')) {
    \Soderlind\WordPress\GitHubUpdater::init(
        github_url: 'https://github.com/soderlind/llms-md',
        plugin_file: __FILE__,
        plugin_slug: 'llms-md',
        name_regex: '/llms-md\\.zip/',
        branch: 'main',
        check_period: 6,
        auth_token: (string) apply_filters(
            'llms_md_github_auth_token',
            defined('LLMS_MD_GITHUB_TOKEN') ? (string) LLMS_MD_GITHUB_TOKEN : ''
        ),
    );
}

final class LLMS_MD_Plugin {
    private const VERSION = '0.3.0';
    private const QUERY_VAR = 'llms_md';
    private const REWRITE_RULE = '^llms\\.md$';

    private const OPTION_SNAPSHOT = 'llms_md_snapshot';
    private const OPTION_STATE = 'llms_md_state';
    private const OPTION_LOCK = 'llms_md_regeneration_lock';
    private const OPTION_PENDING = 'llms_md_regeneration_pending';
    private const OPTION_ADMIN_PREVIEW = 'llms_md_admin_payload_preview';

    private const CRON_DAILY = 'llms_md_daily_rebuild';
    private const CRON_RUN = 'llms_md_run_regeneration';
    private const AS_GROUP = 'llms-md';

    private const SUCCESS_MAX_AGE = 300;
    private const STALE_FAILURE_MAX_AGE = 604800; // 7 days
    private const RETRY_AFTER = 3600;
    private const MIN_SUCCESS_INTERVAL = 300;

    private const PROMPT_VERSION = 'v1';
    private const DEFAULT_MODEL_ID = 'wp-core-ai-default';

    private static ?LLMS_MD_Plugin $instance = null;

    public static function init(): void {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self();
        self::$instance->register_hooks();
    }

    public static function activate(): void {
        $instance = self::$instance instanceof self ? self::$instance : new self();
        $instance->register_rewrite();
        flush_rewrite_rules();
        $instance->schedule_daily_rebuild();
    }

    public static function deactivate(): void {
        $instance = self::$instance instanceof self ? self::$instance : new self();
        $instance->unschedule_all(self::CRON_DAILY);
        $instance->unschedule_all(self::CRON_RUN);
        flush_rewrite_rules();
    }

    private function register_hooks(): void {
        add_action('init', [$this, 'register_rewrite']);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('send_headers', [$this, 'add_discovery_link_header']);
        add_action('parse_request', [$this, 'maybe_serve_llms_md_early'], 0);
        add_action('template_redirect', [$this, 'maybe_serve_llms_md']);

        add_action('save_post', [$this, 'on_content_changed'], 10, 3);
        add_action('deleted_post', [$this, 'on_post_deleted']);
        add_action('set_object_terms', [$this, 'on_term_relationship_changed'], 10, 6);

        add_action(self::CRON_DAILY, [$this, 'daily_regeneration']);
        add_action(self::CRON_RUN, [$this, 'run_regeneration'], 10, 1);

        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_post_llms_md_manual_regenerate', [$this, 'handle_manual_regenerate']);
        add_action('admin_post_llms_md_check_connector', [$this, 'handle_check_connector']);
        add_action('admin_post_llms_md_preview_payload', [$this, 'handle_preview_payload']);
        add_action('admin_notices', [$this, 'maybe_show_admin_notices']);
        add_action('wp_ajax_llms_md_poll_status', [$this, 'handle_poll_status']);

        if (!$this->has_any_scheduled(self::CRON_DAILY)) {
            $this->schedule_daily_rebuild();
        }
    }

    public function register_rewrite(): void {
        add_rewrite_tag('%' . self::QUERY_VAR . '%', '1');
        add_rewrite_rule(self::REWRITE_RULE, 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    public function query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function maybe_serve_llms_md(): void {
        if (!$this->is_llms_md_request()) {
            return;
        }

        $this->serve_llms_md_request();
    }

    public function maybe_serve_llms_md_early(): void {
        if (!$this->request_uri_matches_llms_md()) {
            return;
        }

        $this->serve_llms_md_request();
    }

    private function serve_llms_md_request(): void {

        $physical_file = $this->find_physical_llms_file();
        if ($physical_file !== null) {
            $this->serve_physical_file($physical_file);
        }

        if (!$this->is_connector_configured()) {
            $this->update_state([
                'last_status' => 'missing_connector',
                'last_attempt_at' => time(),
                'last_error' => 'No configured WP Core AI connector found.',
                'last_error_at' => time(),
            ]);

            $this->respond_503('llms.md unavailable: missing AI connector configuration.');
        }

        $snapshot = $this->get_snapshot();
        if ($snapshot === null) {
            $this->schedule_regeneration('request_without_snapshot');
            $this->respond_503('llms.md unavailable: first snapshot not generated yet.');
        }

        if ($this->must_fail_due_to_stale_failure($snapshot)) {
            $this->respond_503('llms.md unavailable: cached snapshot exceeded staleness budget.');
        }

        $this->serve_snapshot($snapshot);
    }

    public function add_discovery_link_header(): void {
        $this->emit_discovery_link_header();
    }

    private function emit_discovery_link_header(): void {
        $llms_url = home_url('/llms.md');
        if (!is_string($llms_url) || $llms_url === '') {
            return;
        }

        header('Link: <' . $llms_url . '>; rel="alternate"; type="text/markdown"', false);
    }

    private function is_llms_md_request(): bool {
        if ((string) get_query_var(self::QUERY_VAR) === '1') {
            return true;
        }

        return $this->request_uri_matches_llms_md();
    }

    private function request_uri_matches_llms_md(?string $request_uri = null): bool {
        $uri = $request_uri;
        if (!is_string($uri) || $uri === '') {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        }

        if ($uri === '') {
            return false;
        }

        $request_path = (string) wp_parse_url($uri, PHP_URL_PATH);
        if ($request_path === '') {
            return false;
        }

        $site_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        $normalized_request_path = $this->normalize_path($request_path);
        $normalized_site_path = $this->normalize_path($site_path);
        $target_path = ($normalized_site_path === '/' ? '' : $normalized_site_path) . '/llms.md';

        return $normalized_request_path === $target_path;
    }

    private function normalize_path(string $path): string {
        $normalized = '/' . ltrim($path, '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    public function on_content_changed(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (wp_is_post_autosave($post_id)) {
            return;
        }

        if (!$this->is_public_post_type($post->post_type)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $this->schedule_regeneration($update ? 'post_update' : 'post_publish');
    }

    public function on_post_deleted(int $post_id): void {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        if (!$this->is_public_post_type($post->post_type)) {
            return;
        }

        $this->schedule_regeneration('post_delete');
    }

    public function on_term_relationship_changed(
        int $object_id,
        array $terms,
        array $tt_ids,
        string $taxonomy,
        bool $append,
        array $old_tt_ids
    ): void {
        unset($terms, $tt_ids, $append, $old_tt_ids);

        if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
            return;
        }

        $post = get_post($object_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        if (!$this->is_public_post_type($post->post_type)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $this->schedule_regeneration('term_relationship_change');
    }

    public function daily_regeneration(): void {
        $this->schedule_regeneration('daily_safety_rebuild', true);
    }

    public function run_regeneration(string $reason = 'scheduled'): void {
        if (!$this->acquire_lock()) {
            $this->set_pending(true);
            return;
        }

        try {
            if (!$this->is_connector_configured()) {
                $this->update_state([
                    'last_status' => 'missing_connector',
                    'last_attempt_at' => time(),
                    'last_error' => 'No configured WP Core AI connector found.',
                    'last_error_at' => time(),
                ]);
                return;
            }

            $payload = $this->collect_payload();
            $generation = $this->generate_document($payload, $reason);

            if (!$generation['ok']) {
                $this->update_state([
                    'last_status' => 'failure',
                    'last_attempt_at' => time(),
                    'last_error' => $generation['error'],
                    'last_error_at' => time(),
                ]);
                return;
            }

            $generated_at = time();
            $content = trim((string) $generation['content']) . "\n";
            $etag = sha1($content);
            $snapshot = [
                'content' => $content,
                'generated_at' => $generated_at,
                'etag' => $etag,
                'last_modified_gmt' => gmdate('D, d M Y H:i:s', $generated_at) . ' GMT',
                'model_id' => $generation['model_id'],
                'prompt_version' => $generation['prompt_version'],
                'source_hash' => $payload['source_hash'],
            ];

            update_option(self::OPTION_SNAPSHOT, $snapshot, false);
            $this->update_state([
                'last_status' => 'success',
                'last_attempt_at' => $generated_at,
                'last_error' => '',
                'last_error_at' => 0,
            ]);
        } finally {
            $this->release_lock();
            $this->maybe_schedule_coalesced_run();
        }
    }

    public function register_admin_page(): void {
        add_options_page(
            'llms.md',
            'llms.md',
            'manage_options',
            'llms-md',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $state = $this->get_state();
        $snapshot = $this->get_snapshot();
        $has_connector = $this->is_connector_configured();
        $has_physical_file = $this->find_physical_llms_file() !== null;

        echo '<div class="wrap">';
        echo '<h1>llms.md</h1>';
        echo '<p>Status overview for /llms.md generation and serving.</p>';

        echo '<table class="widefat striped" style="max-width: 900px">';
        echo '<tbody>';
        $this->render_admin_row('Plugin version', esc_html(self::VERSION));
        $this->render_admin_row('Connector configured', $has_connector ? 'Yes' : 'No');
        $this->render_admin_row('Physical /llms.md detected', $has_physical_file ? 'Yes (passive mode)' : 'No');
        $this->render_admin_row('Last status', esc_html((string) ($state['last_status'] ?? 'unknown')));
        $this->render_admin_row('Last attempt', $this->format_timestamp((int) ($state['last_attempt_at'] ?? 0)));
        $this->render_admin_row('Last error', esc_html((string) ($state['last_error'] ?? '')));
        $this->render_admin_row('Snapshot generated', $this->format_timestamp((int) ($snapshot['generated_at'] ?? 0)));
        $this->render_admin_row('Snapshot model', esc_html((string) ($snapshot['model_id'] ?? 'n/a')));
        $this->render_admin_row('Prompt version', esc_html((string) ($snapshot['prompt_version'] ?? 'n/a')));
        echo '</tbody>';
        echo '</table>';

        echo '<form id="llms-md-regen-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top: 16px">';
        wp_nonce_field('llms_md_manual_regenerate');
        echo '<input type="hidden" name="action" value="llms_md_manual_regenerate" />';
        submit_button('Regenerate llms.md', 'primary', 'submit', false);
        echo '</form>';
        ?>
        <div id="llms-md-progress-wrap" style="display:none;margin-top:12px;max-width:900px">
            <p id="llms-md-progress-label" style="margin-bottom:6px">Regenerating llms.md&hellip;</p>
            <div style="background:#ddd;border-radius:3px;height:20px;overflow:hidden;position:relative">
                <div id="llms-md-progress-bar" style="
                    position:absolute;height:100%;width:40%;
                    background:#2271b1;border-radius:3px;
                    animation:llms-md-slide 1.4s ease-in-out infinite;
                "></div>
            </div>
        </div>
        <style>
        @keyframes llms-md-slide {
            0%   { left:-40%; }
            100% { left:100%; }
        }
        </style>
        <script>
        document.getElementById('llms-md-regen-form').addEventListener('submit', function () {
            document.getElementById('llms-md-progress-wrap').style.display = 'block';
            this.querySelector('[type=submit]').disabled = true;
        });
        </script>
        <?php

        echo '<hr style="margin: 24px 0" />';
        echo '<h2>Diagnostics</h2>';
        echo '<p>Run connector checks and preview the bounded analysis payload used for generation.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-block; margin-right: 8px">';
        wp_nonce_field('llms_md_check_connector');
        echo '<input type="hidden" name="action" value="llms_md_check_connector" />';
        submit_button('Check Connector', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-block">';
        wp_nonce_field('llms_md_preview_payload');
        echo '<input type="hidden" name="action" value="llms_md_preview_payload" />';
        submit_button('Preview Payload', 'secondary', 'submit', false);
        echo '</form>';

        $preview = get_option(self::OPTION_ADMIN_PREVIEW, '');
        if (is_string($preview) && $preview !== '') {
            echo '<h3 style="margin-top: 16px">Last Payload Preview</h3>';
            echo '<textarea readonly rows="16" style="width: 100%; font-family: monospace">' . esc_textarea($preview) . '</textarea>';
        }

        echo '</div>';
    }

    public function handle_manual_regenerate(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', 'Forbidden', ['response' => 403]);
        }

        check_admin_referer('llms_md_manual_regenerate');

        $this->run_regeneration('manual_regenerate');

        $state = $this->get_state();
        $notice = ($state['last_status'] ?? '') === 'success' ? 'regenerated' : 'regen_failed';
        $this->admin_redirect_with_notice($notice);
    }

    public function handle_check_connector(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', 'Forbidden', ['response' => 403]);
        }

        check_admin_referer('llms_md_check_connector');

        $is_configured = $this->is_connector_configured();
        $provider_ids = $this->get_configured_ai_provider_ids();

        if ($is_configured) {
            $notice_value = 'connector_ok';
            if (!empty($provider_ids)) {
                $notice_value .= ':' . implode(',', $provider_ids);
            }
        } else {
            $notice_value = 'connector_missing';
        }

        $this->admin_redirect_with_notice($notice_value);
    }

    public function handle_preview_payload(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', 'Forbidden', ['response' => 403]);
        }

        check_admin_referer('llms_md_preview_payload');

        $payload = $this->collect_payload();
        $preview = [
            'site' => $payload['site'] ?? [],
            'policy' => $payload['policy'] ?? [],
            'items_count' => is_array($payload['items'] ?? null) ? count($payload['items']) : 0,
            'items_sample' => is_array($payload['items'] ?? null) ? array_slice($payload['items'], 0, 5) : [],
            'generated_at_gmt' => $payload['generated_at_gmt'] ?? '',
            'source_hash' => $payload['source_hash'] ?? '',
        ];

        update_option(
            self::OPTION_ADMIN_PREVIEW,
            (string) wp_json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            false
        );

        $this->admin_redirect_with_notice('preview_ready');
    }

    public function handle_poll_status(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.', 403);
        }

        check_ajax_referer('llms_md_poll_status');

        $state = $this->get_state();
        wp_send_json_success([
            'locked'          => $this->is_locked(),
            'last_status'     => (string) ($state['last_status'] ?? ''),
            'last_attempt_at' => (int) ($state['last_attempt_at'] ?? 0),
            'last_error'      => (string) ($state['last_error'] ?? ''),
        ]);
    }

    private function admin_redirect_with_notice(string $notice): void {
        wp_safe_redirect(add_query_arg(['page' => 'llms-md', 'llms_md_notice' => $notice], admin_url('options-general.php')));

        $should_exit = (bool) apply_filters('llms_md_exit_after_redirect', true);
        if ($should_exit) {
            exit;
        }
    }

    public function maybe_show_admin_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['page'], $_GET['llms_md_notice']) && $_GET['page'] === 'llms-md' && $_GET['llms_md_notice'] === 'regenerated') {
            echo '<div class="notice notice-success is-dismissible"><p>llms.md regenerated successfully.</p></div>';
        }

        if (isset($_GET['page'], $_GET['llms_md_notice']) && $_GET['page'] === 'llms-md' && $_GET['llms_md_notice'] === 'regen_failed') {
            $state = $this->get_state();
            $error = esc_html((string) ($state['last_error'] ?? ''));
            $msg   = $error !== '' ? 'llms.md regeneration failed: ' . $error : 'llms.md regeneration failed.';
            echo '<div class="notice notice-error is-dismissible"><p>' . $msg . '</p></div>';
        }

        if (isset($_GET['page'], $_GET['llms_md_notice']) && $_GET['page'] === 'llms-md' && $_GET['llms_md_notice'] === 'scheduled') {
            echo '<div class="notice notice-success is-dismissible"><p>llms.md regeneration scheduled.</p></div>';
        }

        if (isset($_GET['page'], $_GET['llms_md_notice']) && $_GET['page'] === 'llms-md' && $_GET['llms_md_notice'] === 'preview_ready') {
            echo '<div class="notice notice-success is-dismissible"><p>Payload preview generated.</p></div>';
        }

        if (isset($_GET['page'], $_GET['llms_md_notice']) && $_GET['page'] === 'llms-md' && str_starts_with((string) $_GET['llms_md_notice'], 'connector_ok')) {
            $provider_ids = '';
            $parts = explode(':', (string) $_GET['llms_md_notice'], 2);
            if (count($parts) === 2) {
                $provider_ids = sanitize_text_field($parts[1]);
            }

            $suffix = $provider_ids !== '' ? ' Configured providers: ' . $provider_ids : '';
            echo '<div class="notice notice-success is-dismissible"><p>Connector is configured.' . esc_html($suffix) . '</p></div>';
        }

        if (isset($_GET['page'], $_GET['llms_md_notice']) && $_GET['page'] === 'llms-md' && $_GET['llms_md_notice'] === 'connector_missing') {
            echo '<div class="notice notice-error is-dismissible"><p>No configured AI provider connector detected.</p></div>';
        }

        if ($this->find_physical_llms_file() !== null) {
            echo '<div class="notice notice-warning"><p>llms.md plugin is in passive mode because a physical /llms.md file exists at web root.</p></div>';
        }

        if (!$this->is_connector_configured()) {
            echo '<div class="notice notice-error"><p>llms.md requires a configured WP Core AI connector.</p></div>';
        }
    }

    private function render_admin_row(string $label, string $value): void {
        echo '<tr>';
        echo '<th style="width: 240px">' . esc_html($label) . '</th>';
        echo '<td>' . esc_html($value) . '</td>';
        echo '</tr>';
    }

    private function schedule_daily_rebuild(): void {
        if ($this->has_any_scheduled(self::CRON_DAILY)) {
            return;
        }

        if (function_exists('as_schedule_recurring_action')) {
            as_schedule_recurring_action(time() + 60, DAY_IN_SECONDS, self::CRON_DAILY, [], self::AS_GROUP);
        } else {
            wp_schedule_event(time() + 60, 'daily', self::CRON_DAILY);
        }
    }

    private function schedule_regeneration(string $reason, bool $force = false): void {
        if (!$force && $this->is_recent_success()) {
            $this->set_pending(true);
            $this->schedule_coalesced_for_min_interval();
            return;
        }

        if ($this->is_locked()) {
            $this->set_pending(true);
            return;
        }

        if (!$this->has_any_run_scheduled()) {
            $delay = $force ? 1 : 10;
            $this->schedule_single(time() + $delay, self::CRON_RUN, [sanitize_key($reason)]);
        }
    }

    private function schedule_coalesced_for_min_interval(): void {
        if ($this->has_any_run_scheduled()) {
            return;
        }

        $snapshot = $this->get_snapshot();
        $last_success_at = (int) ($snapshot['generated_at'] ?? 0);
        $next_allowed = $last_success_at + self::MIN_SUCCESS_INTERVAL + 1;
        if ($next_allowed <= time()) {
            $next_allowed = time() + 10;
        }

        $this->schedule_single($next_allowed, self::CRON_RUN, ['coalesced']);
    }

    private function maybe_schedule_coalesced_run(): void {
        if (!$this->is_pending()) {
            return;
        }

        $this->set_pending(false);

        if ($this->is_recent_success()) {
            $this->schedule_coalesced_for_min_interval();
            return;
        }

        if (!$this->has_any_run_scheduled()) {
            $this->schedule_single(time() + 10, self::CRON_RUN, ['coalesced']);
        }
    }

    private function has_any_run_scheduled(): bool {
        return $this->has_any_scheduled(self::CRON_RUN);
    }

    private function has_any_scheduled(string $hook): bool {
        if (function_exists('as_has_scheduled_action')) {
            return as_has_scheduled_action($hook, null, self::AS_GROUP);
        }

        return (bool) wp_next_scheduled($hook);
    }

    private function schedule_single(int $timestamp, string $hook, array $args = []): void {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, $hook, $args, self::AS_GROUP);
        } else {
            wp_schedule_single_event($timestamp, $hook, $args);
        }
    }

    private function unschedule_all(string $hook): void {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions($hook, [], self::AS_GROUP);
        } else {
            wp_clear_scheduled_hook($hook);
        }
    }

    private function is_recent_success(): bool {
        $snapshot = $this->get_snapshot();
        if ($snapshot === null) {
            return false;
        }

        $state = $this->get_state();
        if (($state['last_status'] ?? '') !== 'success') {
            return false;
        }

        $last_success_at = (int) ($snapshot['generated_at'] ?? 0);
        if ($last_success_at <= 0) {
            return false;
        }

        return (time() - $last_success_at) < self::MIN_SUCCESS_INTERVAL;
    }

    private function acquire_lock(): bool {
        return add_option(self::OPTION_LOCK, time(), '', false);
    }

    private function release_lock(): void {
        delete_option(self::OPTION_LOCK);
    }

    private function is_locked(): bool {
        return get_option(self::OPTION_LOCK, false) !== false;
    }

    private function set_pending(bool $value): void {
        update_option(self::OPTION_PENDING, $value ? 1 : 0, false);
    }

    private function is_pending(): bool {
        return (int) get_option(self::OPTION_PENDING, 0) === 1;
    }

    private function get_snapshot(): ?array {
        $snapshot = get_option(self::OPTION_SNAPSHOT, null);
        return is_array($snapshot) ? $snapshot : null;
    }

    private function get_state(): array {
        $state = get_option(self::OPTION_STATE, []);
        return is_array($state) ? $state : [];
    }

    private function update_state(array $patch): void {
        $state = $this->get_state();
        update_option(self::OPTION_STATE, array_merge($state, $patch), false);
    }

    private function must_fail_due_to_stale_failure(array $snapshot): bool {
        $state = $this->get_state();
        if (($state['last_status'] ?? '') !== 'failure') {
            return false;
        }

        $generated_at = (int) ($snapshot['generated_at'] ?? 0);
        if ($generated_at <= 0) {
            return true;
        }

        return (time() - $generated_at) > self::STALE_FAILURE_MAX_AGE;
    }

    private function serve_snapshot(array $snapshot): void {
        $this->emit_discovery_link_header();

        $content = (string) ($snapshot['content'] ?? '');
        $etag = (string) ($snapshot['etag'] ?? sha1($content));
        $generated_at = (int) ($snapshot['generated_at'] ?? time());
        $last_modified = (string) ($snapshot['last_modified_gmt'] ?? gmdate('D, d M Y H:i:s', $generated_at) . ' GMT');

        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
        if ($if_none_match !== '' && trim($if_none_match, '"') === $etag) {
            status_header(304);
            exit;
        }

        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
        if ($if_modified_since !== false && $if_modified_since >= $generated_at) {
            status_header(304);
            exit;
        }

        status_header(200);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=' . self::SUCCESS_MAX_AGE);
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . $last_modified);
        header('X-LLMS-MD-Generated-At: ' . gmdate('c', $generated_at));

        echo $content;
        exit;
    }

    private function respond_503(string $message): void {
        $this->emit_discovery_link_header();

        status_header(503);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Retry-After: ' . self::RETRY_AFTER);

        echo "# Service Unavailable\n\n";
        echo $message . "\n";
        exit;
    }

    private function collect_payload(): array {
        $post_types = array_values(array_filter(
            get_post_types(['public' => true], 'names'),
            static fn(string $post_type): bool => $post_type !== 'attachment'
        ));

        $query = new WP_Query([
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'has_password' => false,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'fields' => 'ids',
        ]);

        $items = [];
        foreach ($query->posts as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post instanceof WP_Post) {
                continue;
            }

            $clean_content = $this->clean_content((string) $post->post_content);
            $items[] = [
                'id' => (int) $post->ID,
                'type' => (string) $post->post_type,
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'excerpt' => $this->build_excerpt($post, $clean_content),
                'headings' => $this->extract_headings((string) $post->post_content),
                'content_sample' => $this->limit_chars($clean_content, 2000),
                'categories' => $this->get_terms_for_post($post->ID, 'category'),
                'tags' => $this->get_terms_for_post($post->ID, 'post_tag'),
                'featured_image_alt' => $this->get_featured_image_alt($post->ID),
                'modified_gmt' => (string) $post->post_modified_gmt,
            ];
        }

        wp_reset_postdata();

        $payload = [
            'site' => [
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'home_url' => home_url('/'),
                'language' => get_bloginfo('language'),
            ],
            'policy' => [
                'prompt_version' => self::PROMPT_VERSION,
                'model_id' => (string) apply_filters('llms_md_model_id', self::DEFAULT_MODEL_ID),
                'determinism' => 'low_variance',
            ],
            'items' => $items,
            'generated_at_gmt' => gmdate('c'),
        ];

        $payload['source_hash'] = sha1((string) wp_json_encode($payload));

        return $payload;
    }

    private function generate_document(array $payload, string $reason): array {
        $context = [
            'reason' => $reason,
            'prompt_version' => self::PROMPT_VERSION,
            'model_id' => (string) apply_filters('llms_md_model_id', self::DEFAULT_MODEL_ID),
            'provider_id' => $this->resolve_provider_id(),
        ];

        $filtered = apply_filters('llms_md_generate_document', null, $payload, $context);
        if (is_string($filtered) && trim($filtered) !== '') {
            return [
                'ok' => true,
                'content' => $filtered,
                'model_id' => $context['model_id'],
                'prompt_version' => $context['prompt_version'],
                'error' => '',
            ];
        }

        $fallback = $this->try_generate_via_core_ai($payload, $context);
        if ($fallback['ok']) {
            return $fallback;
        }

        return [
            'ok' => false,
            'content' => '',
            'model_id' => $context['model_id'],
            'prompt_version' => $context['prompt_version'],
            'error' => 'Generation failed. Add a llms_md_generate_document filter that uses your WP Core AI connector.',
        ];
    }

    private function try_generate_via_core_ai(array $payload, array $context): array {
        $prompt = $this->build_prompt($payload);

        if (!function_exists('wp_ai_client_prompt')) {
            return [
                'ok' => false,
                'content' => '',
                'model_id' => $context['model_id'],
                'prompt_version' => $context['prompt_version'],
                'error' => 'wp_ai_client_prompt is not available.',
            ];
        }

        try {
            $builder = wp_ai_client_prompt($prompt);

            if (method_exists($builder, 'using_temperature')) {
                $builder->using_temperature(0.0);
            }

            if (method_exists($builder, 'using_candidate_count')) {
                $builder->using_candidate_count(1);
            }

            if (!empty($context['provider_id']) && method_exists($builder, 'using_provider')) {
                $builder->using_provider((string) $context['provider_id']);
            }

            $result = $builder->generate_text();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'content' => '',
                'model_id' => $context['model_id'],
                'prompt_version' => $context['prompt_version'],
                'error' => $e->getMessage(),
            ];
        }

        if (is_wp_error($result)) {
            return [
                'ok' => false,
                'content' => '',
                'model_id' => $context['model_id'],
                'prompt_version' => $context['prompt_version'],
                'error' => $result->get_error_message(),
            ];
        }

        if (is_string($result) && trim($result) !== '') {
            return [
                'ok' => true,
                'content' => $result,
                'model_id' => $context['model_id'],
                'prompt_version' => $context['prompt_version'],
                'error' => '',
            ];
        }

        if (is_array($result)) {
            $candidate = $result['text'] ?? $result['content'] ?? '';
            if (is_string($candidate) && trim($candidate) !== '') {
                return [
                    'ok' => true,
                    'content' => $candidate,
                    'model_id' => $context['model_id'],
                    'prompt_version' => $context['prompt_version'],
                    'error' => '',
                ];
            }
        }

        return [
            'ok' => false,
            'content' => '',
            'model_id' => $context['model_id'],
            'prompt_version' => $context['prompt_version'],
            'error' => 'wp_ai_client_prompt()->generate_text() returned empty output.',
        ];
    }

    private function build_prompt(array $payload): string {
        $json = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return "You are generating an llms.md file for a WordPress site. "
            . "Write concise, structured Markdown that helps LLM agents understand the site. "
            . "Include sections for site overview, key topics, content map, and notable resources. "
            . "Do not include sensitive or private content. "
            . "Use only the provided JSON payload.\n\n"
            . "Payload:\n"
            . $json;
    }

    private function is_connector_configured(): bool {
        $filtered = apply_filters('llms_md_ai_connector_configured', null);
        if (is_bool($filtered)) {
            return $filtered;
        }

        return count($this->get_configured_ai_provider_ids()) > 0;
    }

    private function resolve_provider_id(): string {
        $provider = (string) apply_filters('llms_md_provider_id', '');
        if ($provider !== '') {
            return $provider;
        }

        $model_id = (string) apply_filters('llms_md_model_id', self::DEFAULT_MODEL_ID);
        if ($model_id !== '' && $model_id !== self::DEFAULT_MODEL_ID && function_exists('wp_is_connector_registered') && wp_is_connector_registered($model_id)) {
            return $model_id;
        }

        $providers = $this->get_configured_ai_provider_ids();
        if (!empty($providers)) {
            return (string) $providers[0];
        }

        return '';
    }

    private function get_configured_ai_provider_ids(): array {
        if (function_exists('wp_supports_ai') && !wp_supports_ai()) {
            return [];
        }

        if (!function_exists('wp_get_connectors')) {
            return [];
        }

        try {
            $connectors = wp_get_connectors();
        } catch (Throwable $e) {
            return [];
        }

        $provider_ids = [];

        foreach ($connectors as $connector_id => $connector) {
            if (!is_array($connector)) {
                continue;
            }

            if (($connector['type'] ?? '') !== 'ai_provider') {
                continue;
            }

            $auth = $connector['authentication'] ?? [];
            if (!is_array($auth)) {
                continue;
            }

            if (($auth['method'] ?? '') === 'none') {
                $provider_ids[] = (string) $connector_id;
                continue;
            }

            if (($auth['method'] ?? '') !== 'api_key') {
                continue;
            }

            $setting_name = (string) ($auth['setting_name'] ?? '');
            if ($setting_name === '') {
                continue;
            }

            $env_var_name = (string) ($auth['env_var_name'] ?? '');
            $constant_name = (string) ($auth['constant_name'] ?? '');

            if (function_exists('_wp_connectors_get_api_key_source')) {
                $source = _wp_connectors_get_api_key_source($setting_name, $env_var_name, $constant_name);
                if ($source !== 'none') {
                    $provider_ids[] = (string) $connector_id;
                }
                continue;
            }

            $db_value = get_option($setting_name, '');
            if (is_string($db_value) && $db_value !== '') {
                $provider_ids[] = (string) $connector_id;
            }
        }

        return array_values(array_unique(array_filter($provider_ids, static fn(string $id): bool => $id !== '')));
    }

    private function find_physical_llms_file(): ?string {
        $candidates = [];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'llms.md';
        }

        $candidates[] = trailingslashit(ABSPATH) . 'llms.md';

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function serve_physical_file(string $path): void {
        $this->emit_discovery_link_header();

        status_header(200);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=' . self::SUCCESS_MAX_AGE);

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->respond_503('llms.md unavailable: physical file exists but is not readable.');
        }

        echo $contents;
        exit;
    }

    private function is_public_post_type(string $post_type): bool {
        $object = get_post_type_object($post_type);
        return $object !== null && !empty($object->public);
    }

    private function clean_content(string $content): string {
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content, true);
        $content = preg_replace('/\s+/', ' ', $content) ?? $content;
        return trim($content);
    }

    private function build_excerpt(WP_Post $post, string $clean_content): string {
        if (has_excerpt($post)) {
            return wp_strip_all_tags((string) get_the_excerpt($post), true);
        }

        return wp_trim_words($clean_content, 55, '...');
    }

    private function extract_headings(string $content): array {
        if ($content === '') {
            return [];
        }

        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $headings = [];
        foreach ($matches[1] as $heading) {
            $clean = trim(wp_strip_all_tags((string) $heading, true));
            if ($clean !== '') {
                $headings[] = $clean;
            }
        }

        return array_slice($headings, 0, 20);
    }

    private function limit_chars(string $text, int $max): string {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }

        return substr($text, 0, $max);
    }

    private function get_terms_for_post(int $post_id, string $taxonomy): array {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!is_array($terms)) {
            return [];
        }

        $names = [];
        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $names[] = $term->name;
            }
        }

        return $names;
    }

    private function get_featured_image_alt(int $post_id): string {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id <= 0) {
            return '';
        }

        $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        return is_string($alt) ? trim($alt) : '';
    }

    private function format_timestamp(int $timestamp): string {
        if ($timestamp <= 0) {
            return 'n/a';
        }

        return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
    }
}

if (!defined('LLMS_MD_DISABLE_BOOTSTRAP') || !LLMS_MD_DISABLE_BOOTSTRAP) {
    LLMS_MD_Plugin::init();
    register_activation_hook(__FILE__, ['LLMS_MD_Plugin', 'activate']);
    register_deactivation_hook(__FILE__, ['LLMS_MD_Plugin', 'deactivate']);
}
