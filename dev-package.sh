#!/bin/bash

# Quick Development Packaging Script
# Simplified version for rapid development cycles

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Get version
VERSION=$(grep "Version:" wp-image-descriptions.php | head -1 | sed 's/.*Version: *\([0-9.]*\).*/\1/')
PACKAGE_NAME="wp-image-descriptions-v${VERSION}-dev"

echo -e "${YELLOW}Quick packaging v${VERSION}...${NC}"

# Clean up
rm -f "${PACKAGE_NAME}.zip"
rm -rf temp-package

# Create package
mkdir -p temp-package/wp-image-descriptions
cp wp-image-descriptions.php temp-package/wp-image-descriptions/
cp -r includes temp-package/wp-image-descriptions/
[ -f README.md ] && cp README.md temp-package/wp-image-descriptions/

# Create ZIP
cd temp-package
zip -r "../${PACKAGE_NAME}.zip" wp-image-descriptions/ -q
cd ..

# Clean up
rm -rf temp-package

echo -e "${GREEN}✅ Created: ${PACKAGE_NAME}.zip${NC}"
