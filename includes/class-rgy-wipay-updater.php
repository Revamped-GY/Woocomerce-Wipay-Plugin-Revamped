<?php
/**
 * Plugin updates through the native WordPress updater.
 *
 * Updates come from GitHub Releases on Revamped-GY/Woocomerce-Wipay-Plugin-Revamped.
 * Each release must carry revamped-wipay-woocommerce-vX.Y.Z.zip and its
 * .zip.sha256 file. The package is downloaded and checked against that hash
 * before WordPress unpacks it.
 *
 * If GitHub cannot be reached, the JSON file on revamped.gy is read instead.
 * It must name a package on the same host together with its SHA-256 value.
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RGY_WiPay_Updater {

    const DEFAULT_REPO = 'Revamped-GY/Woocomerce-Wipay-Plugin-Revamped';
    const SLUG = 'revamped-wipay-woocommerce';
    const CACHE_KEY = 'rgy_wipay_update_info_v2';
    const FAILURE_KEY = 'rgy_wipay_update_check_failed';
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    const FAILURE_TTL = HOUR_IN_SECONDS;

    private static $instance = null;

    /** @var array|null|false Request-level cache. False means not loaded yet. */
    private $remote = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_update'));
        add_filter('plugins_api', array($this, 'plugins_api'), 20, 3);
        // Run late so a package supplied by another filter is still checked.
        add_filter('upgrader_pre_download', array($this, 'download_verified_package'), PHP_INT_MAX, 4);
        add_filter('upgrader_source_selection', array($this, 'keep_installed_folder'), 10, 4);
        add_action('upgrader_process_complete', array($this, 'purge_cache'), 10, 2);
    }

    private function plugin_basename() {
        return plugin_basename(RGY_WIPAY_WC_FILE);
    }

    /**
     * Repository in owner/name form. Override with RGY_WIPAY_GITHUB_REPO or
     * the rgy_wipay_github_repo filter.
     */
    public function get_repo() {
        $repo = defined('RGY_WIPAY_GITHUB_REPO') && is_string(RGY_WIPAY_GITHUB_REPO) && '' !== RGY_WIPAY_GITHUB_REPO
            ? RGY_WIPAY_GITHUB_REPO
            : self::DEFAULT_REPO;
        $repo = (string) apply_filters('rgy_wipay_github_repo', $repo);
        return preg_match('#^[A-Za-z0-9._-]{1,100}/[A-Za-z0-9._-]{1,100}$#', $repo) ? $repo : '';
    }

    private function get_manifest_url() {
        $url = defined('RGY_WIPAY_UPDATE_JSON_URL') ? (string) RGY_WIPAY_UPDATE_JSON_URL : '';
        return (string) apply_filters('rgy_wipay_update_json_url', $url);
    }

    public function clear_cache() {
        $this->remote = false;
        delete_site_transient(self::CACHE_KEY);
        delete_site_transient(self::FAILURE_KEY);
    }

    /**
     * Latest release details, cached. A failed check is remembered for an
     * hour so a GitHub outage or rate limit does not slow every admin page.
     *
     * @return array|null
     */
    public function get_remote_info($force = false) {
        if (!$force && false !== $this->remote) {
            return $this->remote;
        }

        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached['version'])) {
                $this->remote = $cached;
                return $cached;
            }
            if (get_site_transient(self::FAILURE_KEY)) {
                $this->remote = null;
                return null;
            }
        }

        $info = $this->fetch_from_github();
        if (!$info) {
            $manifest = $this->get_manifest_url();
            if ('' !== $manifest) {
                $info = $this->fetch_from_manifest($manifest);
            }
        }

        if ($info) {
            set_site_transient(self::CACHE_KEY, $info, self::CACHE_TTL);
            delete_site_transient(self::FAILURE_KEY);
        } else {
            set_site_transient(self::FAILURE_KEY, time(), self::FAILURE_TTL);
            $info = null;
        }

        $this->remote = $info;
        return $info;
    }

    private function request_headers($accept) {
        return array(
            'Accept'     => $accept,
            'User-Agent' => 'Revamped-WiPay-Updater/' . (defined('RGY_WIPAY_WC_VERSION') ? RGY_WIPAY_WC_VERSION : '0'),
        );
    }

    /**
     * @return array|null
     */
    private function fetch_from_github() {
        $repo = $this->get_repo();
        if ('' === $repo) {
            return null;
        }

        $headers = $this->request_headers('application/vnd.github+json');
        $token = (string) apply_filters('rgy_wipay_github_token', '');
        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_safe_remote_get('https://api.github.com/repos/' . $repo . '/releases/latest', array(
            'timeout'            => 12,
            'headers'            => $headers,
            'reject_unsafe_urls' => true,
        ));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ('' === $body || strlen($body) > 1024 * 1024) {
            return null;
        }

        $release = json_decode($body, true);
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) || empty($release['tag_name'])) {
            return null;
        }

        $version = ltrim((string) $release['tag_name'], 'vV');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        // Only the exact package and checksum built by the release workflow count.
        $zip_name = self::SLUG . '-v' . $version . '.zip';
        $zip_url = '';
        $sha_url = '';
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            if (!is_array($asset) || empty($asset['browser_download_url']) || empty($asset['name'])) {
                continue;
            }
            $name = (string) $asset['name'];
            if ($name === $zip_name) {
                if ('' !== $zip_url) {
                    return null;
                }
                $zip_url = (string) $asset['browser_download_url'];
            } elseif ($name === $zip_name . '.sha256') {
                if ('' !== $sha_url) {
                    return null;
                }
                $sha_url = (string) $asset['browser_download_url'];
            }
        }

        $github_hosts = array('github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com');
        if (!$this->is_allowed_url($zip_url, $github_hosts) || !$this->is_allowed_url($sha_url, $github_hosts)) {
            return null;
        }

        $sha_response = wp_safe_remote_get($sha_url, array(
            'timeout'            => 8,
            'headers'            => $this->request_headers('text/plain'),
            'reject_unsafe_urls' => true,
        ));
        if (is_wp_error($sha_response) || 200 !== (int) wp_remote_retrieve_response_code($sha_response)) {
            return null;
        }
        $sha_body = (string) wp_remote_retrieve_body($sha_response);
        if (strlen($sha_body) > 4096 || !preg_match('/\b([a-fA-F0-9]{64})\b/', $sha_body, $m)) {
            return null;
        }

        $last_updated = '';
        if (!empty($release['published_at'])) {
            $ts = strtotime((string) $release['published_at']);
            $last_updated = $ts ? gmdate('Y-m-d', $ts) : '';
        }

        return array(
            'version'        => $version,
            'download_url'   => $zip_url,
            'package_sha256' => strtolower($m[1]),
            'homepage'       => 'https://github.com/' . $repo,
            'last_updated'   => $last_updated,
            'changelog'      => $this->markdown_to_html(isset($release['body']) ? (string) $release['body'] : ''),
            'source'         => 'github',
        );
    }

    /**
     * @return array|null
     */
    private function fetch_from_manifest($url) {
        if (!$this->is_allowed_url($url, null)) {
            return null;
        }

        $response = wp_safe_remote_get($url, array(
            'timeout'            => 12,
            'headers'            => $this->request_headers('application/json'),
            'reject_unsafe_urls' => true,
        ));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ('' === $body || strlen($body) > 256 * 1024) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['version']) || empty($data['download_url'])) {
            return null;
        }

        $version = (string) $data['version'];
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        $download_url = (string) $data['download_url'];
        $manifest_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if (!$this->is_allowed_url($download_url, array($manifest_host))) {
            return null;
        }

        $sha = isset($data['package_sha256']) && is_string($data['package_sha256']) ? strtolower(trim($data['package_sha256'])) : '';
        if (!preg_match('/^[a-f0-9]{64}$/', $sha)) {
            return null;
        }

        $changelog = '';
        if (!empty($data['sections']['changelog']) && is_string($data['sections']['changelog'])) {
            $changelog = wp_kses_post($data['sections']['changelog']);
        }

        return array(
            'version'        => $version,
            'download_url'   => $download_url,
            'package_sha256' => $sha,
            'homepage'       => !empty($data['homepage']) ? esc_url_raw((string) $data['homepage']) : '',
            'last_updated'   => !empty($data['last_updated']) ? sanitize_text_field((string) $data['last_updated']) : '',
            'changelog'      => $changelog,
            'source'         => 'manifest',
        );
    }

    /**
     * HTTPS only. When $hosts is given, the host must be one of them.
     */
    private function is_allowed_url($url, $hosts) {
        $url = (string) $url;
        if ('' === $url || 'https' !== strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('' === $host) {
            return false;
        }
        if (null === $hosts) {
            return true;
        }
        $hosts = (array) apply_filters('rgy_wipay_update_allowed_hosts', $hosts);
        $hosts = array_filter(array_map('strtolower', array_map('strval', $hosts)));
        return in_array($host, $hosts, true);
    }

    private function readme_field($field) {
        $readme = dirname(RGY_WIPAY_WC_FILE) . '/readme.txt';
        if (!is_readable($readme)) {
            return '';
        }
        $head = (string) file_get_contents($readme, false, null, 0, 4096);
        if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.+)$/im', $head, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function update_item(array $remote) {
        return (object) array(
            'id'           => $this->plugin_basename(),
            'slug'         => self::SLUG,
            'plugin'       => $this->plugin_basename(),
            'new_version'  => $remote['version'],
            'url'          => $remote['homepage'] ? $remote['homepage'] : 'https://revamped.gy/wipay-woocommerce-plugin/',
            'package'      => $remote['download_url'],
            'tested'       => $this->readme_field('Tested up to'),
            'requires'     => $this->readme_field('Requires at least'),
            'requires_php' => $this->readme_field('Requires PHP'),
            'icons'        => array(),
            'banners'      => array(),
        );
    }

    public function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }
        $basename = $this->plugin_basename();
        if (empty($transient->checked) || !is_array($transient->checked) || empty($transient->checked[$basename])) {
            return $transient;
        }

        $remote = $this->get_remote_info();
        if (!$remote) {
            return $transient;
        }

        $item = $this->update_item($remote);
        if (version_compare($remote['version'], (string) $transient->checked[$basename], '>')) {
            $transient->response[$basename] = $item;
            if (isset($transient->no_update[$basename])) {
                unset($transient->no_update[$basename]);
            }
        } else {
            // Listing the plugin here lets WordPress show View details and the
            // auto-update toggle for it.
            $transient->no_update[$basename] = $item;
            if (isset($transient->response[$basename])) {
                unset($transient->response[$basename]);
            }
        }

        return $transient;
    }

    public function plugins_api($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || self::SLUG !== $args->slug) {
            return $result;
        }

        $remote = $this->get_remote_info();
        if (!$remote) {
            return $result;
        }

        return (object) array(
            'name'          => 'Revamped WiPay Gateway for WooCommerce',
            'slug'          => self::SLUG,
            'version'       => $remote['version'],
            'author'        => '<a href="https://revamped.gy">Revamped GY</a>',
            'homepage'      => 'https://revamped.gy/wipay-woocommerce-plugin/',
            'download_link' => $remote['download_url'],
            'requires'      => $this->readme_field('Requires at least'),
            'tested'        => $this->readme_field('Tested up to'),
            'requires_php'  => $this->readme_field('Requires PHP'),
            'last_updated'  => $remote['last_updated'],
            'sections'      => array(
                'description'  => $this->description_html(),
                'installation' => $this->installation_html(),
                'changelog'    => '' !== $remote['changelog'] ? $remote['changelog'] : '<p>See the release notes on GitHub.</p>',
            ),
        );
    }

    /**
     * Download this plugin's package and check its SHA-256 value before
     * WordPress unpacks it. Other plugins are left alone.
     */
    public function download_verified_package($reply, $package, $upgrader, $hook_extra = array()) {
        $basename = $this->plugin_basename();
        $hook_extra = is_array($hook_extra) ? $hook_extra : array();

        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] !== $basename) {
            return $reply;
        }
        if (isset($hook_extra['plugins']) && is_array($hook_extra['plugins']) && !in_array($basename, $hook_extra['plugins'], true)) {
            return $reply;
        }

        $remote = $this->get_remote_info();
        $is_ours = (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $basename)
            || (isset($hook_extra['plugins']) && is_array($hook_extra['plugins']) && in_array($basename, $hook_extra['plugins'], true))
            || ($remote && is_string($package) && $package === $remote['download_url']);
        if (!$is_ours || is_wp_error($reply)) {
            return $reply;
        }

        if (!$remote || !is_string($package) || $package !== $remote['download_url']) {
            return new WP_Error('rgy_wipay_update_package', __('The WiPay update package does not match the published release. Update stopped.', 'revamped-wipay-woocommerce'));
        }
        $expected = strtolower((string) $remote['package_sha256']);
        if (!preg_match('/^[a-f0-9]{64}$/', $expected)) {
            return new WP_Error('rgy_wipay_update_hash_missing', __('The WiPay update has no SHA-256 value. Update stopped.', 'revamped-wipay-woocommerce'));
        }

        if (is_string($reply) && '' !== $reply) {
            $file = $reply;
        } elseif (false === $reply) {
            if (!function_exists('download_url')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $file = download_url($package, 300);
        } else {
            return new WP_Error('rgy_wipay_update_download', __('The WiPay update download could not be checked. Update stopped.', 'revamped-wipay-woocommerce'));
        }

        if (is_wp_error($file)) {
            return $file;
        }
        if (!is_string($file) || !is_file($file)) {
            return new WP_Error('rgy_wipay_update_file', __('The WiPay update file is missing. Update stopped.', 'revamped-wipay-woocommerce'));
        }

        $actual = hash_file('sha256', $file);
        if (!is_string($actual) || !hash_equals($expected, strtolower($actual))) {
            wp_delete_file($file);
            return new WP_Error('rgy_wipay_update_hash_mismatch', __('The WiPay update file failed its SHA-256 check. Update stopped.', 'revamped-wipay-woocommerce'));
        }

        return $file;
    }

    /**
     * Keep the folder the plugin is installed in. Without this a site that
     * installed from a renamed folder would lose the plugin's active state
     * after the update.
     */
    public function keep_installed_folder($source, $remote_source, $upgrader, $hook_extra = array()) {
        if (is_wp_error($source) || !is_array($hook_extra) || empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename()) {
            return $source;
        }

        $folder = dirname($this->plugin_basename());
        if ('.' === $folder || '' === $folder) {
            return $source;
        }

        $wanted = trailingslashit($remote_source) . $folder . '/';
        if (trailingslashit($source) === $wanted) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem || !$wp_filesystem->exists(trailingslashit($source) . basename(RGY_WIPAY_WC_FILE))) {
            return $source;
        }
        if ($wp_filesystem->exists($wanted) || !$wp_filesystem->move($source, $wanted)) {
            return new WP_Error('rgy_wipay_update_folder', __('The WiPay update could not be placed in the plugin folder.', 'revamped-wipay-woocommerce'));
        }
        return $wanted;
    }

    public function purge_cache($upgrader, $hook_extra) {
        if (is_array($hook_extra) && isset($hook_extra['type']) && 'plugin' === $hook_extra['type']) {
            $this->clear_cache();
        }
    }

    /**
     * Small Markdown to HTML conversion for GitHub release notes.
     */
    private function markdown_to_html($md) {
        $md = str_replace(array("\r\n", "\r"), "\n", (string) $md);
        if ('' === trim($md)) {
            return '';
        }

        $html = '';
        $in_list = false;
        foreach (explode("\n", $md) as $line) {
            $line = rtrim($line);
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
                $level = min(6, strlen($m[1]) + 1);
                $html .= '<h' . $level . '>' . esc_html(trim($m[2])) . '</h' . $level . ">\n";
            } elseif (preg_match('/^[\-\*]\s+(.+)$/', $line, $m)) {
                if (!$in_list) { $html .= "<ul>\n"; $in_list = true; }
                $html .= '<li>' . $this->inline_md($m[1]) . "</li>\n";
            } elseif ('' === trim($line)) {
                if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            } else {
                if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
                $html .= '<p>' . $this->inline_md($line) . "</p>\n";
            }
        }
        if ($in_list) {
            $html .= "</ul>\n";
        }
        return wp_kses_post($html);
    }

    private function inline_md($text) {
        $text = esc_html($text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
            return '<a href="' . esc_url(html_entity_decode($m[2])) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
        return $text;
    }

    private function description_html() {
        return '<p>Adds WiPay as a WooCommerce payment method. Customers pay by card on WiPay\'s hosted page and return to your site, where the order is checked and updated.</p>'
            . '<ul><li>Sandbox and Live environments</li><li>Classic and Blocks checkout</li><li>Optional USD charging with an exchange rate, rounded up to the next whole dollar</li><li>Signed return links, hash checks and replay protection</li><li>Updates from GitHub Releases, checked by SHA-256 before install</li></ul>';
    }

    private function installation_html() {
        return '<ol><li>Upload the ZIP under Plugins, Add New, Upload Plugin, then activate it.</li>'
            . '<li>Go to WooCommerce, Settings, Payments and enable WiPay.</li>'
            . '<li>Enter the account number and API key for the environment you use.</li>'
            . '<li>If WiPay cannot charge your store currency, set Charge currency to USD.</li>'
            . '<li>Test in Sandbox, then switch to Live.</li></ol>';
    }
}
