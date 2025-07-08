<?php
/**
 * Batch Manager class
 * 
 * Handles batch creation, tracking, and management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Batch_Manager {
    
    /**
     * Create new batch
     */
    public function create_batch($attachment_ids, $mode = 'test') {
        global $wpdb;
        
        error_log('WP Image Descriptions: create_batch called with ' . count($attachment_ids) . ' attachment IDs, mode: ' . $mode);
        error_log('WP Image Descriptions: Attachment IDs: ' . print_r($attachment_ids, true));
        
        // Validate input
        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            error_log('WP Image Descriptions: create_batch failed - no attachment IDs provided');
            return array(
                'success' => false,
                'batch_id' => '',
                'error' => 'No attachment IDs provided'
            );
        }
        
        // Filter valid image attachments
        $valid_attachment_ids = $this->validate_attachments($attachment_ids);
        error_log('WP Image Descriptions: Valid attachment IDs after validation: ' . print_r($valid_attachment_ids, true));
        
        if (empty($valid_attachment_ids)) {
            error_log('WP Image Descriptions: create_batch failed - no valid image attachments found');
            return array(
                'success' => false,
                'batch_id' => '',
                'error' => 'No valid image attachments found'
            );
        }
        
        // Generate unique batch ID
        $batch_id = $this->generate_batch_id();
        error_log('WP Image Descriptions: Generated batch ID: ' . $batch_id);
        
        // Get current user
        $user_id = get_current_user_id();
        error_log('WP Image Descriptions: Current user ID: ' . $user_id);
        
        // Get current settings
        $settings = get_option('wp_image_descriptions_settings', array());
        error_log('WP Image Descriptions: Settings loaded: ' . (empty($settings) ? 'empty' : 'not empty'));
        
        // Check if tables exist
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$batch_table'") === $batch_table;
        error_log('WP Image Descriptions: Batch table exists: ' . ($table_exists ? 'yes' : 'no'));
        
        if (!$table_exists) {
            error_log('WP Image Descriptions: create_batch failed - batch table does not exist');
            return array(
                'success' => false,
                'batch_id' => '',
                'error' => 'Database tables not found. Please deactivate and reactivate the plugin.'
            );
        }
        
        // Create batch record
        $batch_data = array(
            'batch_id' => $batch_id,
            'user_id' => $user_id,
            'mode' => $mode,
            'status' => 'pending',
            'total_jobs' => count($valid_attachment_ids),
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'settings' => wp_json_encode($settings),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        error_log('WP Image Descriptions: Inserting batch data: ' . print_r($batch_data, true));
        
        $batch_result = $wpdb->insert(
            $batch_table,
            $batch_data,
            array('%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        error_log('WP Image Descriptions: Batch insert result: ' . ($batch_result === false ? 'FAILED' : 'SUCCESS'));
        
        if ($batch_result === false) {
            error_log('WP Image Descriptions: Failed to create batch record. MySQL error: ' . $wpdb->last_error);
            error_log('WP Image Descriptions: Last query: ' . $wpdb->last_query);
            return array(
                'success' => false,
                'batch_id' => '',
                'error' => 'Failed to create batch record: ' . $wpdb->last_error
            );
        }
        
        // Create individual jobs
        $jobs_created = $this->create_batch_jobs($batch_id, $valid_attachment_ids);
        error_log('WP Image Descriptions: Jobs creation result: ' . print_r($jobs_created, true));
        
        if (!$jobs_created['success']) {
            // Clean up batch record if job creation failed
            error_log('WP Image Descriptions: Cleaning up batch record due to job creation failure');
            $wpdb->delete($batch_table, array('batch_id' => $batch_id), array('%s'));
            
            return array(
                'success' => false,
                'batch_id' => '',
                'error' => $jobs_created['error']
            );
        }
        
        error_log('WP Image Descriptions: Successfully created batch ' . $batch_id . ' with ' . count($valid_attachment_ids) . ' jobs');
        
        return array(
            'success' => true,
            'batch_id' => $batch_id,
            'total_jobs' => count($valid_attachment_ids),
            'mode' => $mode
        );
    }
    
    /**
     * Get batch progress
     */
    public function get_batch_progress($batch_id) {
        global $wpdb;
        
        if (empty($batch_id)) {
            return array(
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
                'percentage' => 0,
                'status' => 'not_found'
            );
        }
        
        // Get batch info
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $batch_table WHERE batch_id = %s",
            $batch_id
        ));
        
        if (!$batch) {
            return array(
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
                'percentage' => 0,
                'status' => 'not_found'
            );
        }
        
        // Calculate percentage
        $total = intval($batch->total_jobs);
        $completed = intval($batch->completed_jobs);
        $failed = intval($batch->failed_jobs);
        $processed = $completed + $failed;
        $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
        
        return array(
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'processed' => $processed,
            'percentage' => $percentage,
            'status' => $batch->status,
            'mode' => $batch->mode,
            'created_at' => $batch->created_at
        );
    }
    
    /**
     * Apply batch results to media library
     */
    public function apply_batch_results($batch_id) {
        global $wpdb;
        
        if (empty($batch_id)) {
            return array(
                'success' => false,
                'applied' => 0,
                'error' => 'No batch ID provided'
            );
        }
        
        // Get completed jobs
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        $completed_jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $jobs_table WHERE batch_id = %s AND status = 'completed' AND generated_description != ''",
            $batch_id
        ));
        
        if (empty($completed_jobs)) {
            return array(
                'success' => false,
                'applied' => 0,
                'error' => 'No completed jobs found for this batch'
            );
        }
        
        $applied_count = 0;
        $errors = array();
        
        foreach ($completed_jobs as $job) {
            // Apply description to attachment
            $result = $this->apply_description_to_attachment(
                $job->attachment_id,
                $job->generated_description
            );
            
            if ($result['success']) {
                $applied_count++;
                
                // Update job status
                $wpdb->update(
                    $jobs_table,
                    array('status' => 'applied', 'updated_at' => current_time('mysql')),
                    array('id' => $job->id),
                    array('%s', '%s'),
                    array('%d')
                );
            } else {
                $errors[] = 'Failed to apply description for attachment ' . $job->attachment_id . ': ' . $result['error'];
            }
        }
        
        // Update batch status
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $wpdb->update(
            $batch_table,
            array('status' => 'applied', 'updated_at' => current_time('mysql')),
            array('batch_id' => $batch_id),
            array('%s', '%s'),
            array('%s')
        );
        
        if (!empty($errors)) {
            error_log('WP Image Descriptions: Errors applying batch results: ' . implode('; ', $errors));
        }
        
        return array(
            'success' => true,
            'applied' => $applied_count,
            'errors' => $errors
        );
    }
    
    /**
     * Get batch details
     */
    public function get_batch_details($batch_id) {
        global $wpdb;
        
        if (empty($batch_id)) {
            return null;
        }
        
        // Get batch info
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $batch_table WHERE batch_id = %s",
            $batch_id
        ));
        
        if (!$batch) {
            return null;
        }
        
        // Get jobs
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $jobs_table WHERE batch_id = %s ORDER BY created_at ASC",
            $batch_id
        ));
        
        return array(
            'batch' => $batch,
            'jobs' => $jobs
        );
    }
    
    /**
     * Validate attachment IDs
     */
    private function validate_attachments($attachment_ids) {
        $valid_ids = array();
        
        error_log('WP Image Descriptions: validate_attachments called with: ' . print_r($attachment_ids, true));
        
        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            error_log('WP Image Descriptions: Validating attachment ID: ' . $attachment_id);
            
            // Check if attachment exists
            $post = get_post($attachment_id);
            if (!$post) {
                error_log('WP Image Descriptions: Attachment ' . $attachment_id . ' does not exist');
                continue;
            }
            
            // Check if it's an attachment
            if (get_post_type($attachment_id) !== 'attachment') {
                error_log('WP Image Descriptions: Post ' . $attachment_id . ' is not an attachment, type: ' . get_post_type($attachment_id));
                continue;
            }
            
            // Check if it's an image
            if (!wp_attachment_is_image($attachment_id)) {
                error_log('WP Image Descriptions: Attachment ' . $attachment_id . ' is not an image');
                continue;
            }
            
            // Check if image URL is accessible
            $image_url = wp_get_attachment_url($attachment_id);
            if (!$image_url) {
                error_log('WP Image Descriptions: Attachment ' . $attachment_id . ' has no URL');
                continue;
            }
            
            error_log('WP Image Descriptions: Attachment ' . $attachment_id . ' is valid, URL: ' . $image_url);
            $valid_ids[] = $attachment_id;
        }
        
        error_log('WP Image Descriptions: validate_attachments returning ' . count($valid_ids) . ' valid IDs: ' . print_r($valid_ids, true));
        
        return $valid_ids;
    }
    
    /**
     * Generate unique batch ID
     */
    private function generate_batch_id() {
        return 'batch_' . time() . '_' . wp_generate_password(8, false);
    }
    
    /**
     * Create individual jobs for batch
     */
    private function create_batch_jobs($batch_id, $attachment_ids) {
        global $wpdb;
        
        error_log('WP Image Descriptions: create_batch_jobs called for batch ' . $batch_id . ' with ' . count($attachment_ids) . ' attachments');
        
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        
        // Check if jobs table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$jobs_table'") === $jobs_table;
        error_log('WP Image Descriptions: Jobs table exists: ' . ($table_exists ? 'yes' : 'no'));
        
        if (!$table_exists) {
            error_log('WP Image Descriptions: create_batch_jobs failed - jobs table does not exist');
            return array(
                'success' => false,
                'error' => 'Jobs table does not exist'
            );
        }
        
        $jobs_created = 0;
        
        foreach ($attachment_ids as $attachment_id) {
            error_log('WP Image Descriptions: Creating job for attachment ' . $attachment_id);
            
            // Get existing alt text
            $existing_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            error_log('WP Image Descriptions: Existing alt text for ' . $attachment_id . ': ' . ($existing_alt_text ? $existing_alt_text : 'none'));
            
            // Create job record
            $job_data = array(
                'batch_id' => $batch_id,
                'attachment_id' => $attachment_id,
                'status' => 'pending',
                'generated_description' => '',
                'original_alt_text' => $existing_alt_text,
                'error_message' => '',
                'retry_count' => 0,
                'processed_at' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            error_log('WP Image Descriptions: Inserting job data: ' . print_r($job_data, true));
            
            $result = $wpdb->insert(
                $jobs_table,
                $job_data,
                array('%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $jobs_created++;
                error_log('WP Image Descriptions: Successfully created job for attachment ' . $attachment_id);
            } else {
                error_log('WP Image Descriptions: Failed to create job for attachment ' . $attachment_id . ': ' . $wpdb->last_error);
                error_log('WP Image Descriptions: Last query: ' . $wpdb->last_query);
            }
        }
        
        error_log('WP Image Descriptions: Created ' . $jobs_created . ' out of ' . count($attachment_ids) . ' jobs');
        
        if ($jobs_created === 0) {
            return array(
                'success' => false,
                'error' => 'Failed to create any jobs'
            );
        }
        
        if ($jobs_created < count($attachment_ids)) {
            error_log('WP Image Descriptions: Only created ' . $jobs_created . ' out of ' . count($attachment_ids) . ' jobs');
        }
        
        return array(
            'success' => true,
            'jobs_created' => $jobs_created
        );
    }
    
    /**
     * Apply description to attachment
     */
    private function apply_description_to_attachment($attachment_id, $description) {
        if (empty($description)) {
            return array(
                'success' => false,
                'error' => 'Empty description provided'
            );
        }
        
        // Update alt text
        $result = update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($description));
        
        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Failed to update attachment meta'
            );
        }
        
        return array(
            'success' => true
        );
    }
    
    /**
     * Clean up old batches
     */
    public function cleanup_old_batches($days_old = 30) {
        global $wpdb;
        
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        
        // Get old batch IDs
        $old_batches = $wpdb->get_col($wpdb->prepare(
            "SELECT batch_id FROM $batch_table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));
        
        if (empty($old_batches)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($old_batches), '%s'));
        
        // Delete jobs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $jobs_table WHERE batch_id IN ($placeholders)",
            ...$old_batches
        ));
        
        // Delete batches
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $batch_table WHERE batch_id IN ($placeholders)",
            ...$old_batches
        ));
        
        return $deleted;
    }
}
