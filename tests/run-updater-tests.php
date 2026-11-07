<?php
/**
 * Updater checks without WordPress. Run with: php tests/run-updater-tests.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('RGY_WIPAY_WC_FILE', dirname(__DIR__) . '/revamped-wipay-woocommerce.php');
define('RGY_WIPAY_WC_VERSION', '1.3.0');
define('RGY_WIPAY_UPDATE_JSON_URL', 'https://revamped.gy/updates/revamped-wipay-woocommerce.json');

final class WP_Error {
    private $code;
    public function __construct(string $code, string $message = '') { $this->code = $code; }
    public function get_error_code(): string { return $this->code; }
}

$site_transients = array();
$http = array();
$http_log = array();
$download_contents = '';
$download_count = 0;

function is_wp_error($v): bool { return $v instanceof WP_Error; }
function add_filter(...$a): void {}
function add_action(...$a): void {}
function apply_filters(string $hook, $value) { return $value; }
function __($text, $domain = '') { return $text; }
function plugin_basename(string $file): string { return 'revamped-wipay-woocommerce/' . basename($file); }
function get_site_transient(string $k) { global $site_transients; return $site_transients[$k] ?? false; }
function set_site_transient(string $k, $v, int $ttl = 0): bool { global $site_transients; $site_transients[$k] = $v; return true; }
function delete_site_transient(string $k): bool { global $site_transients; unset($site_transients[$k]); return true; }
function wp_parse_url(string $url, int $component = -1) { return parse_url($url, $component); }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function esc_url($u) { return (string) $u; }
function esc_url_raw($u) { return (string) $u; }
function wp_kses_post($h) { return (string) $h; }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function trailingslashit(string $p): string { return rtrim($p, '/') . '/'; }
function wp_safe_remote_get(string $url, array $args = array()) {
    global $http, $http_log;
    $http_log[] = $url;
    if (!isset($http[$url])) {
        return new WP_Error('http_request_failed', 'offline');
    }
    return $http[$url];
}
function wp_remote_retrieve_response_code($r) { return is_array($r) ? $r['code'] : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) ? $r['body'] : ''; }
function download_url(string $url, int $timeout = 300) {
    global $download_contents, $download_count;
    $download_count++;
    $file = tempnam(sys_get_temp_dir(), 'rgy-wipay-test-');
    file_put_contents($file, $download_contents);
    return $file;
}
function wp_delete_file(string $file): void { if (is_file($file)) { unlink($file); } }

require dirname(__DIR__) . '/includes/class-rgy-wipay-updater.php';

$tests = 0;
$failures = 0;
function check(bool $ok, string $message): void {
    global $tests, $failures;
    $tests++;
    if (!$ok) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

function reset_state(): void {
    global $site_transients, $http, $http_log, $download_count;
    $site_transients = array();
    $http = array();
    $http_log = array();
    $download_count = 0;
    RGY_WiPay_Updater::instance()->clear_cache();
}

function github_release(string $version, array $assets, array $extra = array()): array {
    return array('code' => 200, 'body' => json_encode(array_merge(array(
        'tag_name' => 'v' . $version,
        'draft' => false,
        'prerelease' => false,
        'published_at' => '2026-11-07T12:00:00Z',
        'body' => "### Fixed\n- Item",
        'assets' => $assets,
    ), $extra)));
}

$api = 'https://api.github.com/repos/Revamped-GY/Woocomerce-Wipay-Plugin-Revamped/releases/latest';
$zip = 'https://github.com/Revamped-GY/Woocomerce-Wipay-Plugin-Revamped/releases/download/v1.4.0/revamped-wipay-woocommerce-v1.4.0.zip';
$sha = $zip . '.sha256';
$hash = hash('sha256', 'package');
$updater = RGY_WiPay_Updater::instance();

check($updater->get_repo() === 'Revamped-GY/Woocomerce-Wipay-Plugin-Revamped', 'The default repository is the Revamped-GY repository.');

// A complete release is read from GitHub.
reset_state();
$http[$api] = github_release('1.4.0', array(
    array('name' => 'revamped-wipay-woocommerce.zip', 'browser_download_url' => 'https://github.com/x/latest.zip'),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => $zip),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip.sha256', 'browser_download_url' => $sha),
));
$http[$sha] = array('code' => 200, 'body' => $hash . "  revamped-wipay-woocommerce-v1.4.0.zip\n");
$info = $updater->get_remote_info(true);
check(is_array($info) && $info['version'] === '1.4.0', 'The release version is read from the tag.');
check(is_array($info) && $info['download_url'] === $zip, 'Only the exact versioned ZIP is used, not the fixed-name copy.');
check(is_array($info) && $info['package_sha256'] === $hash, 'The SHA-256 value comes from the sidecar file.');
check(is_array($info) && $info['source'] === 'github', 'GitHub is the first source.');

// The update is offered to an older install and listed as current for a newer one.
$t = (object) array('checked' => array('revamped-wipay-woocommerce/revamped-wipay-woocommerce.php' => '1.2.0'), 'response' => array(), 'no_update' => array());
$t = $updater->inject_update($t);
check(isset($t->response['revamped-wipay-woocommerce/revamped-wipay-woocommerce.php']), 'An older install is offered the update.');
check(($t->response['revamped-wipay-woocommerce/revamped-wipay-woocommerce.php']->package ?? '') === $zip, 'The offered package is the verified release ZIP.');
$t = (object) array('checked' => array('revamped-wipay-woocommerce/revamped-wipay-woocommerce.php' => '1.4.0'), 'response' => array(), 'no_update' => array());
$t = $updater->inject_update($t);
check(empty($t->response) && isset($t->no_update['revamped-wipay-woocommerce/revamped-wipay-woocommerce.php']), 'A current install is listed in no_update so the auto-update toggle shows.');

// Missing checksum, drafts, pre-releases and other hosts are refused.
reset_state();
$http[$api] = github_release('1.4.0', array(array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => $zip)));
check($updater->get_remote_info(true) === null, 'A release without a checksum file is not offered.');

reset_state();
$http[$api] = github_release('1.4.0', array(
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => $zip),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip.sha256', 'browser_download_url' => $sha),
), array('prerelease' => true));
$http[$sha] = array('code' => 200, 'body' => $hash);
check($updater->get_remote_info(true) === null, 'A pre-release is not offered.');

reset_state();
$http[$api] = github_release('1.4.0', array(
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => 'https://evil.example/revamped-wipay-woocommerce-v1.4.0.zip'),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip.sha256', 'browser_download_url' => $sha),
));
$http[$sha] = array('code' => 200, 'body' => $hash);
check($updater->get_remote_info(true) === null, 'A package on another host is not offered.');

reset_state();
$http[$api] = github_release('1.4.0', array(
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => $zip),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => $zip . '?copy'),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip.sha256', 'browser_download_url' => $sha),
));
$http[$sha] = array('code' => 200, 'body' => $hash);
check($updater->get_remote_info(true) === null, 'Two packages with the same name are refused.');

// The revamped.gy file is used when GitHub cannot be reached.
reset_state();
$manifest_zip = 'https://revamped.gy/updates/revamped-wipay-woocommerce-v1.4.0.zip';
$http[RGY_WIPAY_UPDATE_JSON_URL] = array('code' => 200, 'body' => json_encode(array(
    'version' => '1.4.0',
    'download_url' => $manifest_zip,
    'package_sha256' => strtoupper($hash),
    'sections' => array('changelog' => '<p>Notes</p>'),
)));
$info = $updater->get_remote_info(true);
check(is_array($info) && $info['source'] === 'manifest' && $info['download_url'] === $manifest_zip, 'The revamped.gy file is the fallback source.');
check(is_array($info) && $info['package_sha256'] === $hash, 'The fallback checksum is normalised.');

reset_state();
$http[RGY_WIPAY_UPDATE_JSON_URL] = array('code' => 200, 'body' => json_encode(array(
    'version' => '1.4.0',
    'download_url' => 'https://other.example/revamped-wipay-woocommerce-v1.4.0.zip',
    'package_sha256' => $hash,
)));
check($updater->get_remote_info(true) === null, 'A fallback package on another host is refused.');

reset_state();
$http[RGY_WIPAY_UPDATE_JSON_URL] = array('code' => 200, 'body' => json_encode(array(
    'version' => '1.4.0',
    'download_url' => $manifest_zip,
)));
check($updater->get_remote_info(true) === null, 'A fallback package without a checksum is refused.');

// A failed check is remembered for an hour.
reset_state();
check($updater->get_remote_info() === null, 'Nothing is offered when both sources fail.');
$calls = count($http_log);
$updater->clear_cache();
global $site_transients;
$site_transients['rgy_wipay_update_check_failed'] = time();
check($updater->get_remote_info() === null && count($http_log) === $calls, 'A recent failure is not retried on every request.');

// Download checks.
reset_state();
$http[$api] = github_release('1.4.0', array(
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip', 'browser_download_url' => $zip),
    array('name' => 'revamped-wipay-woocommerce-v1.4.0.zip.sha256', 'browser_download_url' => $sha),
));
$http[$sha] = array('code' => 200, 'body' => $hash);
$updater->get_remote_info(true);
$ours = array('plugin' => 'revamped-wipay-woocommerce/revamped-wipay-woocommerce.php');

$download_contents = 'package';
$result = $updater->download_verified_package(false, $zip, null, $ours);
check(is_string($result) && is_file($result), 'A package with the published checksum is accepted.');
if (is_string($result) && is_file($result)) {
    unlink($result);
}

$download_contents = 'altered';
$result = $updater->download_verified_package(false, $zip, null, $ours);
check(is_wp_error($result) && $result->get_error_code() === 'rgy_wipay_update_hash_mismatch', 'An altered package is rejected.');

$result = $updater->download_verified_package(false, 'https://github.com/other.zip', null, $ours);
check(is_wp_error($result) && $result->get_error_code() === 'rgy_wipay_update_package', 'A different package URL for this plugin is rejected.');

$before = $download_count;
$result = $updater->download_verified_package(false, 'https://example.com/other.zip', null, array('plugin' => 'other/other.php'));
check($result === false && $download_count === $before, 'Other plugins are left alone.');

$supplied = tempnam(sys_get_temp_dir(), 'rgy-wipay-test-');
file_put_contents($supplied, 'altered by another filter');
$result = $updater->download_verified_package($supplied, $zip, null, $ours);
check(is_wp_error($result) && !is_file($supplied), 'A file supplied by another filter is checked and removed when it fails.');

$updater->clear_cache();
$site_transients['rgy_wipay_update_check_failed'] = time();
$result = $updater->download_verified_package(false, $zip, null, $ours);
check(is_wp_error($result), 'This plugin fails closed when release details are unavailable.');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} of {$tests} updater tests failed.\n");
    exit(1);
}
fwrite(STDOUT, "All {$tests} updater tests passed.\n");
