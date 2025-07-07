# WordPress Media Library Architecture Research

## Media Attachment Data Structure

### Core Tables
- `wp_posts` - Main attachment records (post_type = 'attachment')
- `wp_postmeta` - Attachment metadata
- `wp_posts.post_excerpt` - Attachment caption
- `wp_posts.post_content` - Attachment description

### Alt Text Storage
- Stored in `wp_postmeta` table
- Meta key: `_wp_attachment_image_alt`
- Retrieved via: `get_post_meta($attachment_id, '_wp_attachment_image_alt', true)`
- Updated via: `update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text)`

### Attachment Metadata
- `wp_postmeta` with key `_wp_attachment_metadata`
- Contains image dimensions, file info, sizes
- Serialized array structure
- Retrieved via: `wp_get_attachment_metadata($attachment_id)`

## Media Library Interface

### List Table Structure
- Extends `WP_List_Table` class
- Located in `wp-admin/includes/class-wp-media-list-table.php`
- Bulk actions handled via `bulk_actions-upload` filter
- Custom columns via `manage_media_columns` filter

### Bulk Actions Implementation
```php
// Add bulk action
add_filter('bulk_actions-upload', 'add_custom_bulk_action');
function add_custom_bulk_action($bulk_actions) {
    $bulk_actions['generate_descriptions'] = 'Generate Descriptions';
    return $bulk_actions;
}

// Handle bulk action
add_filter('handle_bulk_actions-upload', 'handle_custom_bulk_action', 10, 3);
function handle_custom_bulk_action($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'generate_descriptions') {
        return $redirect_to;
    }
    // Process selected attachments
    return $redirect_to;
}
```

### Media Modal Integration
- `wp.media` JavaScript API
- Custom states and views
- Backbone.js architecture
- Event handling and data binding

## Querying Media Attachments

### WP_Query Parameters
```php
$args = array(
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
    'meta_query' => array(
        array(
            'key' => '_wp_attachment_image_alt',
            'compare' => 'NOT EXISTS'
        )
    )
);
```

### get_posts() Alternative
```php
$attachments = get_posts(array(
    'post_type' => 'attachment',
    'numberposts' => -1,
    'post_status' => null,
    'post_parent' => null,
    'post_mime_type' => 'image'
));
```

## Custom Fields and Metadata

### Adding Custom Meta Fields
- Use `add_meta_box()` for attachment edit screen
- Custom fields in media modal
- Validation and sanitization
- Database storage considerations

### Metadata Best Practices
- Prefix custom meta keys with plugin identifier
- Use appropriate data types
- Consider serialization for complex data
- Implement proper sanitization
