<?php
/**
 * Self hosted plugin updates using the native WordPress updater,
 * powered by the GitHub Releases API.
 *
 * From v1.2.0 onwards, releases are published on GitHub. The plugin
 * checks the latest release tag and uses the attached ZIP asset as
 * the update package.
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RGY_WiPay_Updater {

    private static $instance = null;

    private $cache_key = 'rgy_wipay_update_payload';

    /**
     * Singleton.
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('site_transient_update_plugins', array($this, 'inject_update'));
        add_filter('plugins_api', array($this, 'plugins_api'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'rename_unpacked_folder'), 10, 4);

        $plugin_basename = plugin_basename(RGY_WIPAY_WC_FILE);
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'add_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_row_meta_links'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_thickbox'));
    }

    /**
     * Returns the GitHub repo as owner/repo, filterable.
     */
    public function get_repo() {
        $repo = defined('RGY_WIPAY_GITHUB_REPO') ? (string) RGY_WIPAY_GITHUB_REPO : '';
        return (string) apply_filters('rgy_wipay_github_repo', $repo);
    }

    /**
     * Clears cached remote payload.
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
    }

    /**
     * Fetches and validates the latest release from the GitHub API.
     */
    public function get_remote_payload($force = false) {
        if (!$force) {
            $cached = get_transient($this->cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $repo = $this->get_repo();
        if (empty($repo) || false === strpos($repo, '/')) {
            return null;
        }

        $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress; ' . home_url('/'),
            ),
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        $payload = $this->build_payload_from_release($data);
        if (!$payload) {
            return null;
        }

        // Cache for 12 hours.
        set_transient($this->cache_key, $payload, 12 * HOUR_IN_SECONDS);

        return $payload;
    }

    /**
     * Builds a payload from a GitHub Release object.
     */
    private function build_payload_from_release($release) {
        $tag = isset($release['tag_name']) ? sanitize_text_field((string) $release['tag_name']) : '';
        if (empty($tag)) {
            return null;
        }

        $version = ltrim($tag, 'vV');
        if (!preg_match('/^\d+(\.\d+)*/', $version)) {
            return null;
        }

        // Find the first ZIP asset attached to the release.
        $download_url = '';
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $name = isset($asset['name']) ? (string) $asset['name'] : '';
                $url  = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';
                if ($name && $url && '.zip' === strtolower(substr($name, -4))) {
                    $download_url = esc_url_raw($url);
                    break;
                }
            }
        }

        if (empty($download_url)) {
            return null;
        }

        $homepage = 'https://github.com/' . $this->get_repo();

        $body_md = isset($release['body']) ? (string) $release['body'] : '';
        $changelog_html = $this->markdown_to_html($body_md);

        $published_at = isset($release['published_at']) ? sanitize_text_field((string) $release['published_at']) : '';
        $last_updated = $published_at ? gmdate('Y-m-d H:i', strtotime($published_at)) : '';

        return array(
            'name'         => 'Revamped WiPay Gateway for WooCommerce',
            'slug'         => 'revamped-wipay-woocommerce',
            'version'      => $version,
            'download_url' => $download_url,
            'homepage'     => $homepage,
            'tested'       => '6.7',
            'requires'     => '6.0',
            'requires_php' => '7.4',
            'last_updated' => $last_updated,
            'sections'     => array(
                'description'  => $this->get_static_description(),
                'installation' => $this->get_static_installation(),
                'changelog'    => $changelog_html,
            ),
        );
    }

    /**
     * Convert simple markdown (used in GitHub release bodies) to HTML.
     * Handles headings, bullet lists, paragraphs and links.
     */
    private function markdown_to_html($md) {
        $md = (string) $md;
        if ('' === $md) {
            return '';
        }

        $md = str_replace(array("\r\n", "\r"), "\n", $md);
        $lines = explode("\n", $md);

        $html = '';
        $in_list = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
                $level = strlen($m[1]);
                $level = min(6, max(2, $level + 1));
                $html .= '<h' . $level . '>' . esc_html(trim($m[2])) . '</h' . $level . ">\n";
                continue;
            }

            if (preg_match('/^[\-\*]\s+(.+)$/', $line, $m)) {
                if (!$in_list) { $html .= "<ul>\n"; $in_list = true; }
                $html .= '<li>' . $this->inline_md($m[1]) . "</li>\n";
                continue;
            }

            if ('' === trim($line)) {
                if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
                continue;
            }

            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            $html .= '<p>' . $this->inline_md($line) . "</p>\n";
        }

        if ($in_list) { $html .= "</ul>\n"; }

        return $html;
    }

    /**
     * Convert inline markdown (links, code, bold, italic) to HTML.
     */
    private function inline_md($text) {
        $text = esc_html($text);
        // Links: [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m) {
            return '<a href="' . esc_url($m[2]) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $text);
        // Inline code: `code`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        // Bold: **text**
        $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
        // Italic: *text*
        $text = preg_replace('/(?<!\*)\*(?!\*)([^\*]+)\*(?!\*)/', '<em>$1</em>', $text);
        return $text;
    }

    private function get_static_description() {
        return '<h2>WiPay payments for WooCommerce</h2>
<p>This plugin adds WiPay as a WooCommerce payment method. Customers are redirected to WiPay\'s hosted payment page to enter card details, then return to your site where WooCommerce updates the order.</p>
<ul>
  <li>Hosted redirect checkout flow with Sandbox and Live environments</li>
  <li>WooCommerce Classic Checkout and Blocks Checkout support</li>
  <li>Optional USD charging with live FX conversion and rounding up to the next whole USD at checkout</li>
  <li>Security hardened return handling with signed return token, transaction matching and replay protection</li>
  <li>Native WordPress updates via GitHub Releases</li>
</ul>';
    }

    private function get_static_installation() {
        return '<h2>Installation</h2>
<ol>
  <li>Install the plugin ZIP in WordPress, then activate the plugin.</li>
  <li>Go to WooCommerce &gt; Settings &gt; Payments and enable WiPay.</li>
  <li>Open the WiPay settings and enter your account number and API key.</li>
  <li>If your store currency is not supported by WiPay, set Charge currency to USD.</li>
  <li>Test in Sandbox first, then switch to Live for production.</li>
</ol>
<h2>Updates</h2>
<p>The plugin checks GitHub Releases for new versions automatically. WordPress shows updates on the Plugins page and in Dashboard &gt; Updates.</p>';
    }

    /**
     * Inject update data into the WordPress update transient.
     */
    public function inject_update($transient) {
        if (empty($transient) || !is_object($transient) || empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $payload = $this->get_remote_payload(false);
        if (!$payload) {
            return $transient;
        }

        $plugin_basename = plugin_basename(RGY_WIPAY_WC_FILE);
        if (empty($transient->checked[$plugin_basename])) {
            return $transient;
        }

        $current_version = (string) $transient->checked[$plugin_basename];
        if (version_compare($payload['version'], $current_version, '<=')) {
            return $transient;
        }

        $update = new stdClass();
        $update->slug = $payload['slug'];
        $update->plugin = $plugin_basename;
        $update->new_version = $payload['version'];
        $update->url = $payload['homepage'];
        $update->package = $payload['download_url'];

        if (!empty($payload['tested'])) {
            $update->tested = $payload['tested'];
        }
        if (!empty($payload['requires'])) {
            $update->requires = $payload['requires'];
        }
        if (!empty($payload['requires_php'])) {
            $update->requires_php = $payload['requires_php'];
        }

        $transient->response[$plugin_basename] = $update;
        return $transient;
    }

    /**
     * Provide plugin details modal content via plugins_api.
     */
    public function plugins_api($result, $action, $args) {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (empty($args->slug)) {
            return $result;
        }

        $payload = $this->get_remote_payload(false);
        if (!$payload) {
            return $result;
        }

        if ((string) $args->slug !== (string) $payload['slug']) {
            return $result;
        }

        $info = new stdClass();
        $info->name = $payload['name'];
        $info->slug = $payload['slug'];
        $info->version = $payload['version'];
        $info->author = '<a href="https://revamped.gy">Revamped GY</a>';
        $info->homepage = $payload['homepage'];
        $info->download_link = $payload['download_url'];

        if (!empty($payload['requires'])) {
            $info->requires = $payload['requires'];
        }
        if (!empty($payload['requires_php'])) {
            $info->requires_php = $payload['requires_php'];
        }
        if (!empty($payload['tested'])) {
            $info->tested = $payload['tested'];
        }
        if (!empty($payload['last_updated'])) {
            $info->last_updated = $payload['last_updated'];
        }

        $info->sections = array(
            'description'  => isset($payload['sections']['description']) ? $payload['sections']['description'] : '',
            'installation' => isset($payload['sections']['installation']) ? $payload['sections']['installation'] : '',
            'changelog'    => isset($payload['sections']['changelog']) ? $payload['sections']['changelog'] : '',
        );

        return $info;
    }

    /**
     * GitHub release ZIP assets may unpack into a folder name that
     * differs from the plugin slug. Rename the folder before WordPress
     * moves it into wp-content/plugins so the plugin slug stays stable.
     */
    public function rename_unpacked_folder($source, $remote_source, $upgrader, $hook_extra = array()) {
        if (is_wp_error($source)) {
            return $source;
        }

        if (empty($hook_extra['plugin'])) {
            return $source;
        }

        if (plugin_basename(RGY_WIPAY_WC_FILE) !== $hook_extra['plugin']) {
            return $source;
        }

        $expected = trailingslashit($remote_source) . 'revamped-wipay-woocommerce/';
        if (trailingslashit($source) === $expected) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $source;
        }

        if ($wp_filesystem->move($source, $expected)) {
            return $expected;
        }

        return $source;
    }

    /**
     * Enqueue Thickbox on the Plugins page so "View details" works.
     */
    public function enqueue_thickbox($hook) {
        if ('plugins.php' !== $hook) {
            return;
        }
        add_thickbox();
    }

    public function add_action_links($links) {
        $details = '<a href="' . esc_url($this->get_details_modal_url()) . '" class="thickbox open-plugin-details-modal">' . esc_html__('View details', 'revamped-wipay-woocommerce') . '</a>';
        array_unshift($links, $details);
        return $links;
    }

    public function add_row_meta_links($links, $file) {
        $plugin_basename = plugin_basename(RGY_WIPAY_WC_FILE);
        if ($file !== $plugin_basename) {
            return $links;
        }
        $links[] = '<a href="' . esc_url($this->get_details_modal_url()) . '" class="thickbox open-plugin-details-modal">' . esc_html__('View details', 'revamped-wipay-woocommerce') . '</a>';
        return $links;
    }

    private function get_details_modal_url() {
        $slug = 'revamped-wipay-woocommerce';
        $payload = $this->get_remote_payload(false);
        if (!empty($payload['slug'])) {
            $slug = $payload['slug'];
        }

        return add_query_arg(
            array(
                'tab'       => 'plugin-information',
                'plugin'    => $slug,
                'TB_iframe' => 'true',
                'width'     => 600,
                'height'    => 550,
            ),
            admin_url('plugin-install.php')
        );
    }
}
