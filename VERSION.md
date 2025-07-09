# Version Management

This plugin uses **Semantic Versioning (SemVer)** for version management and includes automatic update functionality.

## Semantic Versioning Format

The plugin follows the SemVer format: `MAJOR.MINOR.PATCH[-PRERELEASE][+BUILD]`

### Version Components

- **MAJOR**: Incremented for incompatible API changes
- **MINOR**: Incremented for backwards-compatible functionality additions
- **PATCH**: Incremented for backwards-compatible bug fixes
- **PRERELEASE**: Optional identifier for pre-release versions (alpha, beta, rc)
- **BUILD**: Optional build metadata

### Examples

- `1.0.0` - Stable release
- `1.1.0` - Minor feature update
- `1.0.1` - Bug fix release
- `2.0.0` - Major version with breaking changes
- `1.1.0-beta.1` - Beta pre-release
- `1.0.0-alpha.2+build.123` - Alpha with build metadata

## Version Types

### Stable Releases
- `1.0.0`, `1.1.0`, `1.0.1` - Production-ready versions
- Recommended for all users
- Full feature set with comprehensive testing

### Pre-Release Versions
- **Alpha** (`1.1.0-alpha.1`) - Early development, may have bugs
- **Beta** (`1.1.0-beta.1`) - Feature-complete, testing phase
- **Release Candidate** (`1.1.0-rc.1`) - Final testing before stable

## Automatic Updates

### Update Sources
The plugin checks for updates from:
1. **GitHub Releases** (primary) - `https://github.com/maxheadroom/wordpress-image-description-plugin/releases`


### Update Process
1. **Check**: WordPress checks for updates every 12 hours
2. **Compare**: Uses SemVer comparison to determine if update is available
3. **Notify**: Shows update notification in WordPress admin
4. **Install**: Standard WordPress update process
5. **Migrate**: Automatic database migrations if needed

### Manual Update Check
- Go to **Tools → Image Descriptions Debug**
- Click **"Check for Updates"** button
- Forces immediate update check

## Database Versioning

### Database Schema Versions
- Database schema has its own version tracking
- Stored in `wp_image_descriptions_db_version` option
- Automatic migrations during plugin updates

### Migration Process
```php
// Example migration for v1.1.0
if (version_compare($current_db_version, '1.1.0', '<')) {
    // Add new column
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN new_field VARCHAR(255)");
    
    // Update version
    update_option('wp_image_descriptions_db_version', '1.1.0');
}
```

## Release Process

### 1. Version Bump
Update version in these files:
- `wp-image-descriptions.php` (Plugin header)
- `wp-image-descriptions.php` (WP_IMAGE_DESCRIPTIONS_VERSION constant)
- `README.md` (Changelog section)

### 2. Database Updates
If database changes are needed:
- Update `class-plugin-updater.php` with migration logic
- Increment database version in updater class
- Test migration thoroughly

### 3. Testing
- Test update process from previous version
- Verify database migrations work correctly
- Test both manual and automatic updates
- Check version display in diagnostics

### 4. Release
- Create Git tag with version number: `git tag v1.1.0`
- Push tag: `git push origin v1.1.0`
- Create GitHub release with changelog
- Upload plugin ZIP file to release

### 5. Distribution
- Plugin automatically detects new version
- Users receive update notification
- Standard WordPress update process applies

## Version Comparison Logic

### SemVer Comparison Rules
1. **Major.Minor.Patch** compared numerically
2. **Pre-release** versions are lower than stable
3. **Build metadata** is ignored in comparisons
4. **Pre-release identifiers** compared lexically

### Examples
```
1.0.0-alpha < 1.0.0-alpha.1 < 1.0.0-beta < 1.0.0-rc.1 < 1.0.0 < 1.0.1 < 1.1.0 < 2.0.0
```

## Troubleshooting Updates

### Update Not Detected
1. Check internet connection
2. Verify GitHub repository is accessible
3. Force update check in diagnostics
4. Check WordPress error logs

### Update Fails
1. Check file permissions
2. Verify WordPress has write access
3. Try manual plugin upload
4. Check for plugin conflicts

### Database Migration Issues
1. Check database permissions
2. Review error logs for SQL errors
3. Manually run migration if needed
4. Contact support with specific error messages

## Development Guidelines

### Version Increment Rules
- **Patch** (1.0.1): Bug fixes, security patches, minor improvements
- **Minor** (1.1.0): New features, API additions, backwards-compatible changes
- **Major** (2.0.0): Breaking changes, API removals, major restructuring

### Pre-Release Guidelines
- Use **alpha** for early development versions
- Use **beta** for feature-complete testing versions
- Use **rc** (release candidate) for final testing
- Always test upgrade path from stable to pre-release

### Database Migration Best Practices
- Always backup before migrations
- Use conditional logic to check if changes are needed
- Test migrations on copy of production data
- Provide rollback procedures for major changes
- Log migration progress and errors

## Version History

### 1.0.6 (Current)
- Fixed auto-refresh logic on processing pages
- Improved real-time status detection
- Enhanced user experience during batch processing
- Bug fix release (patch version)

### 1.0.0
- Initial stable release
- Complete MVP functionality
- Automatic update system
- Semantic versioning implementation
