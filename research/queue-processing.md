# Queue Processing in WordPress Research

## WordPress Background Processing Options

### WordPress Cron (wp-cron)
- Built-in scheduling system
- Triggered by page visits (pseudo-cron)
- `wp_schedule_event()` for recurring tasks
- `wp_schedule_single_event()` for one-time tasks
- Limitations: Unreliable on low-traffic sites

### Action Scheduler
- Robust background processing library
- Used by WooCommerce and other major plugins
- Reliable queue processing
- Built-in retry mechanisms
- Admin interface for monitoring

### Custom Queue Implementation
- Database table for queue items
- AJAX-based processing
- Real-time progress updates
- Manual queue management

## Action Scheduler Implementation

### Installation
```php
// Include Action Scheduler
require_once plugin_dir_path(__FILE__) . 'action-scheduler/action-scheduler.php';
```

### Queue Job Creation
```php
// Schedule single action
as_schedule_single_action(
    time() + 60, // When to run
    'process_image_description', // Hook name
    array($attachment_id), // Arguments
    'image-descriptions' // Group
);

// Schedule recurring action
as_schedule_recurring_action(
    time(),
    HOUR_IN_SECONDS,
    'cleanup_temp_data',
    array(),
    'maintenance'
);
```

### Job Processing
```php
add_action('process_image_description', 'handle_image_description_processing');
function handle_image_description_processing($attachment_id) {
    // Process single image
    // Call OpenAI API
    // Update attachment metadata
    // Handle errors and retries
}
```

## Progress Tracking Implementation

### Database Schema
```sql
CREATE TABLE wp_image_description_jobs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    batch_id varchar(255) NOT NULL,
    attachment_id bigint(20) NOT NULL,
    status enum('pending','processing','completed','failed') DEFAULT 'pending',
    progress_data longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY batch_id (batch_id),
    KEY attachment_id (attachment_id)
);
```

### AJAX Progress Updates
```php
add_action('wp_ajax_get_batch_progress', 'get_batch_progress');
function get_batch_progress() {
    $batch_id = sanitize_text_field($_POST['batch_id']);
    
    $total = get_batch_total_count($batch_id);
    $completed = get_batch_completed_count($batch_id);
    $failed = get_batch_failed_count($batch_id);
    
    wp_send_json_success(array(
        'total' => $total,
        'completed' => $completed,
        'failed' => $failed,
        'percentage' => ($total > 0) ? round(($completed + $failed) / $total * 100) : 0
    ));
}
```

### JavaScript Progress Display
```javascript
function updateProgress(batchId) {
    jQuery.post(ajaxurl, {
        action: 'get_batch_progress',
        batch_id: batchId,
        nonce: ajax_nonce
    }, function(response) {
        if (response.success) {
            const data = response.data;
            const percentage = data.percentage;
            jQuery('#progress-bar').css('width', percentage + '%');
            jQuery('#progress-text').text(data.completed + '/' + data.total + ' completed');
            
            if (percentage < 100) {
                setTimeout(() => updateProgress(batchId), 2000);
            }
        }
    });
}
```

## Cancellation Mechanisms

### Job Cancellation
```php
// Cancel specific job
as_unschedule_action('process_image_description', array($attachment_id));

// Cancel entire batch
as_unschedule_all_actions('', array(), 'image-descriptions');

// Update database status
function cancel_batch($batch_id) {
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'image_description_jobs',
        array('status' => 'cancelled'),
        array('batch_id' => $batch_id, 'status' => 'pending')
    );
}
```

### User Interface
```php
add_action('wp_ajax_cancel_batch', 'handle_batch_cancellation');
function handle_batch_cancellation() {
    $batch_id = sanitize_text_field($_POST['batch_id']);
    
    if (!current_user_can('edit_posts')) {
        wp_die('Insufficient permissions');
    }
    
    cancel_batch($batch_id);
    wp_send_json_success('Batch cancelled successfully');
}
```

## Performance Considerations

### Batch Size Optimization
- Process 5-10 images per batch to balance speed and reliability
- Consider API rate limits
- Monitor memory usage
- Implement timeout handling

### Error Handling and Retries
- Exponential backoff for API failures
- Maximum retry attempts (3-5)
- Different retry strategies for different error types
- Logging for debugging and monitoring
