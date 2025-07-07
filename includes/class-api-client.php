<?php
/**
 * API Client class
 * 
 * Handles communication with OpenAI-compatible APIs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_API_Client {
    
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new WP_Image_Descriptions_Settings();
    }
    
    /**
     * Generate description for image
     */
    public function generate_description($image_url, $prompt_template = null) {
        // Get prompt template
        if (empty($prompt_template)) {
            $prompt_template = $this->settings->get_setting('prompts.default_template', 
                'Describe this image for accessibility purposes. Focus on the main subject, important details, and any text visible in the image. Keep the description concise but informative.'
            );
        }
        
        // Validate image URL
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            return array(
                'success' => false,
                'description' => '',
                'error' => 'Invalid image URL provided'
            );
        }
        
        // Check if image is accessible
        $image_data = $this->get_image_data($image_url);
        if (!$image_data['success']) {
            return array(
                'success' => false,
                'description' => '',
                'error' => $image_data['error']
            );
        }
        
        // Prepare API request
        $request_data = $this->prepare_request($image_data['base64'], $prompt_template);
        if (!$request_data['success']) {
            return array(
                'success' => false,
                'description' => '',
                'error' => $request_data['error']
            );
        }
        
        // Make API request
        $response = $this->make_api_request($request_data['data']);
        
        return $response;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        // Check if API settings are configured
        $api_key = $this->settings->get_setting('api.api_key', '');
        $endpoint = $this->settings->get_setting('api.endpoint', '');
        
        if (empty($api_key) || empty($endpoint)) {
            return array(
                'success' => false,
                'error' => 'API key and endpoint must be configured'
            );
        }
        
        // Create a simple test image (1x1 pixel PNG in base64)
        $test_image_base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        // Prepare test request
        $request_data = $this->prepare_request($test_image_base64, 'Describe this test image briefly.');
        if (!$request_data['success']) {
            return array(
                'success' => false,
                'error' => $request_data['error']
            );
        }
        
        // Make test API request
        $response = $this->make_api_request($request_data['data']);
        
        if ($response['success']) {
            return array(
                'success' => true,
                'message' => 'API connection successful'
            );
        } else {
            return array(
                'success' => false,
                'error' => 'API connection failed: ' . $response['error']
            );
        }
    }
    
    /**
     * Get image data and convert to base64
     */
    private function get_image_data($image_url) {
        // Handle local WordPress URLs
        if (strpos($image_url, home_url()) === 0) {
            // Convert to file path for local images
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
            
            if (file_exists($file_path)) {
                $image_data = file_get_contents($file_path);
                if ($image_data === false) {
                    return array(
                        'success' => false,
                        'error' => 'Could not read local image file'
                    );
                }
            } else {
                return array(
                    'success' => false,
                    'error' => 'Local image file not found'
                );
            }
        } else {
            // Use WordPress HTTP API for remote images
            $response = wp_remote_get($image_url, array(
                'timeout' => 30,
                'headers' => array(
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
                )
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => 'Failed to fetch image: ' . $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array(
                    'success' => false,
                    'error' => 'Failed to fetch image: HTTP ' . $response_code
                );
            }
            
            $image_data = wp_remote_retrieve_body($response);
        }
        
        // Validate image data
        if (empty($image_data)) {
            return array(
                'success' => false,
                'error' => 'Empty image data received'
            );
        }
        
        // Check image size (limit to 20MB as per OpenAI requirements)
        if (strlen($image_data) > 20 * 1024 * 1024) {
            return array(
                'success' => false,
                'error' => 'Image too large (max 20MB)'
            );
        }
        
        // Validate image format
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        
        if (!in_array($mime_type, $allowed_types)) {
            return array(
                'success' => false,
                'error' => 'Unsupported image format: ' . $mime_type
            );
        }
        
        // Convert to base64
        $base64 = base64_encode($image_data);
        
        return array(
            'success' => true,
            'base64' => $base64,
            'mime_type' => $mime_type
        );
    }
    
    /**
     * Prepare API request data
     */
    private function prepare_request($image_base64, $prompt_template) {
        // Get API settings
        $model = $this->settings->get_setting('api.model', 'gpt-4-vision-preview');
        $max_tokens = $this->settings->get_setting('api.max_tokens', 300);
        $temperature = $this->settings->get_setting('api.temperature', 0.7);
        
        // Determine image format from base64 data
        $image_info = getimagesizefromstring(base64_decode($image_base64));
        if ($image_info === false) {
            return array(
                'success' => false,
                'error' => 'Invalid image data'
            );
        }
        
        $mime_type = $image_info['mime'];
        
        // Prepare request body based on API provider
        if (strpos($model, 'claude') !== false) {
            // Anthropic Claude format
            $request_body = array(
                'model' => $model,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => $prompt_template
                            ),
                            array(
                                'type' => 'image',
                                'source' => array(
                                    'type' => 'base64',
                                    'media_type' => $mime_type,
                                    'data' => $image_base64
                                )
                            )
                        )
                    )
                )
            );
        } else {
            // OpenAI format (default)
            $request_body = array(
                'model' => $model,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => $prompt_template
                            ),
                            array(
                                'type' => 'image_url',
                                'image_url' => array(
                                    'url' => 'data:' . $mime_type . ';base64,' . $image_base64
                                )
                            )
                        )
                    )
                )
            );
        }
        
        return array(
            'success' => true,
            'data' => $request_body
        );
    }
    
    /**
     * Make API request
     */
    private function make_api_request($request_body) {
        // Get API settings
        $endpoint = $this->settings->get_setting('api.endpoint', '');
        $api_key = $this->settings->get_setting('api.api_key', '');
        $timeout = $this->settings->get_setting('processing.timeout', 30);
        
        // Prepare headers
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; WP-Image-Descriptions/1.0.0'
        );
        
        // Add authentication header based on endpoint
        if (strpos($endpoint, 'anthropic') !== false || strpos($endpoint, 'claude') !== false) {
            $headers['x-api-key'] = $api_key;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        
        // Make request
        $response = wp_remote_post($endpoint, array(
            'timeout' => $timeout,
            'headers' => $headers,
            'body' => wp_json_encode($request_body),
            'data_format' => 'body'
        ));
        
        // Handle request errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'description' => '',
                'error' => 'Request failed: ' . $response->get_error_message()
            );
        }
        
        // Get response data
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle HTTP errors
        if ($response_code !== 200) {
            $error_message = $this->parse_error_response($response_code, $response_body);
            return array(
                'success' => false,
                'description' => '',
                'error' => $error_message
            );
        }
        
        // Parse response
        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'description' => '',
                'error' => 'Invalid JSON response from API'
            );
        }
        
        // Extract description from response
        $description = $this->extract_description($data);
        if (empty($description)) {
            return array(
                'success' => false,
                'description' => '',
                'error' => 'No description found in API response'
            );
        }
        
        return array(
            'success' => true,
            'description' => $description,
            'error' => ''
        );
    }
    
    /**
     * Parse error response
     */
    private function parse_error_response($response_code, $response_body) {
        $error_data = json_decode($response_body, true);
        
        switch ($response_code) {
            case 400:
                $message = 'Bad request';
                if (isset($error_data['error']['message'])) {
                    $message .= ': ' . $error_data['error']['message'];
                }
                break;
                
            case 401:
                $message = 'Authentication failed - check your API key';
                break;
                
            case 403:
                $message = 'Access forbidden - check your API permissions';
                break;
                
            case 429:
                $message = 'Rate limit exceeded - please try again later';
                if (isset($error_data['error']['message'])) {
                    $message .= ': ' . $error_data['error']['message'];
                }
                break;
                
            case 500:
            case 502:
            case 503:
            case 504:
                $message = 'API server error - please try again later';
                break;
                
            default:
                $message = 'API request failed with status ' . $response_code;
                if (isset($error_data['error']['message'])) {
                    $message .= ': ' . $error_data['error']['message'];
                }
        }
        
        return $message;
    }
    
    /**
     * Extract description from API response
     */
    private function extract_description($data) {
        // OpenAI format
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
        
        // Anthropic Claude format
        if (isset($data['content'][0]['text'])) {
            return trim($data['content'][0]['text']);
        }
        
        // Alternative formats
        if (isset($data['text'])) {
            return trim($data['text']);
        }
        
        if (isset($data['description'])) {
            return trim($data['description']);
        }
        
        return '';
    }
    
    /**
     * Get rate limit delay
     */
    public function get_rate_limit_delay() {
        return $this->settings->get_setting('processing.rate_limit_delay', 1);
    }
    
    /**
     * Get max retries
     */
    public function get_max_retries() {
        return $this->settings->get_setting('processing.max_retries', 3);
    }
}
