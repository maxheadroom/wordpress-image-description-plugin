# Plugin Packaging Scripts

This directory contains automated scripts to package the WordPress Image Descriptions plugin for distribution.

## Scripts Available

### 1. `package-plugin.sh` (Full Production Packaging)

**Purpose**: Creates a complete, production-ready plugin package with validation.

**Features**:
- ✅ Automatically extracts version number from plugin file
- ✅ Creates properly structured WordPress plugin ZIP
- ✅ Includes all necessary files (PHP, documentation, assets)
- ✅ Validates package structure and contents
- ✅ Provides detailed output and file size information
- ✅ Color-coded terminal output for easy reading

**Usage**:
```bash
./package-plugin.sh
```

**Output**: `wp-image-descriptions-v1.0.3.zip` (version number from plugin file)

### 2. `dev-package.sh` (Quick Development Packaging)

**Purpose**: Fast packaging for development and testing cycles.

**Features**:
- ⚡ Quick and minimal packaging
- ✅ Includes core files only (PHP + includes + README)
- ✅ Automatic version detection
- ✅ Minimal output for speed

**Usage**:
```bash
./dev-package.sh
```

**Output**: `wp-image-descriptions-v1.0.3-dev.zip`

## How Version Detection Works

Both scripts automatically extract the version number from the main plugin file:

```php
/**
 * Version: 1.0.7
 */
```

The scripts use this regex pattern:
```bash
VERSION=$(grep "Version:" wp-image-descriptions.php | head -1 | sed 's/.*Version: *\([0-9.]*\).*/\1/')
```

## Package Structure

The scripts create a WordPress-compatible package structure:

```
wp-image-descriptions-v1.0.3.zip
└── wp-image-descriptions/
    ├── wp-image-descriptions.php
    ├── includes/
    │   ├── class-api-client.php
    │   ├── class-batch-manager.php
    │   ├── class-media-library.php
    │   └── ... (all other classes)
    ├── README.md
    ├── VERSION.md
    ├── UPDATE-INSTRUCTIONS.md
    └── ... (other documentation)
```

## Files Included

### Always Included:
- `wp-image-descriptions.php` (main plugin file)
- `includes/` directory (all PHP classes)

### Conditionally Included (if they exist):
- `README.md`
- `VERSION.md`
- `UPDATE-INSTRUCTIONS.md`
- `LICENSE.txt`
- `CHANGELOG.md`
- `assets/` directory
- `languages/` directory

### Always Excluded:
- `.git/` directory
- `.DS_Store` files
- Development files (`package-plugin.sh`, `dev-package.sh`, etc.)
- Temporary files and directories

## Usage Examples

### Production Release:
```bash
# Update version in wp-image-descriptions.php to 1.0.4
# Then run:
./package-plugin.sh

# Output: wp-image-descriptions-v1.0.4.zip
# Ready for distribution
```

### Development Testing:
```bash
# Quick package for testing
./dev-package.sh

# Output: wp-image-descriptions-v1.0.3-dev.zip
# Upload to test site
```

### Automated Build Process:
```bash
# In CI/CD pipeline
chmod +x package-plugin.sh
./package-plugin.sh

# Package is ready for release
```

## Validation Features

The `package-plugin.sh` script includes validation:

### Structure Validation:
- ✅ Main plugin file exists in package
- ✅ Includes directory is present
- ✅ Correct folder structure (wp-image-descriptions/)

### Content Validation:
- ✅ File count and size reporting
- ✅ Package contents listing
- ✅ Error detection and reporting

### Example Output:
```
✅ Package created successfully!
==================================================
📦 File: wp-image-descriptions-v1.0.3.zip
🏷️  Version: 1.0.3
📁 Plugin Folder: wp-image-descriptions
📊 Size: 45 KB

Package Contents:
  wp-image-descriptions/wp-image-descriptions.php
  wp-image-descriptions/includes/class-api-client.php
  wp-image-descriptions/includes/class-batch-manager.php
  ... and 12 more files

✅ Package validation passed
🎉 Packaging complete! Ready to distribute.
```

## Troubleshooting

### Script Won't Run:
```bash
# Make sure script is executable
chmod +x package-plugin.sh
chmod +x dev-package.sh
```

### Version Not Detected:
- Check that `wp-image-descriptions.php` contains: `* Version: X.X.X`
- Ensure no extra spaces or characters around version number

### Missing Files in Package:
- Check that files exist in the root directory
- Verify file permissions allow reading

### Package Validation Fails:
- Check WordPress error logs for specific issues
- Verify package structure matches WordPress requirements
- Test package installation on clean WordPress site

## Integration with Development Workflow

### Version Bump Process:
1. Update version in `wp-image-descriptions.php`
2. Update version in constant: `WP_IMAGE_DESCRIPTIONS_VERSION`
3. Update `README.md` changelog
4. Run `./package-plugin.sh`
5. Test package on staging site
6. Distribute or release

### Automated Releases:
```bash
# Example GitHub Actions workflow
- name: Package Plugin
  run: |
    chmod +x package-plugin.sh
    ./package-plugin.sh
    
- name: Upload Release Asset
  uses: actions/upload-release-asset@v1
  with:
    asset_path: ./wp-image-descriptions-v*.zip
```

## Best Practices

### Before Packaging:
- ✅ Test plugin functionality thoroughly
- ✅ Update version number in plugin file
- ✅ Update changelog and documentation
- ✅ Run code quality checks
- ✅ Test on clean WordPress installation

### After Packaging:
- ✅ Test package installation process
- ✅ Verify all files are included
- ✅ Check plugin activation and functionality
- ✅ Test update process from previous version
- ✅ Validate on multiple WordPress versions

### Security Considerations:
- 🔒 Never include sensitive data (API keys, passwords)
- 🔒 Exclude development tools and scripts
- 🔒 Validate all included files are necessary
- 🔒 Test package on isolated environment first
