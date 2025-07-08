# Plugin Update Instructions

## Issue: Multiple Plugin Versions

If you're seeing a "Cannot redeclare function" error, it means WordPress has loaded multiple versions of the plugin simultaneously. This happens when WordPress creates separate plugin folders instead of replacing the existing one.

## How to Fix This

### Method 1: Clean Update (Recommended)

1. **Go to Plugins Page**: WordPress Admin → Plugins → Installed Plugins
2. **Deactivate ALL versions** of "WordPress Image Descriptions"
3. **Delete ALL versions** of the plugin:
   - Look for folders like `wp-image-descriptions-v1.0.1`, `wp-image-descriptions-v1.0.2`, etc.
   - Delete each one using "Delete" link
4. **Upload New Version**: Plugins → Add New → Upload Plugin
5. **Upload** `wp-image-descriptions-v1.0.2.zip`
6. **Activate** the plugin

### Method 2: Manual Cleanup (Advanced)

1. **Access your server** via FTP/SSH
2. **Navigate to** `/wp-content/plugins/`
3. **Delete ALL plugin folders** starting with `wp-image-descriptions`
4. **Upload and extract** the new plugin ZIP file
5. **Rename the folder** to just `wp-image-descriptions` (remove version suffix)
6. **Activate** the plugin in WordPress admin

### Method 3: WordPress CLI (If Available)

```bash
# Deactivate all versions
wp plugin deactivate wp-image-descriptions

# Delete all versions
wp plugin delete wp-image-descriptions

# Install new version
wp plugin install wp-image-descriptions-v1.0.2.zip --activate
```

## Prevention for Future Updates

### For Plugin Developers:
- Always use the same plugin folder name
- Include function_exists() checks for critical functions
- Test update process thoroughly

### For Users:
- Always deactivate old version before uploading new one
- Check plugins page for duplicate entries
- Use WordPress's "Replace current with uploaded" option when available

## Verifying Successful Update

After updating, verify:

1. **Only ONE plugin entry** in Plugins page
2. **Version shows 1.0.2** (or latest)
3. **No error messages** in WordPress admin
4. **Plugin functions normally**

## Troubleshooting

### Still Getting Errors?

1. **Check Error Logs**: Look for specific file paths in error messages
2. **Clear Cache**: Clear any caching plugins or server cache
3. **Restart Web Server**: If possible, restart Apache/Nginx
4. **WordPress Debug**: Enable WP_DEBUG to see detailed errors

### Plugin Not Working After Update?

1. **Check Database**: Go to Tools → Image Descriptions Debug
2. **Verify Tables**: Ensure database tables exist
3. **Test API**: Check Settings → Image Descriptions → Test Connection
4. **Review Logs**: Check WordPress error logs for issues

## Database Preservation

The plugin update process preserves:
- ✅ **Settings**: API configuration, prompts, processing options
- ✅ **Database Tables**: Batch history and job records
- ✅ **Generated Descriptions**: Previously applied alt text remains

## Support

If you continue having issues:

1. **Check WordPress Version**: Ensure WordPress 5.0+
2. **PHP Version**: Ensure PHP 7.4+
3. **Plugin Conflicts**: Deactivate other plugins temporarily
4. **Theme Issues**: Switch to default theme temporarily

## Version History

- **1.0.0**: Initial release
- **1.0.1**: Auto-refresh improvements
- **1.0.2**: Test mode results display fix + update safety improvements
