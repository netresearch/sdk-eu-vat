#!/bin/bash

# EU VAT SDK Release Preparation Script
# This script prepares the package for release and Packagist submission

set -e

echo "=== EU VAT SDK Release Preparation ==="
echo

# Check if we're on main branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo "Error: Must be on main branch to prepare release"
    echo "Current branch: $CURRENT_BRANCH"
    exit 1
fi

# Check for uncommitted changes
if ! git diff-index --quiet HEAD --; then
    echo "Error: Uncommitted changes found"
    echo "Please commit or stash changes before preparing release"
    exit 1
fi

# Get version from user
read -p "Enter version number (e.g., 1.0.0): " VERSION

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Invalid version format. Use semantic versioning (e.g., 1.0.0)"
    exit 1
fi

echo
echo "Preparing release v$VERSION..."
echo

# Check CHANGELOG.md for the release section
echo "1. Checking CHANGELOG.md..."
if ! grep -q "^## \\[$VERSION\\]" CHANGELOG.md; then
    echo "Error: CHANGELOG.md has no '## [$VERSION]' section."
    echo "Please move the entries from '## [Unreleased]' into a"
    echo "'## [$VERSION] - $(date +%Y-%m-%d)' section manually, then re-run this script."
    exit 1
fi

# Run all tests
echo
echo "2. Running all tests..."
composer test

# Run static analysis
echo
echo "3. Running static analysis..."
composer analyse

# Run code style check
echo
echo "4. Checking code style..."
composer cs-check

# Validate composer.json
echo
echo "5. Validating composer.json..."
composer validate --strict

# Run package validation
echo
echo "6. Running package validation..."
php tests/validate-package.php

# Run security check
echo
echo "7. Running security check..."
if ! composer audit; then
    echo
    echo "WARNING: composer audit reported security advisories (see above)."
    read -p "Continue with the release anyway? [y/N] " AUDIT_CONTINUE
    if [[ ! "$AUDIT_CONTINUE" =~ ^[Yy]$ ]]; then
        echo "Release aborted."
        exit 1
    fi
fi

# Create release tag
echo
echo "8. Creating git tag..."
git tag -a "v$VERSION" -m "Release version $VERSION"

echo
echo "=== Release Preparation Complete ==="
echo
echo "Next steps:"
echo "1. Push changes: git push origin main"
echo "2. Push tag: git push origin v$VERSION"
echo "3. Create GitHub release using the CHANGELOG.md section for v$VERSION"
echo "4. Submit to Packagist:"
echo "   - Go to https://packagist.org/packages/submit"
echo "   - Enter repository URL: https://github.com/netresearch/sdk-eu-vat"
echo "5. Set up webhook for automatic updates:"
echo "   - In GitHub: Settings > Webhooks > Add webhook"
echo "   - Payload URL: https://packagist.org/api/github"
echo "   - Content type: application/json"
echo "   - Secret: (from Packagist profile)"
echo
echo "Package is ready for distribution!"