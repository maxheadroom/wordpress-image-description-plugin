<?php
/**
 * Preview Page class
 * 
 * Handles test mode preview page for reviewing generated descriptions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Preview_Page {
    
    /**
     * Add preview page to admin menu
     */
    public function add_preview_page() {
        // Add as a hidden submenu page (not visible in menu but accessible via URL)
        add_submenu_page(
            null, // No parent menu (hidden)
            __('Image Descriptions Preview', 'wp-image-descriptions'),
            __('Image Descriptions Preview', 'wp-image-descriptions'),
            'edit_posts',
            'wp-image-descriptions-preview',
            array($this, 'render_preview_page')
        );
        
        // Add processing status page for production mode
        add_submenu_page(
            null, // No parent menu (hidden)
            __('Image Descriptions Processing', 'wp-image-descriptions'),
            __('Image Descriptions Processing', 'wp-image-descriptions'),
            'edit_posts',
            'wp-image-descriptions-processing',
            array($this, 'render_processing_page')
        );
    }
    
    /**
     * Render processing page for production mode
     */
    public function render_processing_page() {
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-image-descriptions'));
        }
        
        // Get batch ID from URL
        $batch_id = isset($_GET['batch_id']) ? sanitize_text_field($_GET['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_die(__('No batch ID provided.', 'wp-image-descriptions'));
        }
        
        // Get batch details
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $batch_details = $batch_manager->get_batch_details($batch_id);
        
        if (!$batch_details) {
            wp_die(__('Batch not found.', 'wp-image-descriptions'));
        }
        
        $batch = $batch_details['batch'];
        $jobs = $batch_details['jobs'];
        
        // Check if batch belongs to current user (or user is admin)
        if ($batch->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to view this batch.', 'wp-image-descriptions'));
        }
        
        // Check if processing is complete
        if ($batch->status === 'applied') {
            // Redirect to media library with success message
            $redirect_url = admin_url('upload.php?mode=list&wp_image_descriptions_message=batch_completed&descriptions_applied=' . $batch->completed_jobs);
            wp_redirect($redirect_url);
            exit;
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Processing Images', 'wp-image-descriptions'); ?></h1>
            
            <?php $this->display_batch_info($batch); ?>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2><?php esc_html_e('Processing Status', 'wp-image-descriptions'); ?></h2>
                
                <?php if ($batch->status === 'pending' || $batch->status === 'processing'): ?>
                    <p><?php esc_html_e('Your images are being processed and descriptions will be applied automatically when complete.', 'wp-image-descriptions'); ?></p>
                    
                    <?php
                    $total = intval($batch->total_jobs);
                    $completed = intval($batch->completed_jobs);
                    $failed = intval($batch->failed_jobs);
                    $processed = $completed + $failed;
                    $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                    ?>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                    </div>
                    
                    <p style="text-align: center; margin-top: 10px;">
                        <strong><?php echo sprintf(__('Processing: %d of %d images (%s%%)', 'wp-image-descriptions'), $processed, $total, $percentage); ?></strong>
                    </p>
                    
                    <?php if ($completed > 0): ?>
                        <p style="text-align: center; color: #46b450;">
                            <?php echo sprintf(__('✅ %d descriptions generated successfully', 'wp-image-descriptions'), $completed); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($failed > 0): ?>
                        <p style="text-align: center; color: #dc3232;">
                            <?php echo sprintf(__('❌ %d images failed to process', 'wp-image-descriptions'), $failed); ?>
                        </p>
                    <?php endif; ?>
                    
                <?php elseif ($batch->status === 'completed'): ?>
                    <p style="color: #46b450; font-weight: bold;">
                        <?php esc_html_e('✅ Processing complete! Applying descriptions to your media library...', 'wp-image-descriptions'); ?>
                    </p>
                    
                <?php elseif ($batch->status === 'failed'): ?>
                    <p style="color: #dc3232; font-weight: bold;">
                        <?php esc_html_e('❌ Processing failed. Please try again or check your API configuration.', 'wp-image-descriptions'); ?>
                    </p>
                    
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" class="button" onclick="location.reload();">
                        <?php esc_html_e('Refresh Status', 'wp-image-descriptions'); ?>
                    </button>
                    
                    <a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>" class="button" style="margin-left: 10px;">
                        <?php esc_html_e('Back to Media Library', 'wp-image-descriptions'); ?>
                    </a>
                </div>
            </div>
            
            <?php if ($batch->status === 'completed' || $batch->status === 'failed'): ?>
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd;">
                    <h3><?php esc_html_e('Processing Summary', 'wp-image-descriptions'); ?></h3>
                    
                    <?php
                    $completed_jobs = array_filter($jobs, function($job) {
                        return $job->status === 'completed';
                    });
                    $failed_jobs = array_filter($jobs, function($job) {
                        return $job->status === 'failed';
                    });
                    ?>
                    
                    <?php if (!empty($completed_jobs)): ?>
                        <h4 style="color: #46b450;"><?php esc_html_e('Successfully Processed Images', 'wp-image-descriptions'); ?></h4>
                        <ul>
                            <?php foreach ($completed_jobs as $job): ?>
                                <li>
                                    <strong><?php echo esc_html(get_the_title($job->attachment_id) ?: 'Untitled'); ?></strong>
                                    <br><em><?php echo esc_html(wp_trim_words($job->generated_description, 15, '...')); ?></em>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($failed_jobs)): ?>
                        <h4 style="color: #dc3232;"><?php esc_html_e('Failed Images', 'wp-image-descriptions'); ?></h4>
                        <ul>
                            <?php foreach ($failed_jobs as $job): ?>
                                <li>
                                    <strong><?php echo esc_html(get_the_title($job->attachment_id) ?: 'Untitled'); ?></strong>
                                    <?php if (!empty($job->error_message)): ?>
                                        <br><code><?php echo esc_html($job->error_message); ?></code>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #007cba;
            transition: width 0.3s ease;
        }
        </style>
        
        <script>
        // Auto-refresh for pending/processing batches
        <?php if (in_array($batch->status, ['pending', 'processing', 'completed'])): ?>
        setTimeout(function() {
            location.reload();
        }, 3000); // Refresh every 3 seconds for production mode
        <?php endif; ?>
        </script>
        <?php
    }
    
    /**
     * Render preview page
     */
    public function render_preview_page() {
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-image-descriptions'));
        }
        
        // Get batch ID from URL
        $batch_id = isset($_GET['batch_id']) ? sanitize_text_field($_GET['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_die(__('No batch ID provided.', 'wp-image-descriptions'));
        }
        
        // Handle form submissions
        if (isset($_POST['action'])) {
            $this->handle_preview_actions($batch_id);
        }
        
        // Get batch details
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $batch_details = $batch_manager->get_batch_details($batch_id);
        
        if (!$batch_details) {
            wp_die(__('Batch not found.', 'wp-image-descriptions'));
        }
        
        $batch = $batch_details['batch'];
        $jobs = $batch_details['jobs'];
        
        // Check if batch belongs to current user (or user is admin)
        if ($batch->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to view this batch.', 'wp-image-descriptions'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Image Descriptions Preview', 'wp-image-descriptions'); ?></h1>
            
            <?php $this->display_batch_info($batch); ?>
            
            <?php if ($batch->status === 'pending'): ?>
                <?php $this->display_processing_status($batch_id); ?>
            <?php else: ?>
                <?php $this->display_batch_results($batch, $jobs); ?>
            <?php endif; ?>
        </div>
        
        <style>
        .image-preview-item {
            display: flex;
            align-items: flex-start;
            padding: 20px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            background: #fff;
        }
        
        .image-preview-thumbnail {
            flex-shrink: 0;
            margin-right: 20px;
        }
        
        .image-preview-thumbnail img {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #ddd;
        }
        
        .image-preview-content {
            flex-grow: 1;
        }
        
        .image-preview-content h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .description-box {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        
        .batch-actions {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            margin: 20px 0;
            text-align: center;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #007cba;
            transition: width 0.3s ease;
        }
        </style>
        
        <script>
        // Auto-refresh for pending batches
        <?php if ($batch->status === 'pending'): ?>
        setTimeout(function() {
            location.reload();
        }, 5000); // Refresh every 5 seconds
        <?php endif; ?>
        </script>
        <?php
    }
    
    /**
     * Display batch information
     */
    private function display_batch_info($batch) {
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
            <h2><?php esc_html_e('Batch Information', 'wp-image-descriptions'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Batch ID', 'wp-image-descriptions'); ?></strong></td>
                        <td><?php echo esc_html($batch->batch_id); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Mode', 'wp-image-descriptions'); ?></strong></td>
                        <td><?php echo esc_html(ucfirst($batch->mode)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Status', 'wp-image-descriptions'); ?></strong></td>
                        <td><span class="status-badge status-<?php echo esc_attr($batch->status); ?>"><?php echo esc_html(ucfirst($batch->status)); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Total Images', 'wp-image-descriptions'); ?></strong></td>
                        <td><?php echo intval($batch->total_jobs); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Completed', 'wp-image-descriptions'); ?></strong></td>
                        <td><?php echo intval($batch->completed_jobs); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Failed', 'wp-image-descriptions'); ?></strong></td>
                        <td><?php echo intval($batch->failed_jobs); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Created', 'wp-image-descriptions'); ?></strong></td>
                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $batch->created_at)); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display processing status for pending batches
     */
    private function display_processing_status($batch_id) {
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
            <h2><?php esc_html_e('Processing Status', 'wp-image-descriptions'); ?></h2>
            <p><?php esc_html_e('Your images are being processed. This page will automatically refresh to show results.', 'wp-image-descriptions'); ?></p>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;"></div>
            </div>
            
            <p style="text-align: center; margin-top: 10px;">
                <strong><?php esc_html_e('Processing images...', 'wp-image-descriptions'); ?></strong>
            </p>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="button" class="button" onclick="location.reload();">
                    <?php esc_html_e('Refresh Status', 'wp-image-descriptions'); ?>
                </button>
                
                <a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>" class="button">
                    <?php esc_html_e('Back to Media Library', 'wp-image-descriptions'); ?>
                </a>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #007cba;">
                <h4><?php esc_html_e('What happens next?', 'wp-image-descriptions'); ?></h4>
                <ol>
                    <li><?php esc_html_e('Images are being sent to the AI service for description generation', 'wp-image-descriptions'); ?></li>
                    <li><?php esc_html_e('Generated descriptions will appear below when ready', 'wp-image-descriptions'); ?></li>
                    <li><?php esc_html_e('You can review and edit descriptions before applying them', 'wp-image-descriptions'); ?></li>
                    <li><?php esc_html_e('Click "Apply Descriptions" to save them to your media library', 'wp-image-descriptions'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display batch results
     */
    private function display_batch_results($batch, $jobs) {
        $completed_jobs = array_filter($jobs, function($job) {
            return $job->status === 'completed' && !empty($job->generated_description);
        });
        
        $failed_jobs = array_filter($jobs, function($job) {
            return $job->status === 'failed';
        });
        
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
            <h2><?php esc_html_e('Generated Descriptions', 'wp-image-descriptions'); ?></h2>
            
            <?php if (!empty($completed_jobs)): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('apply_descriptions', 'apply_descriptions_nonce'); ?>
                    
                    <?php foreach ($completed_jobs as $job): ?>
                        <?php $this->render_image_preview($job); ?>
                    <?php endforeach; ?>
                    
                    <div class="batch-actions">
                        <input type="hidden" name="action" value="apply_descriptions">
                        <input type="submit" name="apply_descriptions" class="button button-primary button-large" 
                               value="<?php esc_attr_e('Apply Descriptions to Media Library', 'wp-image-descriptions'); ?>">
                        
                        <a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>" class="button button-large" style="margin-left: 10px;">
                            <?php esc_html_e('Cancel', 'wp-image-descriptions'); ?>
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <?php if (!empty($failed_jobs)): ?>
                <div style="margin-top: 30px;">
                    <h3><?php esc_html_e('Failed Images', 'wp-image-descriptions'); ?></h3>
                    <?php foreach ($failed_jobs as $job): ?>
                        <?php $this->render_failed_image($job); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($completed_jobs) && empty($failed_jobs)): ?>
                <div style="text-align: center; padding: 40px;">
                    <p><?php esc_html_e('No results available yet. The batch may still be processing.', 'wp-image-descriptions'); ?></p>
                    <button type="button" class="button" onclick="location.reload();">
                        <?php esc_html_e('Refresh Page', 'wp-image-descriptions'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render individual image preview
     */
    private function render_image_preview($job) {
        $attachment_id = $job->attachment_id;
        $image_url = wp_get_attachment_url($attachment_id);
        $image_title = get_the_title($attachment_id);
        $thumbnail = wp_get_attachment_image($attachment_id, array(150, 150));
        
        ?>
        <div class="image-preview-item">
            <div class="image-preview-thumbnail">
                <?php if ($thumbnail): ?>
                    <?php echo $thumbnail; ?>
                <?php else: ?>
                    <div style="width: 150px; height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd;">
                        <span><?php esc_html_e('No Preview', 'wp-image-descriptions'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="image-preview-content">
                <h3><?php echo esc_html($image_title ?: 'Untitled'); ?></h3>
                
                <p><strong><?php esc_html_e('File:', 'wp-image-descriptions'); ?></strong> 
                   <a href="<?php echo esc_url($image_url); ?>" target="_blank"><?php echo esc_html(basename($image_url)); ?></a>
                </p>
                
                <?php if (!empty($job->original_alt_text)): ?>
                    <p><strong><?php esc_html_e('Original Alt Text:', 'wp-image-descriptions'); ?></strong><br>
                       <em><?php echo esc_html($job->original_alt_text); ?></em>
                    </p>
                <?php endif; ?>
                
                <p><strong><?php esc_html_e('Generated Description:', 'wp-image-descriptions'); ?></strong></p>
                <textarea name="descriptions[<?php echo intval($attachment_id); ?>]" class="description-box"><?php echo esc_textarea($job->generated_description); ?></textarea>
                
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    <?php esc_html_e('You can edit the description above before applying it.', 'wp-image-descriptions'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render failed image
     */
    private function render_failed_image($job) {
        $attachment_id = $job->attachment_id;
        $image_url = wp_get_attachment_url($attachment_id);
        $image_title = get_the_title($attachment_id);
        $thumbnail = wp_get_attachment_image($attachment_id, array(100, 100));
        
        ?>
        <div class="image-preview-item" style="border-left: 4px solid #dc3545;">
            <div class="image-preview-thumbnail">
                <?php if ($thumbnail): ?>
                    <?php echo $thumbnail; ?>
                <?php else: ?>
                    <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd;">
                        <span><?php esc_html_e('No Preview', 'wp-image-descriptions'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="image-preview-content">
                <h4><?php echo esc_html($image_title ?: 'Untitled'); ?></h4>
                <p><strong><?php esc_html_e('Status:', 'wp-image-descriptions'); ?></strong> 
                   <span class="status-badge status-failed"><?php esc_html_e('Failed', 'wp-image-descriptions'); ?></span>
                </p>
                
                <?php if (!empty($job->error_message)): ?>
                    <p><strong><?php esc_html_e('Error:', 'wp-image-descriptions'); ?></strong><br>
                       <code><?php echo esc_html($job->error_message); ?></code>
                    </p>
                <?php endif; ?>
                
                <p><a href="<?php echo esc_url($image_url); ?>" target="_blank"><?php echo esc_html(basename($image_url)); ?></a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle preview page actions
     */
    private function handle_preview_actions($batch_id) {
        if (!wp_verify_nonce($_POST['apply_descriptions_nonce'], 'apply_descriptions')) {
            wp_die(__('Security check failed.', 'wp-image-descriptions'));
        }
        
        if ($_POST['action'] === 'apply_descriptions') {
            $descriptions = isset($_POST['descriptions']) ? $_POST['descriptions'] : array();
            
            if (empty($descriptions)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning"><p>' . esc_html__('No descriptions to apply.', 'wp-image-descriptions') . '</p></div>';
                });
                return;
            }
            
            $applied_count = 0;
            $errors = array();
            
            foreach ($descriptions as $attachment_id => $description) {
                $attachment_id = intval($attachment_id);
                $description = sanitize_text_field($description);
                
                if (!empty($description)) {
                    $result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $description);
                    if ($result !== false) {
                        $applied_count++;
                    } else {
                        $errors[] = sprintf(__('Failed to update attachment %d', 'wp-image-descriptions'), $attachment_id);
                    }
                }
            }
            
            // Update batch status
            global $wpdb;
            $batch_table = $wpdb->prefix . 'image_description_batches';
            $wpdb->update(
                $batch_table,
                array('status' => 'applied', 'updated_at' => current_time('mysql')),
                array('batch_id' => $batch_id),
                array('%s', '%s'),
                array('%s')
            );
            
            // Redirect with success message
            $redirect_url = admin_url('upload.php?mode=list&wp_image_descriptions_message=batch_completed&descriptions_applied=' . $applied_count);
            wp_redirect($redirect_url);
            exit;
        }
    }
}
