<?php
/**
 * Queue Processor class
 * 
 * Handles background processing of image description jobs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Queue_Processor {
    
    /**
     * API Client instance
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new WP_Image_Descriptions_API_Client();
    }
    
    /**
     * Process entire batch
     */
    public function process_batch($batch_id) {
        global $wpdb;
        
        error_log('WP Image Descriptions: process_batch called for batch ' . $batch_id);
        
        if (empty($batch_id)) {
            return array(
                'success' => false,
                'processed' => 0,
                'error' => 'No batch ID provided'
            );
        }
        
        // Get batch info
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $batch_table WHERE batch_id = %s",
            $batch_id
        ));
        
        if (!$batch) {
            error_log('WP Image Descriptions: Batch not found: ' . $batch_id);
            return array(
                'success' => false,
                'processed' => 0,
                'error' => 'Batch not found'
            );
        }
        
        // Update batch status to processing
        $wpdb->update(
            $batch_table,
            array('status' => 'processing', 'updated_at' => current_time('mysql')),
            array('batch_id' => $batch_id),
            array('%s', '%s'),
            array('%s')
        );
        
        // Get pending jobs
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $jobs_table WHERE batch_id = %s AND status = 'pending' ORDER BY created_at ASC",
            $batch_id
        ));
        
        if (empty($jobs)) {
            error_log('WP Image Descriptions: No pending jobs found for batch ' . $batch_id);
            return array(
                'success' => false,
                'processed' => 0,
                'error' => 'No pending jobs found'
            );
        }
        
        error_log('WP Image Descriptions: Processing ' . count($jobs) . ' jobs for batch ' . $batch_id);
        
        $processed_count = 0;
        $completed_count = 0;
        $failed_count = 0;
        
        // Get batch settings
        $batch_settings = json_decode($batch->settings, true);
        $rate_limit_delay = isset($batch_settings['processing']['rate_limit_delay']) ? 
            floatval($batch_settings['processing']['rate_limit_delay']) : 1;
        
        foreach ($jobs as $job) {
            error_log('WP Image Descriptions: Processing job ' . $job->id . ' for attachment ' . $job->attachment_id);
            
            $result = $this->process_single_image($job->id);
            
            if ($result['success']) {
                $completed_count++;
                error_log('WP Image Descriptions: Successfully processed job ' . $job->id);
            } else {
                $failed_count++;
                error_log('WP Image Descriptions: Failed to process job ' . $job->id . ': ' . $result['error']);
            }
            
            $processed_count++;
            
            // Update batch progress
            $wpdb->update(
                $batch_table,
                array(
                    'completed_jobs' => $completed_count,
                    'failed_jobs' => $failed_count,
                    'updated_at' => current_time('mysql')
                ),
                array('batch_id' => $batch_id),
                array('%d', '%d', '%s'),
                array('%s')
            );
            
            // Rate limiting delay
            if ($rate_limit_delay > 0 && $processed_count < count($jobs)) {
                error_log('WP Image Descriptions: Rate limit delay: ' . $rate_limit_delay . ' seconds');
                sleep($rate_limit_delay);
            }
        }
        
        // Update final batch status
        $final_status = ($failed_count === 0) ? 'completed' : 
                       (($completed_count === 0) ? 'failed' : 'completed');
        
        $wpdb->update(
            $batch_table,
            array('status' => $final_status, 'updated_at' => current_time('mysql')),
            array('batch_id' => $batch_id),
            array('%s', '%s'),
            array('%s')
        );
        
        error_log('WP Image Descriptions: Batch ' . $batch_id . ' processing complete. ' . 
                 $completed_count . ' completed, ' . $failed_count . ' failed');
        
        return array(
            'success' => true,
            'processed' => $processed_count,
            'completed' => $completed_count,
            'failed' => $failed_count,
            'batch_status' => $final_status
        );
    }
    
    /**
     * Process single image job
     */
    public function process_single_image($job_id) {
        global $wpdb;
        
        if (empty($job_id)) {
            return array(
                'success' => false,
                'error' => 'No job ID provided'
            );
        }
        
        // Get job details
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $jobs_table WHERE id = %d",
            $job_id
        ));
        
        if (!$job) {
            return array(
                'success' => false,
                'error' => 'Job not found'
            );
        }
        
        error_log('WP Image Descriptions: Processing single image job ' . $job_id . ' for attachment ' . $job->attachment_id);
        
        // Update job status to processing
        $wpdb->update(
            $jobs_table,
            array('status' => 'processing', 'updated_at' => current_time('mysql')),
            array('id' => $job_id),
            array('%s', '%s'),
            array('%d')
        );
        
        try {
            // Get image URL
            $image_url = wp_get_attachment_url($job->attachment_id);
            if (!$image_url) {
                throw new Exception('Could not get image URL for attachment ' . $job->attachment_id);
            }
            
            error_log('WP Image Descriptions: Image URL: ' . $image_url);
            
            // Get batch settings for prompt template
            $batch_table = $wpdb->prefix . 'image_description_batches';
            $batch = $wpdb->get_row($wpdb->prepare(
                "SELECT settings FROM $batch_table WHERE batch_id = %s",
                $job->batch_id
            ));
            
            $prompt_template = null;
            if ($batch && !empty($batch->settings)) {
                $batch_settings = json_decode($batch->settings, true);
                if (isset($batch_settings['prompts']['default_template'])) {
                    $prompt_template = $batch_settings['prompts']['default_template'];
                }
            }
            
            error_log('WP Image Descriptions: Using prompt template: ' . ($prompt_template ?: 'default'));
            
            // Generate description using API client
            $api_result = $this->api_client->generate_description($image_url, $prompt_template);
            
            if (!$api_result['success']) {
                throw new Exception('API call failed: ' . $api_result['error']);
            }
            
            $generated_description = $api_result['description'];
            error_log('WP Image Descriptions: Generated description: ' . substr($generated_description, 0, 100) . '...');
            
            // Update job with success
            $wpdb->update(
                $jobs_table,
                array(
                    'status' => 'completed',
                    'generated_description' => $generated_description,
                    'error_message' => '',
                    'processed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $job_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return array(
                'success' => true,
                'description' => $generated_description
            );
            
        } catch (Exception $e) {
            error_log('WP Image Descriptions: Error processing job ' . $job_id . ': ' . $e->getMessage());
            
            // Update retry count
            $retry_count = intval($job->retry_count) + 1;
            $max_retries = $this->api_client->get_max_retries();
            
            if ($retry_count <= $max_retries) {
                // Mark for retry
                $wpdb->update(
                    $jobs_table,
                    array(
                        'status' => 'pending',
                        'retry_count' => $retry_count,
                        'error_message' => $e->getMessage(),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $job_id),
                    array('%s', '%d', '%s', '%s'),
                    array('%d')
                );
                
                error_log('WP Image Descriptions: Job ' . $job_id . ' marked for retry (' . $retry_count . '/' . $max_retries . ')');
                
                return array(
                    'success' => false,
                    'error' => 'Marked for retry: ' . $e->getMessage(),
                    'retry' => true
                );
            } else {
                // Mark as failed
                $wpdb->update(
                    $jobs_table,
                    array(
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'processed_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $job_id),
                    array('%s', '%s', '%s', '%s'),
                    array('%d')
                );
                
                return array(
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }
    }
    
    /**
     * Process batch asynchronously (for future use with Action Scheduler)
     */
    public function schedule_batch_processing($batch_id) {
        // For MVP, we'll process synchronously
        // In future versions, this could use Action Scheduler for true background processing
        return $this->process_batch($batch_id);
    }
    
    /**
     * Get processing statistics
     */
    public function get_processing_stats($batch_id) {
        global $wpdb;
        
        if (empty($batch_id)) {
            return null;
        }
        
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
            FROM $jobs_table 
            WHERE batch_id = %s
        ", $batch_id));
        
        if (!$stats) {
            return null;
        }
        
        $total = intval($stats->total);
        $completed = intval($stats->completed);
        $failed = intval($stats->failed);
        $processed = $completed + $failed;
        
        return array(
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => intval($stats->pending),
            'processing' => intval($stats->processing),
            'processed' => $processed,
            'percentage' => $total > 0 ? round(($processed / $total) * 100, 1) : 0
        );
    }
    
    /**
     * Cancel batch processing
     */
    public function cancel_batch($batch_id) {
        global $wpdb;
        
        if (empty($batch_id)) {
            return false;
        }
        
        // Update batch status
        $batch_table = $wpdb->prefix . 'image_description_batches';
        $wpdb->update(
            $batch_table,
            array('status' => 'cancelled', 'updated_at' => current_time('mysql')),
            array('batch_id' => $batch_id),
            array('%s', '%s'),
            array('%s')
        );
        
        // Update pending jobs
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        $wpdb->update(
            $jobs_table,
            array('status' => 'cancelled', 'updated_at' => current_time('mysql')),
            array('batch_id' => $batch_id, 'status' => 'pending'),
            array('%s', '%s'),
            array('%s', '%s')
        );
        
        error_log('WP Image Descriptions: Batch ' . $batch_id . ' cancelled');
        
        return true;
    }
    
    /**
     * Retry failed jobs in batch
     */
    public function retry_failed_jobs($batch_id) {
        global $wpdb;
        
        if (empty($batch_id)) {
            return false;
        }
        
        // Reset failed jobs to pending
        $jobs_table = $wpdb->prefix . 'image_description_jobs';
        $updated = $wpdb->update(
            $jobs_table,
            array(
                'status' => 'pending',
                'error_message' => '',
                'updated_at' => current_time('mysql')
            ),
            array('batch_id' => $batch_id, 'status' => 'failed'),
            array('%s', '%s', '%s'),
            array('%s', '%s')
        );
        
        if ($updated > 0) {
            // Update batch status back to pending
            $batch_table = $wpdb->prefix . 'image_description_batches';
            $wpdb->update(
                $batch_table,
                array('status' => 'pending', 'updated_at' => current_time('mysql')),
                array('batch_id' => $batch_id),
                array('%s', '%s'),
                array('%s')
            );
            
            error_log('WP Image Descriptions: Reset ' . $updated . ' failed jobs for batch ' . $batch_id);
        }
        
        return $updated;
    }
}
