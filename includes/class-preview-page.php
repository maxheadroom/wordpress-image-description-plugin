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
        } elseif ($batch->status === 'completed') {
            // Processing is done but not yet applied - this shouldn't happen in production mode
            // but let's handle it gracefully by showing results
            $batch->status = 'show_results';
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Processing Images', 'wp-image-descriptions'); ?></h1>
            
            <?php $this->display_batch_info($batch); ?>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <h2><?php esc_html_e('Processing Status', 'wp-image-descriptions'); ?></h2>
                
                <?php
                // Get real-time progress from database
                $batch_manager = new WP_Image_Descriptions_Batch_Manager();
                $current_progress = $batch_manager->get_batch_progress($batch_id);
                
                $total = $current_progress['total'];
                $completed = $current_progress['completed'];
                $failed = $current_progress['failed'];
                $processed = $completed + $failed;
                $percentage = $current_progress['percentage'];
                $current_status = $current_progress['status'];
                ?>
                
                <?php if ($current_status === 'pending' || $current_status === 'processing'): ?>
                    <p><?php esc_html_e('Your images are being processed and descriptions will be applied automatically when complete.', 'wp-image-descriptions'); ?></p>
                    
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
                    
                <?php elseif ($current_status === 'completed'): ?>
                    <p style="color: #46b450; font-weight: bold;">
                        <?php esc_html_e('✅ Processing complete! Applying descriptions to your media library...', 'wp-image-descriptions'); ?>
                    </p>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 100%;"></div>
                    </div>
                    
                    <p style="text-align: center; margin-top: 10px;">
                        <strong><?php echo sprintf(__('Completed: %d of %d images (100%%)', 'wp-image-descriptions'), $total, $total); ?></strong>
                    </p>
                    
                <?php elseif ($current_status === 'applied'): ?>
                    <p style="color: #46b450; font-weight: bold;">
                        <?php esc_html_e('✅ All done! Descriptions have been applied to your media library.', 'wp-image-descriptions'); ?>
                    </p>
                    
                    <script>
                    // Redirect to media library after showing success message
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_js(admin_url('upload.php?mode=list&wp_image_descriptions_message=batch_completed&descriptions_applied=' . $completed)); ?>';
                    }, 2000);
                    </script>
                    
                <?php elseif ($current_status === 'failed'): ?>
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
        <?php 
        // Get current status for JavaScript
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $current_progress = $batch_manager->get_batch_progress($batch_id);
        $js_status = $current_progress['status'];
        $js_percentage = $current_progress['percentage'];
        ?>
        
        var currentStatus = '<?php echo esc_js($js_status); ?>';
        var currentPercentage = <?php echo intval($js_percentage); ?>;
        
        console.log('Current batch status:', currentStatus, 'Progress:', currentPercentage + '%');
        
        // Only refresh if still processing
        if (currentStatus === 'pending' || currentStatus === 'processing') {
            console.log('Batch still processing, will refresh in 3 seconds');
            setTimeout(function() {
                location.reload();
            }, 3000);
        } else if (currentStatus === 'completed') {
            console.log('Batch completed, will refresh once more to apply results');
            setTimeout(function() {
                location.reload();
            }, 2000);
        } else if (currentStatus === 'applied') {
            console.log('Batch applied, redirecting to media library');
            setTimeout(function() {
                window.location.href = '<?php echo esc_js(admin_url('upload.php?mode=list&wp_image_descriptions_message=batch_completed&descriptions_applied=' . $current_progress['completed'])); ?>';
            }, 2000);
        } else {
            console.log('Batch processing finished with status:', currentStatus);
        }
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
            
            <?php 
            // Get real-time batch progress to determine what to show
            $batch_manager = new WP_Image_Descriptions_Batch_Manager();
            $current_progress = $batch_manager->get_batch_progress($batch_id);
            
            // Show processing status if still pending/processing, otherwise show results
            if ($current_progress['status'] === 'pending' || $current_progress['status'] === 'processing'): ?>
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
        // Auto-refresh logic for test mode
        <?php
        // Get real-time progress for JavaScript
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $current_progress = $batch_manager->get_batch_progress($batch_id);
        $is_processing_view = ($current_progress['status'] === 'pending' || $current_progress['status'] === 'processing');
        ?>
        
        <?php if ($is_processing_view): ?>
            var currentStatus = '<?php echo esc_js($current_progress['status']); ?>';
            var currentPercentage = <?php echo intval($current_progress['percentage']); ?>;
            var totalJobs = <?php echo intval($current_progress['total']); ?>;
            var completedJobs = <?php echo intval($current_progress['completed']); ?>;
            var failedJobs = <?php echo intval($current_progress['failed']); ?>;
            var processedJobs = completedJobs + failedJobs;
            
            console.log('Test mode - Status:', currentStatus, 'Progress:', currentPercentage + '%', 'Processed:', processedJobs + '/' + totalJobs);
            
            // Check if processing is actually complete
            if (processedJobs >= totalJobs && totalJobs > 0) {
                console.log('Test mode - All jobs processed, refreshing to show results');
                setTimeout(function() {
                    location.reload();
                }, 500); // Quick refresh to show results
            } else if (currentStatus === 'pending' || currentStatus === 'processing') {
                console.log('Test mode - Still processing, will refresh in 4 seconds');
                setTimeout(function() {
                    location.reload();
                }, 4000); // Refresh every 4 seconds
            } else {
                console.log('Test mode - Status changed to:', currentStatus, 'refreshing to show results');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        <?php else: ?>
            console.log('Test mode - Showing results page, no auto-refresh needed');
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
        // Get real-time progress
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $progress = $batch_manager->get_batch_progress($batch_id);
        
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
            <h2><?php esc_html_e('Processing Status', 'wp-image-descriptions'); ?></h2>
            
            <?php if ($progress['status'] === 'pending' || $progress['status'] === 'processing'): ?>
                <p><?php esc_html_e('Your images are being processed. This page will automatically refresh to show results.', 'wp-image-descriptions'); ?></p>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo esc_attr($progress['percentage']); ?>%;"></div>
                </div>
                
                <p style="text-align: center; margin-top: 10px;">
                    <strong>
                        <?php if ($progress['processed'] > 0): ?>
                            <?php echo sprintf(__('Processing: %d of %d images (%s%%)', 'wp-image-descriptions'), $progress['processed'], $progress['total'], $progress['percentage']); ?>
                        <?php else: ?>
                            <?php esc_html_e('Processing images...', 'wp-image-descriptions'); ?>
                        <?php endif; ?>
                    </strong>
                </p>
                
                <?php if ($progress['completed'] > 0): ?>
                    <p style="text-align: center; color: #46b450;">
                        <?php echo sprintf(__('✅ %d descriptions generated', 'wp-image-descriptions'), $progress['completed']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($progress['failed'] > 0): ?>
                    <p style="text-align: center; color: #dc3232;">
                        <?php echo sprintf(__('❌ %d images failed', 'wp-image-descriptions'), $progress['failed']); ?>
                    </p>
                <?php endif; ?>
                
            <?php else: ?>
                <p style="color: #46b450; font-weight: bold;">
                    <?php esc_html_e('✅ Processing complete! Loading results...', 'wp-image-descriptions'); ?>
                </p>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 100%;"></div>
                </div>
                
                <script>
                // Refresh immediately to show results
                setTimeout(function() {
                    location.reload();
                }, 1000);
                </script>
            <?php endif; ?>
            
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
        // Get fresh job data from database to ensure we have the latest results
        $batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $fresh_batch_details = $batch_manager->get_batch_details($batch->batch_id);
        
        if ($fresh_batch_details) {
            $jobs = $fresh_batch_details['jobs']; // Use fresh job data
        }
        
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
                <div style="background: #d4edda; padding: 15px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #155724;">
                        <?php echo sprintf(__('✅ Success! Generated %d descriptions', 'wp-image-descriptions'), count($completed_jobs)); ?>
                    </h3>
                    <p style="margin-bottom: 0; color: #155724;">
                        <?php esc_html_e('Review the descriptions below and edit them if needed, then click "Apply Descriptions" to save them to your media library.', 'wp-image-descriptions'); ?>
                    </p>
                </div>
                
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
                    <h3 style="color: #dc3232;"><?php esc_html_e('Failed Images', 'wp-image-descriptions'); ?></h3>
                    <div style="background: #f8d7da; padding: 15px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                        <p style="margin: 0; color: #721c24;">
                            <?php echo sprintf(__('❌ %d images failed to process. See details below.', 'wp-image-descriptions'), count($failed_jobs)); ?>
                        </p>
                    </div>
                    <?php foreach ($failed_jobs as $job): ?>
                        <?php $this->render_failed_image($job); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($completed_jobs) && empty($failed_jobs)): ?>
                <div style="text-align: center; padding: 40px;">
                    <h3><?php esc_html_e('No Results Available', 'wp-image-descriptions'); ?></h3>
                    <p><?php esc_html_e('The batch processing may still be in progress or there may have been an issue.', 'wp-image-descriptions'); ?></p>
                    <button type="button" class="button button-primary" onclick="location.reload();">
                        <?php esc_html_e('Refresh Page', 'wp-image-descriptions'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>" class="button" style="margin-left: 10px;">
                        <?php esc_html_e('Back to Media Library', 'wp-image-descriptions'); ?>
                    </a>
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
            
            error_log('WP Image Descriptions: Applying descriptions for batch ' . $batch_id . ' with ' . count($descriptions) . ' descriptions');
            
            if (empty($descriptions)) {
                error_log('WP Image Descriptions: No descriptions to apply, redirecting back to preview');
                $redirect_url = admin_url('admin.php?page=wp-image-descriptions-preview&batch_id=' . urlencode($batch_id) . '&message=no_descriptions');
                wp_redirect($redirect_url);
                exit;
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
                        error_log('WP Image Descriptions: Applied description to attachment ' . $attachment_id);
                    } else {
                        $errors[] = sprintf(__('Failed to update attachment %d', 'wp-image-descriptions'), $attachment_id);
                        error_log('WP Image Descriptions: Failed to update attachment ' . $attachment_id);
                    }
                }
            }
            
            // Update batch status
            global $wpdb;
            $batch_table = $wpdb->prefix . 'image_description_batches';
            $update_result = $wpdb->update(
                $batch_table,
                array('status' => 'applied', 'updated_at' => current_time('mysql')),
                array('batch_id' => $batch_id),
                array('%s', '%s'),
                array('%s')
            );
            
            error_log('WP Image Descriptions: Updated batch status, result: ' . ($update_result !== false ? 'success' : 'failed'));
            error_log('WP Image Descriptions: Applied ' . $applied_count . ' descriptions successfully');
            
            // Build redirect URL
            $redirect_url = admin_url('upload.php');
            $redirect_url = add_query_arg('mode', 'list', $redirect_url);
            $redirect_url = add_query_arg('wp_image_descriptions_message', 'batch_completed', $redirect_url);
            $redirect_url = add_query_arg('descriptions_applied', $applied_count, $redirect_url);
            
            if (!empty($errors)) {
                $redirect_url = add_query_arg('apply_errors', count($errors), $redirect_url);
                error_log('WP Image Descriptions: Errors applying descriptions: ' . implode('; ', $errors));
            }
            
            error_log('WP Image Descriptions: Redirecting to: ' . $redirect_url);
            
            // Ensure no output before redirect
            if (!headers_sent()) {
                wp_redirect($redirect_url);
                exit;
            } else {
                error_log('WP Image Descriptions: Headers already sent, cannot redirect');
                // Fallback: JavaScript redirect
                echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                echo '<p>Redirecting... <a href="' . esc_url($redirect_url) . '">Click here if not redirected automatically</a></p>';
                exit;
            }
        }
    }
}
