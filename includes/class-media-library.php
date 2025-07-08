<?php
/**
 * Media Library integration class
 * 
 * Handles bulk actions and media library interface modifications
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Media_Library {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register hooks directly in constructor for WordPress 6.8+ compatibility
        add_action('admin_init', array($this, 'init_media_library_hooks'));
        add_action('current_screen', array($this, 'init_screen_specific_hooks'));
    }
    
    /**
     * Initialize media library hooks
     */
    public function init_media_library_hooks() {
        // Only add hooks on media library pages
        global $pagenow;
        
        if ($pagenow === 'upload.php') {
            add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'), 20);
            add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 20, 3);
            add_filter('manage_media_columns', array($this, 'add_media_columns'), 20);
            add_action('manage_media_custom_column', array($this, 'display_media_column'), 20, 2);
            add_action('restrict_manage_posts', array($this, 'add_alt_text_filter'), 20);
            add_filter('request', array($this, 'filter_media_by_alt_text'), 20);
            add_action('admin_head', array($this, 'add_media_library_styles'), 20);
            
            error_log('WP Image Descriptions: Media library hooks registered on upload.php');
        }
    }
    
    /**
     * Initialize screen-specific hooks
     */
    public function init_screen_specific_hooks($current_screen) {
        if ($current_screen && $current_screen->id === 'upload') {
            add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'), 30);
            add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 30, 3);
            
            error_log('WP Image Descriptions: Screen-specific hooks registered for upload screen');
        }
    }
    
    /**
     * Add bulk actions to media library
     */
    public function add_bulk_actions($bulk_actions) {
        // Enhanced debugging
        error_log('WP Image Descriptions: add_bulk_actions called');
        error_log('WP Image Descriptions: Current screen: ' . (isset($GLOBALS['current_screen']) ? $GLOBALS['current_screen']->id : 'unknown'));
        error_log('WP Image Descriptions: Current page: ' . (isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : 'unknown'));
        error_log('WP Image Descriptions: User can edit_posts: ' . (current_user_can('edit_posts') ? 'yes' : 'no'));
        error_log('WP Image Descriptions: Existing bulk actions: ' . print_r(array_keys($bulk_actions), true));
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            error_log('WP Image Descriptions: User does not have edit_posts capability');
            return $bulk_actions;
        }
        
        // Add our bulk actions
        $bulk_actions['generate_descriptions_test'] = __('Generate Descriptions (Test Mode)', 'wp-image-descriptions');
        $bulk_actions['generate_descriptions_production'] = __('Generate Descriptions (Apply Directly)', 'wp-image-descriptions');
        
        error_log('WP Image Descriptions: Bulk actions added. New actions: ' . print_r(array_keys($bulk_actions), true));
        
        return $bulk_actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        // Check if this is our bulk action
        if (!in_array($doaction, array('generate_descriptions_test', 'generate_descriptions_production'))) {
            return $redirect_to;
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            return add_query_arg('wp_image_descriptions_message', 'insufficient_permissions', $redirect_to);
        }
        
        // Validate API configuration before processing
        $api_validation = $this->validate_api_configuration();
        if (!$api_validation['valid']) {
            return add_query_arg('wp_image_descriptions_message', $api_validation['error_code'], $redirect_to);
        }
        
        // Validate post IDs
        if (empty($post_ids) || !is_array($post_ids)) {
            return add_query_arg('wp_image_descriptions_message', 'no_images_selected', $redirect_to);
        }
        
        // Filter for image attachments only
        $image_ids = $this->filter_image_attachments($post_ids);
        
        if (empty($image_ids)) {
            return add_query_arg('wp_image_descriptions_message', 'no_images_found', $redirect_to);
        }
        
        // Determine mode
        $mode = ($doaction === 'generate_descriptions_test') ? 'test' : 'production';
        
        // Create batch
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $result = $batch_manager->create_batch($image_ids, $mode);
        
        if (!$result['success']) {
            return add_query_arg('wp_image_descriptions_message', 'batch_creation_failed', $redirect_to);
        }
        
        // Redirect based on mode
        if ($mode === 'test') {
            // For test mode, start processing in background and redirect to preview
            wp_schedule_single_event(time() + 1, 'wp_image_descriptions_process_batch', array($result['batch_id']));
            
            // Redirect to preview page
            $preview_url = admin_url('admin.php?page=wp-image-descriptions-preview&batch_id=' . $result['batch_id']);
            return $preview_url;
        } else {
            // Production mode: process and apply immediately
            wp_schedule_single_event(time() + 1, 'wp_image_descriptions_process_batch_production', array($result['batch_id']));
            
            // Redirect to a processing status page
            $processing_url = admin_url('admin.php?page=wp-image-descriptions-processing&batch_id=' . $result['batch_id']);
            return $processing_url;
        }
    }
    
    /**
     * Add custom columns to media library
     */
    public function add_media_columns($columns) {
        // Add alt text status column
        $columns['alt_text_status'] = __('Alt Text', 'wp-image-descriptions');
        return $columns;
    }
    
    /**
     * Display custom column content
     */
    public function display_media_column($column_name, $post_id) {
        if ($column_name === 'alt_text_status') {
            // Only show for image attachments
            if (!wp_attachment_is_image($post_id)) {
                echo '<span class="dashicons dashicons-minus" style="color: #ccc;" title="' . esc_attr__('Not an image', 'wp-image-descriptions') . '"></span>';
                return;
            }
            
            $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
            
            if (!empty($alt_text)) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__('Has alt text', 'wp-image-descriptions') . '"></span>';
                echo '<div class="alt-text-preview" style="font-size: 11px; color: #666; margin-top: 2px;">';
                echo esc_html(wp_trim_words($alt_text, 8, '...'));
                echo '</div>';
            } else {
                echo '<span class="dashicons dashicons-warning" style="color: #ffb900;" title="' . esc_attr__('Missing alt text', 'wp-image-descriptions') . '"></span>';
                echo '<div style="font-size: 11px; color: #d63638; margin-top: 2px;">';
                echo esc_html__('No alt text', 'wp-image-descriptions');
                echo '</div>';
            }
        }
    }
    
    /**
     * Add alt text filter dropdown
     */
    public function add_alt_text_filter() {
        global $typenow;
        
        // Only show on media library page
        if ($typenow !== 'attachment') {
            return;
        }
        
        // Get current filter value
        $current_filter = isset($_GET['alt_text_filter']) ? sanitize_text_field($_GET['alt_text_filter']) : '';
        
        ?>
        <select name="alt_text_filter">
            <option value=""><?php esc_html_e('All images', 'wp-image-descriptions'); ?></option>
            <option value="missing" <?php selected($current_filter, 'missing'); ?>>
                <?php esc_html_e('Missing alt text', 'wp-image-descriptions'); ?>
            </option>
            <option value="has_alt" <?php selected($current_filter, 'has_alt'); ?>>
                <?php esc_html_e('Has alt text', 'wp-image-descriptions'); ?>
            </option>
        </select>
        <?php
    }
    
    /**
     * Filter media by alt text status
     */
    public function filter_media_by_alt_text($vars) {
        global $typenow;
        
        // Only apply to media library
        if ($typenow !== 'attachment') {
            return $vars;
        }
        
        // Check if filter is set
        if (!isset($_GET['alt_text_filter']) || empty($_GET['alt_text_filter'])) {
            return $vars;
        }
        
        $filter = sanitize_text_field($_GET['alt_text_filter']);
        
        // Only filter images
        $vars['post_mime_type'] = 'image';
        
        if ($filter === 'missing') {
            // Show images without alt text
            $vars['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                )
            );
        } elseif ($filter === 'has_alt') {
            // Show images with alt text
            $vars['meta_query'] = array(
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '!='
                )
            );
        }
        
        return $vars;
    }
    
    /**
     * Filter image attachments from post IDs
     */
    private function filter_image_attachments($post_ids) {
        $image_ids = array();
        
        foreach ($post_ids as $post_id) {
            // Validate post ID
            $post_id = intval($post_id);
            if ($post_id <= 0) {
                continue;
            }
            
            // Check if it's an attachment
            if (get_post_type($post_id) !== 'attachment') {
                continue;
            }
            
            // Check if it's an image
            if (!wp_attachment_is_image($post_id)) {
                continue;
            }
            
            // Check if image file exists
            $image_url = wp_get_attachment_url($post_id);
            if (!$image_url) {
                continue;
            }
            
            $image_ids[] = $post_id;
        }
        
        return $image_ids;
    }
    
    /**
     * Display admin notices for bulk actions
     */
    public function display_bulk_action_notices() {
        if (!isset($_GET['wp_image_descriptions_message'])) {
            return;
        }
        
        $message_type = sanitize_text_field($_GET['wp_image_descriptions_message']);
        $descriptions_applied = isset($_GET['descriptions_applied']) ? intval($_GET['descriptions_applied']) : 0;
        
        switch ($message_type) {
            case 'insufficient_permissions':
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('You do not have permission to generate image descriptions. Please contact your administrator.', 'wp-image-descriptions') . '</p>';
                echo '</div>';
                break;
                
            case 'no_images_selected':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('Please select at least one image to generate descriptions.', 'wp-image-descriptions') . '</p>';
                echo '</div>';
                break;
                
            case 'no_images_found':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('No valid images found in your selection. Please ensure you have selected image files (JPEG, PNG, GIF, WebP).', 'wp-image-descriptions') . '</p>';
                echo '</div>';
                break;
                
            case 'batch_creation_failed':
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('Failed to create processing batch. This may be due to database issues or plugin configuration problems. Please try again or contact support.', 'wp-image-descriptions') . '</p>';
                echo '<p><a href="' . esc_url(admin_url('tools.php?page=wp-image-descriptions-diagnostics')) . '" class="button button-secondary">' . 
                     esc_html__('Run Diagnostics', 'wp-image-descriptions') . '</a></p>';
                echo '</div>';
                break;
                
            case 'batch_completed':
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                if ($descriptions_applied > 0) {
                    echo sprintf(
                        esc_html__('🎉 Success! Generated and applied descriptions for %d images! Your images are now more accessible.', 'wp-image-descriptions'),
                        $descriptions_applied
                    );
                    
                    // Add additional info about what was accomplished
                    echo '<br><small>' . esc_html__('Alt text has been added to improve accessibility for screen readers and SEO.', 'wp-image-descriptions') . '</small>';
                } else {
                    echo esc_html__('Processing completed successfully!', 'wp-image-descriptions');
                }
                echo '</p>';
                
                // Show error count if any
                if (isset($_GET['apply_errors']) && intval($_GET['apply_errors']) > 0) {
                    $error_count = intval($_GET['apply_errors']);
                    echo '<p style="color: #856404;"><strong>' . esc_html__('Note:', 'wp-image-descriptions') . '</strong> ';
                    echo sprintf(
                        esc_html__('%d images had errors during processing. Check the error logs for details.', 'wp-image-descriptions'),
                        $error_count
                    ) . '</p>';
                }
                
                echo '</div>';
                break;
                
            case 'batch_processing_failed':
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('Failed to process images. This could be due to API configuration issues, network problems, or invalid images.', 'wp-image-descriptions') . '</p>';
                echo '<p><strong>' . esc_html__('Troubleshooting steps:', 'wp-image-descriptions') . '</strong></p>';
                echo '<ul style="margin-left: 20px;">';
                echo '<li>' . esc_html__('Check your API configuration in Settings → Image Descriptions', 'wp-image-descriptions') . '</li>';
                echo '<li>' . esc_html__('Verify your API key is valid and has sufficient credits', 'wp-image-descriptions') . '</li>';
                echo '<li>' . esc_html__('Ensure selected images are in supported formats (JPEG, PNG, GIF, WebP)', 'wp-image-descriptions') . '</li>';
                echo '<li>' . esc_html__('Try processing fewer images at once', 'wp-image-descriptions') . '</li>';
                echo '</ul>';
                echo '<p>';
                echo '<a href="' . esc_url(admin_url('options-general.php?page=wp-image-descriptions-settings')) . '" class="button button-secondary">' . 
                     esc_html__('Check Settings', 'wp-image-descriptions') . '</a> ';
                echo '<a href="' . esc_url(admin_url('tools.php?page=wp-image-descriptions-diagnostics')) . '" class="button button-secondary">' . 
                     esc_html__('Run Diagnostics', 'wp-image-descriptions') . '</a>';
                echo '</p>';
                echo '</div>';
                break;
                
            case 'batch_apply_failed':
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('Images were processed successfully, but failed to apply descriptions to your media library. This may be due to database permissions or WordPress configuration issues.', 'wp-image-descriptions') . '</p>';
                echo '<p>' . esc_html__('You can try the process again, or contact your administrator if the problem persists.', 'wp-image-descriptions') . '</p>';
                echo '</div>';
                break;
                
            case 'api_configuration_missing':
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('API configuration is missing or incomplete. Please configure your OpenAI API settings before generating descriptions.', 'wp-image-descriptions') . '</p>';
                echo '<p><a href="' . esc_url(admin_url('options-general.php?page=wp-image-descriptions-settings')) . '" class="button button-primary">' . 
                     esc_html__('Configure API Settings', 'wp-image-descriptions') . '</a></p>';
                echo '</div>';
                break;
                
            case 'rate_limit_exceeded':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>' . esc_html__('Image Descriptions:', 'wp-image-descriptions') . '</strong> ';
                echo esc_html__('API rate limit exceeded. Please wait a few minutes before trying again, or increase the rate limit delay in your settings.', 'wp-image-descriptions') . '</p>';
                echo '<p><a href="' . esc_url(admin_url('options-general.php?page=wp-image-descriptions-settings')) . '" class="button button-secondary">' . 
                     esc_html__('Adjust Rate Limiting', 'wp-image-descriptions') . '</a></p>';
                echo '</div>';
                break;
        }
    }
    
    /**
     * Add CSS for media library enhancements
     */
    public function add_media_library_styles() {
        global $typenow;
        
        if ($typenow !== 'attachment') {
            return;
        }
        
        ?>
        <style>
        .column-alt_text_status {
            width: 120px;
        }
        
        .alt-text-preview {
            max-width: 100px;
            word-wrap: break-word;
        }
        
        .wp-list-table .column-alt_text_status .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        
        /* Bulk action styling */
        .tablenav .actions select[name="action"] option[value^="generate_descriptions"] {
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * Validate API configuration
     */
    private function validate_api_configuration() {
        $settings = get_option('wp_image_descriptions_settings', array());
        
        // Check if API settings exist
        if (empty($settings['api'])) {
            return array(
                'valid' => false,
                'error_code' => 'api_configuration_missing'
            );
        }
        
        // Check required API fields
        $required_fields = array('endpoint', 'api_key', 'model');
        foreach ($required_fields as $field) {
            if (empty($settings['api'][$field])) {
                return array(
                    'valid' => false,
                    'error_code' => 'api_configuration_missing'
                );
            }
        }
        
        // Validate API endpoint format
        if (!filter_var($settings['api']['endpoint'], FILTER_VALIDATE_URL)) {
            return array(
                'valid' => false,
                'error_code' => 'api_configuration_missing'
            );
        }
        
        return array('valid' => true);
    }
    
    /**
     * Get statistics for dashboard
     */
    public function get_alt_text_statistics() {
        global $wpdb;
        
        // Count total images
        $total_images = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
        ");
        
        // Count images with alt text
        $images_with_alt = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_wp_attachment_image_alt'
            AND pm.meta_value != ''
        ");
        
        // Count images without alt text
        $images_without_alt = $total_images - $images_with_alt;
        
        return array(
            'total' => intval($total_images),
            'with_alt' => intval($images_with_alt),
            'without_alt' => intval($images_without_alt),
            'percentage' => $total_images > 0 ? round(($images_with_alt / $total_images) * 100, 1) : 0
        );
    }
}
