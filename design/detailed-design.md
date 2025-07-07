# WordPress Image Description Plugin - Detailed Design

## Overview

The WordPress Image Description Plugin is an accessibility-focused tool that automatically generates alt text descriptions for images using OpenAI-compatible APIs. The plugin integrates seamlessly with the WordPress Media Library, providing bulk processing capabilities with test mode preview and robust background processing.

### Key Features
- Bulk action integration in WordPress Media Library
- OpenAI-compatible API integration with configurable parameters
- Test mode with dedicated preview page
- Background processing with progress tracking and cancellation
- Custom prompt templates for description generation
- Role-based permissions (admin configuration, editor/author usage)
- Comprehensive error handling with retry mechanisms

## Requirements Summary

Based on our requirements clarification, the plugin must support:

1. **User Interface**: Bulk action available from Media Library interface
2. **Image Selection**: Filtering for both images with and without existing alt text
3. **Test Mode**: Dedicated preview page showing generated descriptions
4. **Configuration**: Dedicated settings page under WordPress Settings menu
5. **Permissions**: Administrators configure settings, editors/authors generate descriptions
6. **Error Handling**: Continue processing with failure reporting and retry mechanisms
7. **API Configuration**: Support for custom endpoints, models, parameters, and rate limiting
8. **Customization**: Custom prompt templates that users can modify
9. **Performance**: Parallel processing, queue-based handling, and cancellation support

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Admin Interface                │
├─────────────────────────────────────────────────────────────┤
│  Media Library    │  Settings Page  │  Preview Page        │
│  - Bulk Actions   │  - API Config   │  - Test Results      │
│  - Filtering      │  - Prompts      │  - Batch Review      │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                      Plugin Core Layer                      │
├─────────────────────────────────────────────────────────────┤
│  Batch Manager    │  API Client     │  Queue Processor     │
│  - Job Creation   │  - OpenAI API   │  - Action Scheduler  │
│  - Progress Track │  - Error Handle │  - Progress Updates  │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                     WordPress Data Layer                    │
├─────────────────────────────────────────────────────────────┤
│  wp_posts         │  wp_postmeta    │  Custom Tables       │
│  - Attachments    │  - Alt Text     │  - Job Queue         │
│  - Media Data     │  - Metadata     │  - Progress Data     │
└─────────────────────────────────────────────────────────────┘
```

### Plugin Structure

```
wp-image-descriptions/
├── wp-image-descriptions.php          # Main plugin file
├── includes/
│   ├── class-plugin-core.php          # Core plugin class
│   ├── class-media-library.php        # Media library integration
│   ├── class-settings.php             # Settings management
│   ├── class-api-client.php           # OpenAI API client
│   ├── class-batch-manager.php        # Batch processing manager
│   ├── class-queue-processor.php      # Background queue processing
│   └── class-preview-page.php         # Test mode preview page
├── admin/
│   ├── css/
│   ├── js/
│   └── partials/
├── assets/
└── languages/
```

## Components and Interfaces

### 1. Plugin Core (`PluginCore`)

**Responsibilities:**
- Plugin initialization and lifecycle management
- Hook registration and dependency injection
- Component coordination

**Key Methods:**
```php
class PluginCore {
    public function init()
    public function activate()
    public function deactivate()
    private function load_dependencies()
    private function define_admin_hooks()
}
```

### 2. Media Library Integration (`MediaLibrary`)

**Responsibilities:**
- Add bulk actions to media library
- Handle bulk action processing
- Implement filtering for images with/without alt text
- Integrate with WordPress media list table

**Key Methods:**
```php
class MediaLibrary {
    public function add_bulk_actions($bulk_actions)
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids)
    public function add_media_columns($columns)
    public function display_media_column($column_name, $post_id)
    public function filter_media_by_alt_text()
}
```

### 3. Settings Management (`Settings`)

**Responsibilities:**
- Register and manage plugin settings
- Provide settings page interface
- Handle API configuration and prompt templates
- Manage user permissions

**Key Methods:**
```php
class Settings {
    public function register_settings()
    public function add_settings_page()
    public function render_settings_page()
    public function validate_api_settings($input)
    public function get_setting($key, $default = null)
}
```

**Settings Structure:**
```php
$settings = [
    'api' => [
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
        'api_key' => '',
        'model' => 'gpt-4-vision-preview',
        'max_tokens' => 300,
        'temperature' => 0.7
    ],
    'processing' => [
        'batch_size' => 5,
        'rate_limit_delay' => 1,
        'max_retries' => 3,
        'timeout' => 30
    ],
    'prompts' => [
        'default_template' => 'Describe this image for accessibility purposes. Focus on the main subject, important details, and any text visible in the image. Keep the description concise but informative.'
    ]
];
```

### 4. API Client (`ApiClient`)

**Responsibilities:**
- Handle OpenAI API communication
- Manage authentication and rate limiting
- Process API responses and errors
- Support multiple API providers

**Key Methods:**
```php
class ApiClient {
    public function generate_description($image_url, $prompt_template)
    public function test_connection()
    private function prepare_request($image_url, $prompt)
    private function handle_response($response)
    private function handle_error($error)
}
```

### 5. Batch Manager (`BatchManager`)

**Responsibilities:**
- Create and manage processing batches
- Track batch progress and status
- Handle test mode vs production mode
- Coordinate with queue processor

**Key Methods:**
```php
class BatchManager {
    public function create_batch($attachment_ids, $mode = 'test')
    public function get_batch_progress($batch_id)
    public function cancel_batch($batch_id)
    public function apply_batch_results($batch_id)
    private function create_batch_jobs($batch_id, $attachment_ids)
}
```

### 6. Queue Processor (`QueueProcessor`)

**Responsibilities:**
- Process individual image description jobs
- Handle background processing via Action Scheduler
- Manage retries and error handling
- Update progress tracking

**Key Methods:**
```php
class QueueProcessor {
    public function schedule_batch_processing($batch_id)
    public function process_single_image($job_id)
    public function handle_processing_error($job_id, $error)
    public function update_job_progress($job_id, $status, $data = null)
}
```

### 7. Preview Page (`PreviewPage`)

**Responsibilities:**
- Display test mode results
- Allow batch review and editing
- Provide apply/cancel options
- Show processing progress

**Key Methods:**
```php
class PreviewPage {
    public function render_preview_page()
    public function display_batch_results($batch_id)
    public function handle_batch_actions()
    private function render_image_preview($attachment_id, $description)
}
```

## Data Models

### Batch Processing Table

```sql
CREATE TABLE {$wpdb->prefix}image_description_batches (
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
);
```

### Job Queue Table

```sql
CREATE TABLE {$wpdb->prefix}image_description_jobs (
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
);
```

### WordPress Integration

**Alt Text Storage:**
- Location: `wp_postmeta` table
- Meta key: `_wp_attachment_image_alt`
- Access: `get_post_meta($attachment_id, '_wp_attachment_image_alt', true)`
- Update: `update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text)`

**Plugin Settings:**
- Location: `wp_options` table
- Option name: `wp_image_descriptions_settings`
- Access: `get_option('wp_image_descriptions_settings', [])`

## Error Handling

### Error Categories

1. **API Errors**
   - Authentication failures (401)
   - Rate limiting (429)
   - Invalid requests (400)
   - Server errors (500+)
   - Network timeouts

2. **Processing Errors**
   - Invalid image formats
   - Corrupted image files
   - Missing attachment records
   - Permission errors

3. **System Errors**
   - Database connection issues
   - Memory limitations
   - Plugin conflicts

### Error Handling Strategy

**Retry Logic:**
```php
class RetryHandler {
    private $max_retries = 3;
    private $base_delay = 1; // seconds
    
    public function execute_with_retry($callback, $job_id) {
        $attempt = 0;
        
        while ($attempt < $this->max_retries) {
            try {
                return $callback();
            } catch (Exception $e) {
                $attempt++;
                
                if ($attempt >= $this->max_retries) {
                    $this->log_final_failure($job_id, $e);
                    throw $e;
                }
                
                $delay = $this->base_delay * pow(2, $attempt - 1);
                sleep($delay);
            }
        }
    }
}
```

**Error Reporting:**
- Log errors to WordPress error log
- Store error details in job records
- Provide user-friendly error messages
- Generate error summary reports

## Testing Strategy

### Unit Testing
- **Framework**: PHPUnit with WordPress test suite
- **Coverage**: Core classes and methods
- **Mocking**: API responses and WordPress functions
- **Test Data**: Sample images and expected responses

### Integration Testing
- **WordPress Integration**: Plugin activation, settings, hooks
- **API Integration**: OpenAI API communication and error handling
- **Database Operations**: Batch and job management
- **User Interface**: Admin pages and AJAX functionality

### Accessibility Testing
- **Screen Reader Testing**: NVDA, JAWS, VoiceOver
- **Generated Content Quality**: Manual review of AI descriptions
- **WCAG Compliance**: Automated and manual accessibility audits

### Performance Testing
- **Batch Processing**: Large image sets (100+ images)
- **API Rate Limiting**: Stress testing with rate limits
- **Memory Usage**: Monitor memory consumption during processing
- **Database Performance**: Query optimization and indexing

### User Acceptance Testing
- **Workflow Testing**: Complete user journeys
- **Role-based Testing**: Different user permission levels
- **Error Scenarios**: Network failures, API errors, cancellations
- **Cross-browser Testing**: Admin interface compatibility

## Security Considerations

### API Key Management
- Store API keys encrypted in database
- Provide secure input fields (password type)
- Validate API key format and permissions
- Support environment variable configuration

### User Permissions
- Capability checks for all admin actions
- Nonce verification for form submissions
- Sanitize and validate all user inputs
- Escape output data appropriately

### Data Protection
- Secure image data transmission to APIs
- Temporary file cleanup after processing
- User data privacy considerations
- GDPR compliance for EU users

### Rate Limiting Protection
- Implement client-side rate limiting
- Monitor API usage and costs
- Provide usage analytics and alerts
- Support API key rotation

This detailed design provides a comprehensive foundation for implementing the WordPress Image Description Plugin. The architecture supports all identified requirements while maintaining WordPress best practices and ensuring scalability, security, and accessibility.
