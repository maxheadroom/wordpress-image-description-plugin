<?php
/**
 * Plugin Name: WordPress Image Descriptions
 * Plugin URI: https://repos.mxhdr.net/maxheadroom/wordpress-image-description-plugin
 * Description: Generate AI-powered image descriptions for accessibility using OpenAI-compatible APIs. Helps create alt text for visually impaired users.
 * Version: 1.0.4
 * Author: Falko Zurell
 * Author URI: https://falko.zurell.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-image-descriptions
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: https://github.com/your-username/wp-image-descriptions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_IMAGE_DESCRIPTIONS_VERSION', '1.0.4');
define('WP_IMAGE_DESCRIPTIONS_PLUGIN_FILE', __FILE__);
define('WP_IMAGE_DESCRIPTIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_IMAGE_DESCRIPTIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR', WP_IMAGE_DESCRIPTIONS_PLUGIN_DIR . 'includes/');

/**
 * Main plugin class
 */
class WP_Image_Descriptions {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    private $plugin_core;
    private $plugin_updater;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize core
        if (class_exists('WP_Image_Descriptions_Core')) {
            $this->plugin_core = new WP_Image_Descriptions_Core();
            $this->plugin_core->init();
        }
        
        // Initialize updater
        if (class_exists('WP_Image_Descriptions_Plugin_Updater')) {
            $this->plugin_updater = new WP_Image_Descriptions_Plugin_Updater(__FILE__);
        }
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('WP_Image_Descriptions', 'uninstall'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core class
        require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-plugin-core.php';
        
        // Admin classes (load only in admin)
        if (is_admin()) {
            require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-settings.php';
            require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-media-library.php';
            require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-preview-page.php';
        }
        
        // Core functionality classes
        require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-api-client.php';
        require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-batch-manager.php';
        require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-queue-processor.php';
        require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-diagnostics.php';
        require_once WP_IMAGE_DESCRIPTIONS_INCLUDES_DIR . 'class-plugin-updater.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('WP Image Descriptions plugin activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_image_descriptions_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('WP Image Descriptions plugin deactivated');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove database tables
        self::remove_database_tables();
        
        // Remove options
        delete_option('wp_image_descriptions_settings');
        delete_option('wp_image_descriptions_version');
        
        // Log uninstall
        error_log('WP Image Descriptions plugin uninstalled');
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check current database version
        $current_db_version = get_option('wp_image_descriptions_db_version', '0');
        $target_db_version = '1.0.0';
        
        // Only create/update tables if needed
        if (version_compare($current_db_version, $target_db_version, '<')) {
            
            // Batches table
            $batches_table = $wpdb->prefix . 'image_description_batches';
            $batches_sql = "CREATE TABLE $batches_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                batch_id varchar(255) NOT NULL,
                user_id bigint(20) NOT NULL,
                mode enum('test','production') DEFAULT 'test',
                status enum('pending','processing','completed','cancelled','failed') DEFAULT 'pending',
                total_jobs int(11) DEFAULT 0,
                completed_jobs int(11) DEFAULT 0,
                failed_jobs int(11) DEFAULT 0,
                settings longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY batch_id_unique (batch_id),
                KEY user_id_idx (user_id),
                KEY status_idx (status)
            ) $charset_collate;";
            
            // Jobs table
            $jobs_table = $wpdb->prefix . 'image_description_jobs';
            $jobs_sql = "CREATE TABLE $jobs_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                batch_id varchar(255) NOT NULL,
                attachment_id bigint(20) NOT NULL,
                status enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
                generated_description text,
                original_alt_text text,
                error_message text,
                retry_count int(11) DEFAULT 0,
                processed_at datetime NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY batch_id_idx (batch_id),
                KEY attachment_id_idx (attachment_id),
                KEY status_idx (status)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Create tables
            $result1 = dbDelta($batches_sql);
            $result2 = dbDelta($jobs_sql);
            
            // Log results
            error_log('WP Image Descriptions: Table creation results:');
            error_log('Batches table: ' . print_r($result1, true));
            error_log('Jobs table: ' . print_r($result2, true));
            
            // Verify tables were created
            $batches_exists = $wpdb->get_var("SHOW TABLES LIKE '$batches_table'") === $batches_table;
            $jobs_exists = $wpdb->get_var("SHOW TABLES LIKE '$jobs_table'") === $jobs_table;
            
            error_log('WP Image Descriptions: Table verification:');
            error_log('Batches table exists: ' . ($batches_exists ? 'yes' : 'no'));
            error_log('Jobs table exists: ' . ($jobs_exists ? 'yes' : 'no'));
            
            if ($batches_exists && $jobs_exists) {
                // Update database version
                update_option('wp_image_descriptions_db_version', $target_db_version);
                error_log('WP Image Descriptions: Database tables created successfully');
            } else {
                error_log('WP Image Descriptions: Failed to create database tables');
            }
        } else {
            error_log('WP Image Descriptions: Database tables already up to date (version ' . $current_db_version . ')');
        }
    }
    
    /**
     * Remove database tables
     */
    private static function remove_database_tables() {
        global $wpdb;
        
        $batches_table = $wpdb->prefix . 'image_description_batches';
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        
        $wpdb->query("DROP TABLE IF EXISTS $jobs_table");
        $wpdb->query("DROP TABLE IF EXISTS $batches_table");
        
        delete_option('wp_image_descriptions_db_version');
        
        error_log('WP Image Descriptions: Database tables removed');
    }
    
    /**
     * Force recreate database tables (for troubleshooting)
     */
    public function force_recreate_tables() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        error_log('WP Image Descriptions: Force recreating database tables');
        
        // Remove existing tables
        self::remove_database_tables();
        
        // Recreate tables
        $this->create_database_tables();
        
        return true;
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_settings = array(
            'api' => array(
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'api_key' => '',
                'model' => 'gpt-4-vision-preview',
                'max_tokens' => 300,
                'temperature' => 0.7
            ),
            'processing' => array(
                'batch_size' => 5,
                'rate_limit_delay' => 1,
                'max_retries' => 3,
                'timeout' => 30
            ),
            'prompts' => array(
                'default_template' => 'Describe this image for accessibility purposes. Focus on the main subject, important details, and any text visible in the image. Keep the description concise but informative.'
            )
        );
        
        // Only set if not already exists
        if (!get_option('wp_image_descriptions_settings')) {
            update_option('wp_image_descriptions_settings', $default_settings);
        }
        
        // Store plugin version
        update_option('wp_image_descriptions_version', WP_IMAGE_DESCRIPTIONS_VERSION);
    }
    
    /**
     * Get plugin updater instance
     */
    public function get_updater() {
        return $this->plugin_updater;
    }
    
    /**
     * Get plugin version info
     */
    public function get_version_info() {
        if ($this->plugin_updater) {
            return $this->plugin_updater->get_version_info();
        }
        
        return array(
            'version' => WP_IMAGE_DESCRIPTIONS_VERSION,
            'type' => 'unknown'
        );
    }
    
    /**
     * Force check for updates (admin only)
     */
    public function force_update_check() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if ($this->plugin_updater) {
            return $this->plugin_updater->force_update_check();
        }
        
        return false;
    }
}

/**
 * Initialize the plugin
 */
if (!function_exists('wp_image_descriptions_init')) {
    function wp_image_descriptions_init() {
        return WP_Image_Descriptions::get_instance();
    }
    
    // Start the plugin
    wp_image_descriptions_init();
} else {
    // If function already exists, it means another version is loaded
    // Add admin notice about multiple versions
    add_action('admin_notices', function() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>WordPress Image Descriptions:</strong> Multiple plugin versions detected. Please deactivate and delete the old version before activating the new one.</p>';
            echo '<p><a href="' . admin_url('plugins.php') . '" class="button">Go to Plugins Page</a></p>';
            echo '</div>';
        }
    });
}
