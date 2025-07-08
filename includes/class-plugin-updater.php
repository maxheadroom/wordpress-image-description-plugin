<?php
/**
 * Plugin Updater class
 * 
 * Handles automatic plugin updates with semantic versioning
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Plugin_Updater {
    
    /**
     * Plugin file path
     */
    private $plugin_file;
    
    /**
     * Plugin slug
     */
    private $plugin_slug;
    
    /**
     * Plugin version
     */
    private $version;
    
    /**
     * Update server URL
     */
    private $update_server_url;
    
    /**
     * Plugin data
     */
    private $plugin_data;
    
    /**
     * Constructor
     */
    public function __construct($plugin_file, $update_server_url = '') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = WP_IMAGE_DESCRIPTIONS_VERSION;
        $this->update_server_url = $update_server_url;
        
        // Get plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data($plugin_file);
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        
        // Plugin information popup
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        
        // After plugin update
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
        
        // Add version info to plugins page
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        
        // Handle database updates
        add_action('plugins_loaded', array($this, 'check_database_version'));
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get remote version info
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->version, $remote_version['version'], '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version['version'],
                'url' => $this->plugin_data['PluginURI'],
                'package' => $remote_version['download_url'],
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => $remote_version['tested'],
                'requires_php' => $remote_version['requires_php'],
                'compatibility' => new stdClass(),
            );
        }
        
        return $transient;
    }
    
    /**
     * Get remote version information
     */
    private function get_remote_version() {
        // Check cache first
        $cache_key = 'wp_image_descriptions_update_check';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // GitHub repository information
        $github_user = 'maxheadroom'; // Change this to your GitHub username
        $github_repo = 'wordpress-image-description-plugin'; // Change this to your repository name
        
        // You can also use a custom update server URL instead
        $update_url = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";
        
        error_log('WP Image Descriptions: Checking for updates from: ' . $update_url);
        
        $request = wp_remote_get($update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($request)) {
            error_log('WP Image Descriptions: Update check failed: ' . $request->get_error_message());
            // Cache failure for 1 hour to avoid repeated failed requests
            set_transient($cache_key, false, HOUR_IN_SECONDS);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($request);
        if ($response_code !== 200) {
            error_log('WP Image Descriptions: Update check returned HTTP ' . $response_code);
            set_transient($cache_key, false, HOUR_IN_SECONDS);
            return false;
        }
        
        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);
        
        if (empty($data['tag_name'])) {
            error_log('WP Image Descriptions: No tag_name found in GitHub response');
            set_transient($cache_key, false, HOUR_IN_SECONDS);
            return false;
        }
        
        // Parse semantic version (remove 'v' prefix if present)
        $version = ltrim($data['tag_name'], 'v');
        if (!$this->is_valid_semver($version)) {
            error_log('WP Image Descriptions: Invalid version format: ' . $version);
            set_transient($cache_key, false, HOUR_IN_SECONDS);
            return false;
        }
        
        // Find the plugin ZIP file in assets
        $download_url = '';
        if (!empty($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (strpos($asset['name'], '.zip') !== false && strpos($asset['name'], 'wp-image-descriptions') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        
        // Fallback to zipball if no specific asset found
        if (empty($download_url)) {
            $download_url = $data['zipball_url'] ?? '';
        }
        
        $version_info = array(
            'version' => $version,
            'download_url' => $download_url,
            'details_url' => $data['html_url'] ?? '',
            'tested' => '6.4',
            'requires_php' => '7.4',
            'changelog' => $data['body'] ?? '',
            'release_date' => $data['published_at'] ?? ''
        );
        
        // Cache successful result for 12 hours
        set_transient($cache_key, $version_info, 12 * HOUR_IN_SECONDS);
        
        error_log('WP Image Descriptions: Found remote version: ' . $version . ' (current: ' . $this->version . ')');
        
        return $version_info;
    }
    
    /**
     * Validate semantic version format
     */
    private function is_valid_semver($version) {
        return preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/', $version);
    }
    
    /**
     * Compare semantic versions
     */
    public function compare_versions($version1, $version2) {
        // Parse versions
        $v1_parts = $this->parse_semver($version1);
        $v2_parts = $this->parse_semver($version2);
        
        if (!$v1_parts || !$v2_parts) {
            return version_compare($version1, $version2);
        }
        
        // Compare major.minor.patch
        foreach (array('major', 'minor', 'patch') as $part) {
            if ($v1_parts[$part] < $v2_parts[$part]) {
                return -1;
            } elseif ($v1_parts[$part] > $v2_parts[$part]) {
                return 1;
            }
        }
        
        // Compare pre-release versions
        if (empty($v1_parts['prerelease']) && !empty($v2_parts['prerelease'])) {
            return 1; // v1 is stable, v2 is pre-release
        } elseif (!empty($v1_parts['prerelease']) && empty($v2_parts['prerelease'])) {
            return -1; // v1 is pre-release, v2 is stable
        } elseif (!empty($v1_parts['prerelease']) && !empty($v2_parts['prerelease'])) {
            return strcmp($v1_parts['prerelease'], $v2_parts['prerelease']);
        }
        
        return 0; // Versions are equal
    }
    
    /**
     * Parse semantic version string
     */
    private function parse_semver($version) {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/', $version, $matches)) {
            return false;
        }
        
        return array(
            'major' => intval($matches[1]),
            'minor' => intval($matches[2]),
            'patch' => intval($matches[3]),
            'prerelease' => isset($matches[4]) ? $matches[4] : '',
            'build' => isset($matches[5]) ? $matches[5] : ''
        );
    }
    
    /**
     * Plugin information for the popup
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        $remote_version = $this->get_remote_version();
        
        if (!$remote_version) {
            return $result;
        }
        
        return (object) array(
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_version['version'],
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'requires' => '5.0',
            'tested' => $remote_version['tested'],
            'requires_php' => $remote_version['requires_php'],
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'changelog' => $remote_version['changelog']
            ),
            'download_link' => $remote_version['download_url']
        );
    }
    
    /**
     * After plugin update actions
     */
    public function after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            if (isset($options['plugins'])) {
                foreach ($options['plugins'] as $plugin) {
                    if ($plugin === $this->plugin_slug) {
                        // Clear update cache
                        delete_transient('wp_image_descriptions_update_check');
                        
                        // Run database updates if needed
                        $this->maybe_update_database();
                        
                        // Log successful update
                        error_log('WP Image Descriptions: Plugin updated successfully to version ' . $this->version);
                        
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Add version info to plugin row
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_slug) {
            $links[] = '<strong>' . sprintf(__('Version: %s', 'wp-image-descriptions'), $this->version) . '</strong>';
            
            // Add update available notice
            $remote_version = $this->get_remote_version();
            if ($remote_version && version_compare($this->version, $remote_version['version'], '<')) {
                $links[] = '<span style="color: #d63638;">' . 
                          sprintf(__('Update available: %s', 'wp-image-descriptions'), $remote_version['version']) . 
                          '</span>';
            }
        }
        
        return $links;
    }
    
    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        $current_db_version = get_option('wp_image_descriptions_db_version', '0.0.0');
        $target_db_version = '1.0.0';
        
        if (version_compare($current_db_version, $target_db_version, '<')) {
            $this->update_database($current_db_version, $target_db_version);
        }
    }
    
    /**
     * Update database schema
     */
    private function update_database($from_version, $to_version) {
        global $wpdb;
        
        error_log('WP Image Descriptions: Updating database from ' . $from_version . ' to ' . $to_version);
        
        // Database update logic based on version
        if (version_compare($from_version, '1.0.0', '<')) {
            // Updates for version 1.0.0
            $this->update_to_1_0_0();
        }
        
        // Update database version
        update_option('wp_image_descriptions_db_version', $to_version);
        
        error_log('WP Image Descriptions: Database updated successfully to version ' . $to_version);
    }
    
    /**
     * Database updates for version 1.0.0
     */
    private function update_to_1_0_0() {
        global $wpdb;
        
        // Add any new columns or tables needed for v1.0.0
        $charset_collate = $wpdb->get_charset_collate();
        
        // Example: Add new column to batches table
        $batches_table = $wpdb->prefix . 'image_description_batches';
        
        // Check if column exists before adding
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$batches_table` LIKE %s",
            'version'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `$batches_table` ADD COLUMN `version` varchar(20) DEFAULT '1.0.0' AFTER `settings`");
        }
        
        // Update existing records
        $wpdb->update(
            $batches_table,
            array('version' => '1.0.0'),
            array('version' => null),
            array('%s'),
            array('%s')
        );
    }
    
    /**
     * Maybe update database (called after plugin update)
     */
    private function maybe_update_database() {
        $this->check_database_version();
    }
    
    /**
     * Get current plugin version
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Get version info for display
     */
    public function get_version_info() {
        $parsed = $this->parse_semver($this->version);
        
        if (!$parsed) {
            return array(
                'version' => $this->version,
                'type' => 'unknown'
            );
        }
        
        $type = 'stable';
        if (!empty($parsed['prerelease'])) {
            if (strpos($parsed['prerelease'], 'alpha') !== false) {
                $type = 'alpha';
            } elseif (strpos($parsed['prerelease'], 'beta') !== false) {
                $type = 'beta';
            } elseif (strpos($parsed['prerelease'], 'rc') !== false) {
                $type = 'release-candidate';
            } else {
                $type = 'pre-release';
            }
        }
        
        return array(
            'version' => $this->version,
            'major' => $parsed['major'],
            'minor' => $parsed['minor'],
            'patch' => $parsed['patch'],
            'prerelease' => $parsed['prerelease'],
            'build' => $parsed['build'],
            'type' => $type
        );
    }
    
    /**
     * Force check for updates (for admin use)
     */
    public function force_update_check() {
        delete_transient('wp_image_descriptions_update_check');
        delete_site_transient('update_plugins');
        
        return $this->get_remote_version();
    }
}
