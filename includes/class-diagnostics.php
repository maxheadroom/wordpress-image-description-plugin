<?php
/**
 * Diagnostics class for troubleshooting bulk action issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Diagnostics {
    
    /**
     * Run comprehensive diagnostics
     */
    public static function run_diagnostics() {
        $results = array();
        
        // WordPress environment
        $results['wordpress'] = array(
            'version' => get_bloginfo('version'),
            'multisite' => is_multisite(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'current_theme' => wp_get_theme()->get('Name'),
            'active_plugins' => self::get_active_plugins()
        );
        
        // User capabilities
        $results['user'] = array(
            'current_user_id' => get_current_user_id(),
            'user_roles' => wp_get_current_user()->roles,
            'can_edit_posts' => current_user_can('edit_posts'),
            'can_upload_files' => current_user_can('upload_files'),
            'can_manage_options' => current_user_can('manage_options')
        );
        
        // Current screen info
        global $current_screen, $pagenow;
        $results['screen'] = array(
            'current_screen_id' => $current_screen ? $current_screen->id : 'none',
            'current_screen_base' => $current_screen ? $current_screen->base : 'none',
            'pagenow' => $pagenow,
            'is_admin' => is_admin(),
            'query_vars' => $_GET
        );
        
        // Hook diagnostics
        $results['hooks'] = array(
            'bulk_actions_upload_hooks' => self::get_hook_callbacks('bulk_actions-upload'),
            'handle_bulk_actions_upload_hooks' => self::get_hook_callbacks('handle_bulk_actions-upload'),
            'admin_init_hooks' => count($GLOBALS['wp_filter']['admin_init']->callbacks ?? []),
            'current_screen_hooks' => count($GLOBALS['wp_filter']['current_screen']->callbacks ?? [])
        );
        
        // Test bulk actions directly
        $results['bulk_actions_test'] = self::test_bulk_actions_filter();
        
        return $results;
    }
    
    /**
     * Get active plugins list
     */
    private static function get_active_plugins() {
        $active_plugins = get_option('active_plugins', array());
        $plugin_names = array();
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugin_names[] = $plugin_data['Name'] . ' (' . $plugin_data['Version'] . ')';
        }
        
        return $plugin_names;
    }
    
    /**
     * Get callbacks for a specific hook
     */
    private static function get_hook_callbacks($hook_name) {
        global $wp_filter;
        
        if (!isset($wp_filter[$hook_name])) {
            return array('status' => 'Hook not registered');
        }
        
        $callbacks = array();
        foreach ($wp_filter[$hook_name]->callbacks as $priority => $functions) {
            foreach ($functions as $function) {
                if (is_array($function['function'])) {
                    if (is_object($function['function'][0])) {
                        $callbacks[] = get_class($function['function'][0]) . '::' . $function['function'][1] . ' (priority: ' . $priority . ')';
                    } else {
                        $callbacks[] = $function['function'][0] . '::' . $function['function'][1] . ' (priority: ' . $priority . ')';
                    }
                } else {
                    $callbacks[] = $function['function'] . ' (priority: ' . $priority . ')';
                }
            }
        }
        
        return $callbacks;
    }
    
    /**
     * Test bulk actions filter directly
     */
    private static function test_bulk_actions_filter() {
        // Simulate the bulk actions filter
        $test_actions = array(
            'delete' => 'Delete Permanently',
            'edit' => 'Edit'
        );
        
        // Apply the filter
        $filtered_actions = apply_filters('bulk_actions-upload', $test_actions);
        
        return array(
            'original_actions' => array_keys($test_actions),
            'filtered_actions' => array_keys($filtered_actions),
            'new_actions' => array_diff(array_keys($filtered_actions), array_keys($test_actions)),
            'filter_applied' => count($filtered_actions) !== count($test_actions)
        );
    }
    
    /**
     * Display diagnostics page
     */
    public static function display_diagnostics_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        // Handle table recreation
        if (isset($_POST['recreate_tables']) && wp_verify_nonce($_POST['_wpnonce'], 'recreate_tables')) {
            $plugin_instance = WP_Image_Descriptions::get_instance();
            if ($plugin_instance->force_recreate_tables()) {
                echo '<div class="notice notice-success"><p>Database tables recreated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to recreate database tables.</p></div>';
            }
        }
        
        $diagnostics = self::run_diagnostics();
        
        ?>
        <div class="wrap">
            <h1>WP Image Descriptions - Diagnostics</h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
                <h2>Database Tables</h2>
                <?php
                global $wpdb;
                $batches_table = $wpdb->prefix . 'image_description_batches';
                $jobs_table = $wpdb->prefix . 'image_description_jobs';
                $batches_exists = $wpdb->get_var("SHOW TABLES LIKE '$batches_table'") === $batches_table;
                $jobs_exists = $wpdb->get_var("SHOW TABLES LIKE '$jobs_table'") === $jobs_table;
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>Batches Table</strong></td>
                            <td><?php echo $batches_exists ? '✅ Exists' : '❌ Missing'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Jobs Table</strong></td>
                            <td><?php echo $jobs_exists ? '✅ Exists' : '❌ Missing'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database Version</strong></td>
                            <td><?php echo get_option('wp_image_descriptions_db_version', 'Not set'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!$batches_exists || !$jobs_exists): ?>
                <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7;">
                    <h4>⚠️ Database Tables Missing</h4>
                    <p>Some database tables are missing. This will cause batch creation to fail.</p>
                    <form method="post" action="">
                        <?php wp_nonce_field('recreate_tables'); ?>
                        <input type="submit" name="recreate_tables" class="button button-primary" 
                               value="Recreate Database Tables" 
                               onclick="return confirm('This will recreate the database tables. Any existing batch data will be lost. Continue?');">
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
                <h2>WordPress Environment</h2>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['wordpress'] as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                            <td><?php echo esc_html(is_array($value) ? implode(', ', $value) : $value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
                <h2>User Capabilities</h2>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['user'] as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                            <td><?php echo esc_html(is_array($value) ? implode(', ', $value) : ($value ? 'Yes' : 'No')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
                <h2>Current Screen Info</h2>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['screen'] as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                            <td><?php echo esc_html(is_array($value) ? print_r($value, true) : $value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
                <h2>Hook Diagnostics</h2>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['hooks'] as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                            <td><pre><?php echo esc_html(is_array($value) ? print_r($value, true) : $value); ?></pre></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
                <h2>Bulk Actions Filter Test</h2>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['bulk_actions_test'] as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                            <td><?php echo esc_html(is_array($value) ? implode(', ', $value) : ($value ? 'Yes' : 'No')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-left: 4px solid #72aee6;">
                <h3>Troubleshooting Steps</h3>
                <ol>
                    <li><strong>Database Tables:</strong> If tables are missing, use the "Recreate Database Tables" button above</li>
                    <li><strong>Theme Test:</strong> Switch to a default WordPress theme (Twenty Twenty-Four) temporarily</li>
                    <li><strong>Plugin Test:</strong> Deactivate all other plugins except WP Image Descriptions</li>
                    <li><strong>User Test:</strong> Try with a different administrator account</li>
                    <li><strong>Browser Test:</strong> Clear browser cache and try in incognito mode</li>
                    <li><strong>WordPress Test:</strong> Check if bulk actions work in Posts or Pages admin</li>
                </ol>
            </div>
        </div>
        <?php
    }
}
