#!/bin/bash

# WordPress Image Descriptions Plugin Packaging Script
# This script automatically packages the plugin for WordPress installation
# and uses the current version number from the plugin file

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${BLUE}WordPress Image Descriptions - Plugin Packager${NC}"
echo "=================================================="

# Check if main plugin file exists
PLUGIN_FILE="wp-image-descriptions.php"
if [ ! -f "$PLUGIN_FILE" ]; then
    echo -e "${RED}Error: Plugin file '$PLUGIN_FILE' not found!${NC}"
    exit 1
fi

# Extract version number from plugin file
echo -e "${YELLOW}Extracting version number...${NC}"
VERSION=$(grep "Version:" "$PLUGIN_FILE" | head -1 | sed 's/.*Version: *\([0-9.]*\).*/\1/')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version number from $PLUGIN_FILE${NC}"
    echo "Make sure the plugin file contains a line like: * Version: 1.0.3"
    exit 1
fi

echo -e "${GREEN}Found version: $VERSION${NC}"

# Define package details
PLUGIN_SLUG="wp-image-descriptions"
PACKAGE_NAME="${PLUGIN_SLUG}-v${VERSION}"
TEMP_DIR="${PACKAGE_NAME}"
ZIP_FILE="${PACKAGE_NAME}.zip"

# Clean up any existing package
if [ -f "$ZIP_FILE" ]; then
    echo -e "${YELLOW}Removing existing package: $ZIP_FILE${NC}"
    rm "$ZIP_FILE"
fi

if [ -d "$TEMP_DIR" ]; then
    echo -e "${YELLOW}Removing existing temp directory: $TEMP_DIR${NC}"
    rm -rf "$TEMP_DIR"
fi

# Create temporary directory structure
echo -e "${YELLOW}Creating package structure...${NC}"
mkdir -p "$TEMP_DIR/$PLUGIN_SLUG"

# Copy plugin files
echo -e "${YELLOW}Copying plugin files...${NC}"

# Main plugin file
cp "$PLUGIN_FILE" "$TEMP_DIR/$PLUGIN_SLUG/"

# Includes directory
if [ -d "includes" ]; then
    cp -r "includes" "$TEMP_DIR/$PLUGIN_SLUG/"
    echo "  ✓ Copied includes directory"
else
    echo -e "${RED}Warning: includes directory not found${NC}"
fi

# Documentation files
for file in README.md VERSION.md UPDATE-INSTRUCTIONS.md LICENSE.txt CHANGELOG.md; do
    if [ -f "$file" ]; then
        cp "$file" "$TEMP_DIR/$PLUGIN_SLUG/"
        echo "  ✓ Copied $file"
    fi
done

# Assets directory (if exists)
if [ -d "assets" ]; then
    cp -r "assets" "$TEMP_DIR/$PLUGIN_SLUG/"
    echo "  ✓ Copied assets directory"
fi

# Languages directory (if exists)
if [ -d "languages" ]; then
    cp -r "languages" "$TEMP_DIR/$PLUGIN_SLUG/"
    echo "  ✓ Copied languages directory"
fi

# Create ZIP package
echo -e "${YELLOW}Creating ZIP package...${NC}"
cd "$TEMP_DIR"
zip -r "../$ZIP_FILE" "$PLUGIN_SLUG/" -q

# Return to original directory
cd "$SCRIPT_DIR"

# Clean up temporary directory
rm -rf "$TEMP_DIR"

# Get file size
if command -v stat >/dev/null 2>&1; then
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        FILE_SIZE=$(stat -f%z "$ZIP_FILE")
    else
        # Linux
        FILE_SIZE=$(stat -c%s "$ZIP_FILE")
    fi
    FILE_SIZE_KB=$((FILE_SIZE / 1024))
else
    FILE_SIZE_KB="unknown"
fi

# Success message
echo ""
echo -e "${GREEN}✅ Package created successfully!${NC}"
echo "=================================================="
echo -e "${BLUE}Package Details:${NC}"
echo "  📦 File: $ZIP_FILE"
echo "  🏷️  Version: $VERSION"
echo "  📁 Plugin Folder: $PLUGIN_SLUG"
if [ "$FILE_SIZE_KB" != "unknown" ]; then
    echo "  📊 Size: ${FILE_SIZE_KB} KB"
fi
echo ""
echo -e "${GREEN}Ready for WordPress installation!${NC}"
echo ""

# Optional: Show package contents
echo -e "${BLUE}Package Contents:${NC}"
unzip -l "$ZIP_FILE" | grep -E "^\s*[0-9]+" | awk '{print "  " $4}' | head -20

# Count total files
TOTAL_FILES=$(unzip -l "$ZIP_FILE" | grep -E "^\s*[0-9]+" | wc -l | tr -d ' ')
if [ "$TOTAL_FILES" -gt 20 ]; then
    echo "  ... and $((TOTAL_FILES - 20)) more files"
fi

echo ""
echo -e "${YELLOW}Installation Instructions:${NC}"
echo "1. Go to WordPress Admin → Plugins → Add New → Upload Plugin"
echo "2. Choose file: $ZIP_FILE"
echo "3. Click 'Install Now' and then 'Activate'"
echo ""

# Optional: Validate package
echo -e "${YELLOW}Validating package...${NC}"
VALIDATION_ERRORS=0

# Check if main plugin file is in the package
if ! unzip -l "$ZIP_FILE" | grep -q "$PLUGIN_SLUG/$PLUGIN_FILE"; then
    echo -e "${RED}❌ Main plugin file not found in package${NC}"
    VALIDATION_ERRORS=$((VALIDATION_ERRORS + 1))
fi

# Check if includes directory is in the package
if ! unzip -l "$ZIP_FILE" | grep -q "$PLUGIN_SLUG/includes/"; then
    echo -e "${RED}❌ Includes directory not found in package${NC}"
    VALIDATION_ERRORS=$((VALIDATION_ERRORS + 1))
fi

# Check package structure
if ! unzip -l "$ZIP_FILE" | grep -q "^.*$PLUGIN_SLUG/$"; then
    echo -e "${RED}❌ Incorrect package structure${NC}"
    VALIDATION_ERRORS=$((VALIDATION_ERRORS + 1))
fi

if [ $VALIDATION_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ Package validation passed${NC}"
else
    echo -e "${RED}❌ Package validation failed with $VALIDATION_ERRORS errors${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}🎉 Packaging complete! Ready to distribute.${NC}"
