<?php
/**
 * Settings management class
 * 
 * Handles plugin settings page and configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Settings {
    
    /**
     * Settings page slug
     */
    private $page_slug = 'wp-image-descriptions-settings';
    
    /**
     * Settings option name
     */
    private $option_name = 'wp_image_descriptions_settings';
    
    /**
     * Settings group name
     */
    private $settings_group = 'wp_image_descriptions_group';
    
    /**
     * Add settings page to WordPress admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __('Image Descriptions Settings', 'wp-image-descriptions'),
            __('Image Descriptions', 'wp-image-descriptions'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register the main settings option
        register_setting(
            $this->settings_group,
            $this->option_name,
            array($this, 'validate_settings')
        );
        
        // API Configuration Section
        add_settings_section(
            'api_section',
            __('API Configuration', 'wp-image-descriptions'),
            array($this, 'render_api_section'),
            $this->page_slug
        );
        
        // API Endpoint field
        add_settings_field(
            'api_endpoint',
            __('API Endpoint', 'wp-image-descriptions'),
            array($this, 'render_api_endpoint_field'),
            $this->page_slug,
            'api_section'
        );
        
        // API Key field
        add_settings_field(
            'api_key',
            __('API Key', 'wp-image-descriptions'),
            array($this, 'render_api_key_field'),
            $this->page_slug,
            'api_section'
        );
        
        // Model Selection field
        add_settings_field(
            'model',
            __('Model', 'wp-image-descriptions'),
            array($this, 'render_model_field'),
            $this->page_slug,
            'api_section'
        );
        
        // Max Tokens field
        add_settings_field(
            'max_tokens',
            __('Max Tokens', 'wp-image-descriptions'),
            array($this, 'render_max_tokens_field'),
            $this->page_slug,
            'api_section'
        );
        
        // Temperature field
        add_settings_field(
            'temperature',
            __('Temperature', 'wp-image-descriptions'),
            array($this, 'render_temperature_field'),
            $this->page_slug,
            'api_section'
        );
        
        // Processing Settings Section
        add_settings_section(
            'processing_section',
            __('Processing Settings', 'wp-image-descriptions'),
            array($this, 'render_processing_section'),
            $this->page_slug
        );
        
        // Batch Size field
        add_settings_field(
            'batch_size',
            __('Batch Size', 'wp-image-descriptions'),
            array($this, 'render_batch_size_field'),
            $this->page_slug,
            'processing_section'
        );
        
        // Rate Limit Delay field
        add_settings_field(
            'rate_limit_delay',
            __('Rate Limit Delay (seconds)', 'wp-image-descriptions'),
            array($this, 'render_rate_limit_delay_field'),
            $this->page_slug,
            'processing_section'
        );
        
        // Max Retries field
        add_settings_field(
            'max_retries',
            __('Max Retries', 'wp-image-descriptions'),
            array($this, 'render_max_retries_field'),
            $this->page_slug,
            'processing_section'
        );
        
        // Prompt Template Section
        add_settings_section(
            'prompt_section',
            __('Prompt Template', 'wp-image-descriptions'),
            array($this, 'render_prompt_section'),
            $this->page_slug
        );
        
        // Default Template field
        add_settings_field(
            'default_template',
            __('Default Prompt Template', 'wp-image-descriptions'),
            array($this, 'render_default_template_field'),
            $this->page_slug,
            'prompt_section'
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-image-descriptions'));
        }
        
        // Handle test connection
        if (isset($_POST['test_connection']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_image_descriptions_test')) {
            $this->handle_test_connection();
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Test API Connection', 'wp-image-descriptions'); ?></h2>
            <p><?php esc_html_e('Test your API configuration to ensure it\'s working correctly.', 'wp-image-descriptions'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wp_image_descriptions_test'); ?>
                <input type="submit" name="test_connection" class="button button-secondary" 
                       value="<?php esc_attr_e('Test Connection', 'wp-image-descriptions'); ?>">
            </form>
        </div>
        <?php
    }
    
    /**
     * Render API section description
     */
    public function render_api_section() {
        echo '<p>' . esc_html__('Configure your OpenAI-compatible API settings. These settings are required for the plugin to generate image descriptions.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render processing section description
     */
    public function render_processing_section() {
        echo '<p>' . esc_html__('Configure how the plugin processes images and handles API requests.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render prompt section description
     */
    public function render_prompt_section() {
        echo '<p>' . esc_html__('Customize the prompt template used to generate image descriptions.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render API endpoint field
     */
    public function render_api_endpoint_field() {
        $value = $this->get_setting('api.endpoint', 'https://api.openai.com/v1/chat/completions');
        echo '<input type="url" name="' . $this->option_name . '[api][endpoint]" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('The API endpoint URL for your OpenAI-compatible service.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = $this->get_setting('api.api_key', '');
        echo '<input type="password" name="' . $this->option_name . '[api][api_key]" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Your API key for authentication. This will be stored securely.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render model field
     */
    public function render_model_field() {
        $value = $this->get_setting('api.model', 'gpt-4-vision-preview');
        $models = array(
            'gpt-4-vision-preview' => 'GPT-4 Vision Preview',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'claude-3-opus' => 'Claude 3 Opus',
            'claude-3-sonnet' => 'Claude 3 Sonnet',
            'claude-3-haiku' => 'Claude 3 Haiku'
        );
        
        echo '<select name="' . $this->option_name . '[api][model]" class="regular-text">';
        foreach ($models as $model_id => $model_name) {
            echo '<option value="' . esc_attr($model_id) . '"' . selected($value, $model_id, false) . '>' . esc_html($model_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('The AI model to use for generating descriptions.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render max tokens field
     */
    public function render_max_tokens_field() {
        $value = $this->get_setting('api.max_tokens', 300);
        echo '<input type="number" name="' . $this->option_name . '[api][max_tokens]" value="' . esc_attr($value) . '" min="50" max="1000" class="small-text">';
        echo '<p class="description">' . esc_html__('Maximum number of tokens for the generated description (50-1000).', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render temperature field
     */
    public function render_temperature_field() {
        $value = $this->get_setting('api.temperature', 0.7);
        echo '<input type="number" name="' . $this->option_name . '[api][temperature]" value="' . esc_attr($value) . '" min="0" max="2" step="0.1" class="small-text">';
        echo '<p class="description">' . esc_html__('Controls randomness in the output (0.0 = deterministic, 2.0 = very random).', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render batch size field
     */
    public function render_batch_size_field() {
        $value = $this->get_setting('processing.batch_size', 5);
        echo '<input type="number" name="' . $this->option_name . '[processing][batch_size]" value="' . esc_attr($value) . '" min="1" max="20" class="small-text">';
        echo '<p class="description">' . esc_html__('Number of images to process simultaneously (1-20).', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render rate limit delay field
     */
    public function render_rate_limit_delay_field() {
        $value = $this->get_setting('processing.rate_limit_delay', 1);
        echo '<input type="number" name="' . $this->option_name . '[processing][rate_limit_delay]" value="' . esc_attr($value) . '" min="0" max="10" step="0.5" class="small-text">';
        echo '<p class="description">' . esc_html__('Delay between API requests to avoid rate limiting (0-10 seconds).', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render max retries field
     */
    public function render_max_retries_field() {
        $value = $this->get_setting('processing.max_retries', 3);
        echo '<input type="number" name="' . $this->option_name . '[processing][max_retries]" value="' . esc_attr($value) . '" min="0" max="10" class="small-text">';
        echo '<p class="description">' . esc_html__('Maximum number of retry attempts for failed requests (0-10).', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Render default template field
     */
    public function render_default_template_field() {
        $value = $this->get_setting('prompts.default_template', 'Describe this image for accessibility purposes. Focus on the main subject, important details, and any text visible in the image. Keep the description concise but informative.');
        echo '<textarea name="' . $this->option_name . '[prompts][default_template]" rows="4" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('The default prompt template used to generate image descriptions. You can customize this to match your needs.', 'wp-image-descriptions') . '</p>';
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($input) {
        $validated = array();
        
        // Validate API settings
        if (isset($input['api'])) {
            $validated['api'] = array();
            
            // Validate endpoint
            if (isset($input['api']['endpoint'])) {
                $endpoint = sanitize_url($input['api']['endpoint']);
                if (filter_var($endpoint, FILTER_VALIDATE_URL)) {
                    $validated['api']['endpoint'] = $endpoint;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'invalid_endpoint',
                        __('Please enter a valid API endpoint URL.', 'wp-image-descriptions')
                    );
                }
            }
            
            // Validate API key
            if (isset($input['api']['api_key'])) {
                $api_key = sanitize_text_field($input['api']['api_key']);
                if (!empty($api_key)) {
                    $validated['api']['api_key'] = $api_key;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'empty_api_key',
                        __('API key is required.', 'wp-image-descriptions')
                    );
                }
            }
            
            // Validate model
            if (isset($input['api']['model'])) {
                $validated['api']['model'] = sanitize_text_field($input['api']['model']);
            }
            
            // Validate max tokens
            if (isset($input['api']['max_tokens'])) {
                $max_tokens = intval($input['api']['max_tokens']);
                if ($max_tokens >= 50 && $max_tokens <= 1000) {
                    $validated['api']['max_tokens'] = $max_tokens;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'invalid_max_tokens',
                        __('Max tokens must be between 50 and 1000.', 'wp-image-descriptions')
                    );
                }
            }
            
            // Validate temperature
            if (isset($input['api']['temperature'])) {
                $temperature = floatval($input['api']['temperature']);
                if ($temperature >= 0 && $temperature <= 2) {
                    $validated['api']['temperature'] = $temperature;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'invalid_temperature',
                        __('Temperature must be between 0.0 and 2.0.', 'wp-image-descriptions')
                    );
                }
            }
        }
        
        // Validate processing settings
        if (isset($input['processing'])) {
            $validated['processing'] = array();
            
            // Validate batch size
            if (isset($input['processing']['batch_size'])) {
                $batch_size = intval($input['processing']['batch_size']);
                if ($batch_size >= 1 && $batch_size <= 20) {
                    $validated['processing']['batch_size'] = $batch_size;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'invalid_batch_size',
                        __('Batch size must be between 1 and 20.', 'wp-image-descriptions')
                    );
                }
            }
            
            // Validate rate limit delay
            if (isset($input['processing']['rate_limit_delay'])) {
                $delay = floatval($input['processing']['rate_limit_delay']);
                if ($delay >= 0 && $delay <= 10) {
                    $validated['processing']['rate_limit_delay'] = $delay;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'invalid_rate_limit_delay',
                        __('Rate limit delay must be between 0 and 10 seconds.', 'wp-image-descriptions')
                    );
                }
            }
            
            // Validate max retries
            if (isset($input['processing']['max_retries'])) {
                $max_retries = intval($input['processing']['max_retries']);
                if ($max_retries >= 0 && $max_retries <= 10) {
                    $validated['processing']['max_retries'] = $max_retries;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'invalid_max_retries',
                        __('Max retries must be between 0 and 10.', 'wp-image-descriptions')
                    );
                }
            }
        }
        
        // Validate prompt settings
        if (isset($input['prompts'])) {
            $validated['prompts'] = array();
            
            // Validate default template
            if (isset($input['prompts']['default_template'])) {
                $template = sanitize_textarea_field($input['prompts']['default_template']);
                if (!empty($template)) {
                    $validated['prompts']['default_template'] = $template;
                } else {
                    add_settings_error(
                        $this->option_name,
                        'empty_template',
                        __('Default prompt template cannot be empty.', 'wp-image-descriptions')
                    );
                }
            }
        }
        
        return $validated;
    }
    
    /**
     * Handle test connection
     */
    private function handle_test_connection() {
        // Get API client
        $api_client = new WP_Image_Descriptions_API_Client();
        $result = $api_client->test_connection();
        
        if ($result['success']) {
            add_settings_error(
                $this->option_name,
                'connection_success',
                __('API connection test successful!', 'wp-image-descriptions'),
                'success'
            );
        } else {
            add_settings_error(
                $this->option_name,
                'connection_failed',
                sprintf(__('API connection test failed: %s', 'wp-image-descriptions'), $result['error']),
                'error'
            );
        }
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        $settings = get_option($this->option_name, array());
        
        // Support nested keys like 'api.endpoint'
        $keys = explode('.', $key);
        $value = $settings;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}
