#!/bin/bash
# Deployment-Ready System Setup Script
# This script automates the deployment checklist fixes

echo "🚀 Starting Deployment-Ready System Setup..."
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Remove old backup files with hardcoded credentials
echo "📁 Step 1: Removing old backup files with hardcoded credentials..."
FILES_TO_REMOVE=(
    "device/smoke_api_old.php"
    "device/smoke_old.php"
)

for file in "${FILES_TO_REMOVE[@]}"; do
    if [ -f "$file" ]; then
        rm "$file"
        echo -e "${GREEN}✓${NC} Removed: $file"
    else
        echo -e "${YELLOW}⚠${NC}  Not found: $file (already removed or doesn't exist)"
    fi
done

# Step 2: Fix remaining files with hardcoded credentials
echo ""
echo "🔐 Step 2: Checking for remaining hardcoded credentials..."
grep -r "password.*=.*[\"'][^\"']\{8,\}[\"']" --include="*.php" | grep -v ".md" | grep -v "example" | grep -v "SECURITY_FIXES" | while read line; do
    echo -e "${RED}✗${NC} Found hardcoded credential: $line"
done

# Step 3: Create .env file from example if it doesn't exist
echo ""
echo "⚙️  Step 3: Setting up environment configuration..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}✓${NC} Created .env file from .env.example"
        echo -e "${YELLOW}⚠${NC}  Please edit .env file with your actual credentials!"
    else
        echo -e "${RED}✗${NC} .env.example not found. Please create .env manually."
    fi
else
    echo -e "${GREEN}✓${NC} .env file already exists"
fi

# Step 4: Ensure logs directory exists
echo ""
echo "📝 Step 4: Setting up log directories..."
mkdir -p logs
chmod 755 logs
echo -e "${GREEN}✓${NC} Log directory created/verified"

# Step 5: Ensure uploads directory exists with correct permissions
echo ""
echo "📤 Step 5: Setting up upload directories..."
mkdir -p uploads
chmod 755 uploads
echo -e "${GREEN}✓${NC} Upload directory created/verified"

# Step 6: Set file permissions
echo ""
echo "🔒 Step 6: Setting secure file permissions..."
if [ -f ".env" ]; then
    chmod 600 .env
    echo -e "${GREEN}✓${NC} Set .env permissions to 600"
fi

# Step 7: Verify core modules exist
echo ""
echo "📦 Step 7: Verifying core modules..."
CORE_MODULES=(
    "core/bootstrap.php"
    "core/config/config.php"
    "core/database/database.php"
    "core/security/security.php"
    "core/session/session.php"
)

for module in "${CORE_MODULES[@]}"; do
    if [ -f "$module" ]; then
        echo -e "${GREEN}✓${NC} Found: $module"
    else
        echo -e "${RED}✗${NC} Missing: $module"
    fi
done

# Step 8: Check for debug code
echo ""
echo "🐛 Step 8: Checking for debug code..."
DEBUG_PATTERNS=(
    "console.log"
    "var_dump"
    "print_r"
    "error_log.*debug"
)

echo "Scanning for debug patterns..."
for pattern in "${DEBUG_PATTERNS[@]}"; do
    count=$(grep -r "$pattern" --include="*.php" --include="*.js" 2>/dev/null | wc -l)
    if [ "$count" -gt 0 ]; then
        echo -e "${YELLOW}⚠${NC}  Found $count instances of: $pattern"
    fi
done

echo ""
echo "✨ Deployment-Ready Setup Complete!"
echo ""
echo "📋 Next Steps:"
echo "  1. Edit .env file with your actual credentials"
echo "  2. Test database connection: php scripts/test_connection.php"
echo "  3. Review DEPLOYMENT_CHECKLIST.md for remaining items"
echo "  4. Run security tests"
echo ""

