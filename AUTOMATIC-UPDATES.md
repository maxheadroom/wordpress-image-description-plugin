# Automatic Updates Setup Guide

This guide explains how to set up automatic updates for the WordPress Image Descriptions plugin so that WordPress installations can automatically check for and install new versions.

## 🎯 **Update Methods Available**

### **Option 1: GitHub Releases (Recommended)**
- ✅ **Free and easy to set up**
- ✅ **Automatic version detection**
- ✅ **Professional release management**
- ✅ **Public or private repositories supported**

### **Option 2: Custom Update Server**
- ✅ **Full control over update process**
- ✅ **Private distribution**
- ✅ **Custom licensing/authentication**
- ✅ **Advanced analytics**

### **Option 3: WordPress.org Repository**
- ✅ **Official WordPress plugin directory**
- ✅ **Maximum distribution**
- ✅ **Built-in update system**
- ✅ **Review process ensures quality**

## 🚀 **Option 1: GitHub Releases Setup**

### **Step 1: Configure Plugin Settings**

Edit `includes/class-plugin-updater.php` and update these lines:

```php
// GitHub repository information
$github_user = 'your-username'; // Change this to your GitHub username
$github_repo = 'wp-image-descriptions'; // Change this to your repository name
```

### **Step 2: Create GitHub Repository**

1. **Create Repository**: `https://github.com/your-username/wp-image-descriptions`
2. **Upload Plugin Files**: Push your plugin code to the repository
3. **Set Repository Visibility**: Public or private (both work)

### **Step 3: Create Releases**

For each new version:

1. **Update Version**: Edit `wp-image-descriptions.php` header
   ```php
   * Version: 1.0.4
   ```

2. **Package Plugin**: Run packaging script
   ```bash
   ./package-plugin.sh
   ```

3. **Create Git Tag**:
   ```bash
   git add .
   git commit -m "Version 1.0.4 - Bug fixes and improvements"
   git tag v1.0.4
   git push origin main
   git push origin v1.0.4
   ```

4. **Create GitHub Release**:
   - Go to `https://github.com/your-username/wp-image-descriptions/releases`
   - Click "Create a new release"
   - Choose tag: `v1.0.4`
   - Release title: `Version 1.0.4`
   - Description: Add changelog
   - **Upload ZIP file**: Attach `wp-image-descriptions-v1.0.4.zip`
   - Click "Publish release"

### **Step 4: Test Automatic Updates**

1. **Install Plugin**: Install current version on test site
2. **Create New Release**: Follow Step 3 with higher version number
3. **Check for Updates**: 
   - WordPress Admin → Plugins (should show update notification)
   - Or Settings → Image Descriptions → "Check for Updates"
4. **Update Plugin**: Click "Update now" in WordPress

## 🔧 **Option 2: Custom Update Server**

### **Server Requirements**
- PHP 7.4+ web server
- HTTPS support (recommended)
- Database (optional, for analytics)

### **Update Server API**

Create an endpoint that returns JSON with version information:

```php
<?php
// update-server.php
header('Content-Type: application/json');

$latest_version = array(
    'version' => '1.0.4',
    'download_url' => 'https://yourserver.com/downloads/wp-image-descriptions-v1.0.4.zip',
    'details_url' => 'https://yourserver.com/changelog',
    'tested' => '6.4',
    'requires_php' => '7.4',
    'changelog' => 'Bug fixes and performance improvements'
);

echo json_encode($latest_version);
?>
```

### **Configure Plugin**

Update `includes/class-plugin-updater.php`:

```php
private function get_remote_version() {
    $update_url = 'https://yourserver.com/api/check-updates.php';
    // ... rest of the method
}
```

## 📋 **Option 3: WordPress.org Repository**

### **Submission Process**

1. **Prepare Plugin**:
   - Follow WordPress coding standards
   - Add proper documentation
   - Include readme.txt file
   - Test thoroughly

2. **Submit to WordPress.org**:
   - Go to `https://wordpress.org/plugins/developers/add/`
   - Upload plugin ZIP file
   - Wait for review (can take weeks)

3. **Automatic Updates**:
   - WordPress.org handles all updates automatically
   - Users get updates through standard WordPress update system

## ⚙️ **Current Plugin Configuration**

### **Update Check Frequency**
- **Automatic**: Every 12 hours
- **Manual**: Settings page "Check for Updates" button
- **Cache**: Results cached to avoid excessive API calls

### **Version Comparison**
- Uses semantic versioning (SemVer)
- Compares: MAJOR.MINOR.PATCH
- Handles pre-release versions (alpha, beta, rc)

### **Update Process**
1. **Check**: WordPress queries update server
2. **Compare**: Compares current vs. available version
3. **Notify**: Shows update notification if newer version available
4. **Download**: WordPress downloads new version
5. **Install**: Standard WordPress plugin update process
6. **Migrate**: Automatic database migrations if needed

## 🔍 **Testing Updates**

### **Local Testing**
```bash
# Create test release
git tag v1.0.5-test
git push origin v1.0.5-test

# Create GitHub release with test ZIP
# Test on staging site
```

### **Staging Environment**
1. **Install Current Version**: On staging site
2. **Create Test Release**: With higher version number
3. **Check Updates**: Should detect new version
4. **Update Plugin**: Test update process
5. **Verify Functionality**: Ensure everything works

### **Production Rollout**
1. **Test Thoroughly**: On staging environment
2. **Create Release**: Follow proper release process
3. **Monitor**: Watch for update notifications
4. **Support**: Be ready to help users with issues

## 🛠️ **Troubleshooting**

### **Updates Not Detected**
- Check GitHub repository URL in plugin code
- Verify GitHub releases are public
- Check WordPress error logs for API errors
- Test manual update check in settings

### **Download Fails**
- Ensure ZIP file is attached to GitHub release
- Check file permissions and accessibility
- Verify download URL is correct
- Test download URL manually

### **Update Process Fails**
- Check WordPress file permissions
- Verify plugin folder is writable
- Look for plugin conflicts
- Check PHP memory limits

## 📊 **Update Analytics**

### **Tracking Updates**
```php
// Add to plugin updater
private function log_update_check() {
    $stats = array(
        'site_url' => home_url(),
        'wp_version' => get_bloginfo('version'),
        'plugin_version' => $this->version,
        'php_version' => PHP_VERSION,
        'timestamp' => time()
    );
    
    // Send to your analytics server
    wp_remote_post('https://yourserver.com/api/update-stats', array(
        'body' => $stats
    ));
}
```

### **Metrics to Track**
- Update check frequency
- Version adoption rates
- WordPress/PHP version compatibility
- Geographic distribution
- Update success/failure rates

## 🔐 **Security Considerations**

### **HTTPS Required**
- Always use HTTPS for update servers
- Verify SSL certificates
- Protect against man-in-the-middle attacks

### **File Integrity**
```php
// Add checksum verification
$expected_hash = 'sha256_hash_of_zip_file';
$actual_hash = hash_file('sha256', $downloaded_file);

if ($expected_hash !== $actual_hash) {
    // Reject update
}
```

### **Authentication** (for private plugins)
```php
// Add API key authentication
$headers = array(
    'Authorization' => 'Bearer ' . $api_key,
    'User-Agent' => 'WordPress/' . get_bloginfo('version')
);
```

## 🎯 **Best Practices**

### **Release Management**
- ✅ **Semantic Versioning**: Use proper version numbering
- ✅ **Changelog**: Always include detailed changelog
- ✅ **Testing**: Thoroughly test before release
- ✅ **Rollback Plan**: Be prepared to rollback if needed

### **User Communication**
- ✅ **Clear Descriptions**: Explain what's new/fixed
- ✅ **Breaking Changes**: Clearly mark breaking changes
- ✅ **Migration Notes**: Explain any required actions
- ✅ **Support**: Provide support channels

### **Technical**
- ✅ **Caching**: Cache update checks to reduce server load
- ✅ **Error Handling**: Graceful handling of failed checks
- ✅ **Logging**: Log update activities for debugging
- ✅ **Backwards Compatibility**: Maintain compatibility when possible

## 🚀 **Quick Start Checklist**

- [ ] Choose update method (GitHub recommended)
- [ ] Update plugin code with your repository details
- [ ] Create GitHub repository
- [ ] Test packaging script
- [ ] Create first release
- [ ] Test update process on staging site
- [ ] Document release process
- [ ] Set up monitoring/analytics
- [ ] Plan support process
- [ ] Go live!

## 📞 **Support**

For help setting up automatic updates:
1. Check WordPress error logs
2. Test update URLs manually
3. Verify GitHub repository settings
4. Check plugin configuration
5. Test on clean WordPress installation
