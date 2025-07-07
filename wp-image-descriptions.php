<?php
/**
 * Plugin Name: WordPress Image Descriptions
 * Plugin URI: https://github.com/your-username/wp-image-descriptions
 * Description: Generate AI-powered image descriptions for accessibility using OpenAI-compatible APIs. Helps create alt text for visually impaired users.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-image-descriptions
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_IMAGE_DESCRIPTIONS_VERSION', '1.0.0');
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
        
        // Batches table
        $batches_table = $wpdb->prefix . 'image_description_batches';
        $batches_sql = "CREATE TABLE $batches_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            batch_id varchar(255) NOT NULL UNIQUE,
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
            KEY batch_id (batch_id),
            KEY user_id (user_id),
            KEY status (status)
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
            KEY batch_id (batch_id),
            KEY attachment_id (attachment_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($batches_sql);
        dbDelta($jobs_sql);
        
        // Store database version
        update_option('wp_image_descriptions_db_version', '1.0.0');
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
}

/**
 * Initialize the plugin
 */
function wp_image_descriptions_init() {
    return WP_Image_Descriptions::get_instance();
}

// Start the plugin
wp_image_descriptions_init();
