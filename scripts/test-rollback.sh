#!/bin/bash
# Rollback Testing Script for Linux
# This script tests the rollback procedures in a safe manner

set -e

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/tmp/backups/test}"
APP_DIR="${APP_DIR:-/var/www/html}"
SKIP_DATABASE="${SKIP_DATABASE:-false}"

# Test configuration
TEST_MARKER_FILE="rollback_test_marker.txt"
TEST_MARKER_CONTENT="ROLLBACK_TEST_$(date +%Y%m%d_%H%M%S)"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

print_header() {
    echo ""
    echo "========================================"
    echo "   ROLLBACK TESTING FRAMEWORK"
    echo "========================================"
    echo ""
    echo "Test Directory: $BACKUP_DIR"
    echo "App Directory:  $APP_DIR"
    echo "Skip Database:  $SKIP_DATABASE"
    echo ""
}

test_result() {
    local test_name="$1"
    local passed="$2"
    local message="${3:-}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$passed" = "true" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}✗ FAIL${NC}: $test_name"
    fi
    
    if [ -n "$message" ]; then
        echo "  └─ $message"
    fi
}

test_prerequisites() {
    echo -e "\n${CYAN}[1/7] Testing Prerequisites...${NC}"
    
    # Test backup script exists
    if [ -f "scripts/create-backup.sh" ]; then
        test_result "Backup script exists" "true" "scripts/create-backup.sh"
    else
        test_result "Backup script exists" "false" "scripts/create-backup.sh not found"
        return 1
    fi
    
    # Test rollback script exists
    if [ -f "scripts/create-rollback.sh" ]; then
        test_result "Rollback script exists" "true" "scripts/create-rollback.sh"
    else
        test_result "Rollback script exists" "false" "scripts/create-rollback.sh not found"
        return 1
    fi
    
    # Test app directory exists
    if [ -d "$APP_DIR" ]; then
        test_result "Application directory exists" "true" "$APP_DIR"
    else
        test_result "Application directory exists" "false" "$APP_DIR not found"
        return 1
    fi
    
    # Test write permissions
    if touch "$APP_DIR/test_write_permission.tmp" 2>/dev/null; then
        rm -f "$APP_DIR/test_write_permission.tmp"
        test_result "Write permissions OK" "true"
    else
        test_result "Write permissions OK" "false" "Cannot write to $APP_DIR"
        return 1
    fi
    
    return 0
}

test_backup_creation() {
    echo -e "\n${CYAN}[2/7] Testing Backup Creation...${NC}"
    
    # Create test backup directory
    if mkdir -p "$BACKUP_DIR" 2>/dev/null; then
        test_result "Backup directory created" "true" "$BACKUP_DIR"
    else
        test_result "Backup directory created" "false" "Cannot create $BACKUP_DIR"
        return 1
    fi
    
    # Create test marker file
    if echo "$TEST_MARKER_CONTENT" > "$APP_DIR/$TEST_MARKER_FILE"; then
        test_result "Test marker file created" "true" "$APP_DIR/$TEST_MARKER_FILE"
    else
        test_result "Test marker file created" "false"
        return 1
    fi
    
    # Run backup script
    if BACKUP_DIR="$BACKUP_DIR" APP_DIR="$APP_DIR" bash scripts/create-backup.sh > /dev/null 2>&1; then
        test_result "Backup script executed" "true"
    else
        test_result "Backup script executed" "false" "Exit code: $?"
        return 1
    fi
    
    # Verify backup was created
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR" | grep "backup_" | head -n 1)
    if [ -n "$LATEST_BACKUP" ]; then
        TEST_BACKUP_PATH="$BACKUP_DIR/$LATEST_BACKUP"
        test_result "Backup directory created" "true" "$TEST_BACKUP_PATH"
    else
        test_result "Backup directory created" "false" "No backup found"
        return 1
    fi
    
    # Verify backup contains files
    FILE_COUNT=$(find "$TEST_BACKUP_PATH" -type f | wc -l)
    if [ "$FILE_COUNT" -gt 0 ]; then
        test_result "Backup contains files" "true" "Files: $FILE_COUNT"
    else
        test_result "Backup contains files" "false"
        return 1
    fi
    
    return 0
}

test_marker_modification() {
    echo -e "\n${CYAN}[3/7] Testing Marker Modification...${NC}"
    
    # Modify the marker file
    MODIFIED_CONTENT="MODIFIED_VERSION_$(date +%Y%m%d_%H%M%S)"
    if echo "$MODIFIED_CONTENT" > "$APP_DIR/$TEST_MARKER_FILE"; then
        test_result "Marker file modified" "true"
    else
        test_result "Marker file modified" "false"
        return 1
    fi
    
    # Verify modification
    CURRENT_CONTENT=$(cat "$APP_DIR/$TEST_MARKER_FILE")
    if [ "$CURRENT_CONTENT" != "$TEST_MARKER_CONTENT" ]; then
        test_result "Marker content changed" "true"
    else
        test_result "Marker content changed" "false"
        return 1
    fi
    
    return 0
}

test_rollback_execution() {
    echo -e "\n${CYAN}[4/7] Testing Rollback Execution...${NC}"
    
    if [ -z "$TEST_BACKUP_PATH" ]; then
        test_result "Rollback execution" "false" "No backup path available"
        return 1
    fi
    
    # Get backup timestamp
    BACKUP_TIMESTAMP=$(basename "$TEST_BACKUP_PATH")
    
    # Run rollback script
    if BACKUP_DIR="$BACKUP_DIR" APP_DIR="$APP_DIR" bash scripts/create-rollback.sh "$BACKUP_TIMESTAMP" > /dev/null 2>&1; then
        test_result "Rollback script executed" "true"
    else
        test_result "Rollback script executed" "false" "Exit code: $?"
        return 1
    fi
    
    # Verify pre-rollback backup was created
    PRE_ROLLBACK_COUNT=$(ls -d "$BACKUP_DIR"/pre_rollback_* 2>/dev/null | wc -l)
    if [ "$PRE_ROLLBACK_COUNT" -gt 0 ]; then
        test_result "Pre-rollback backup created" "true"
    else
        test_result "Pre-rollback backup created" "false"
    fi
    
    return 0
}

test_rollback_verification() {
    echo -e "\n${CYAN}[5/7] Testing Rollback Verification...${NC}"
    
    # Verify marker file was restored
    if [ -f "$APP_DIR/$TEST_MARKER_FILE" ]; then
        RESTORED_CONTENT=$(cat "$APP_DIR/$TEST_MARKER_FILE" | tr -d '\n\r')
        EXPECTED_CONTENT=$(echo "$TEST_MARKER_CONTENT" | tr -d '\n\r')
        
        if [ "$RESTORED_CONTENT" = "$EXPECTED_CONTENT" ]; then
            test_result "Marker file restored" "true"
        else
            test_result "Marker file restored" "false" "Content mismatch"
            return 1
        fi
    else
        test_result "Marker file restored" "false" "Marker file not found"
        return 1
    fi
    
    # Verify file count
    FILE_COUNT=$(find "$APP_DIR" -type f | wc -l)
    if [ "$FILE_COUNT" -gt 0 ]; then
        test_result "Application files exist" "true" "Files: $FILE_COUNT"
    else
        test_result "Application files exist" "false"
        return 1
    fi
    
    # Verify critical files
    CRITICAL_FILES=("index.php" "composer.json" "core/config/config.php")
    for file in "${CRITICAL_FILES[@]}"; do
        if [ -f "$APP_DIR/$file" ]; then
            test_result "Critical file exists: $file" "true"
        else
            test_result "Critical file exists: $file" "false"
        fi
    done
    
    return 0
}

test_backup_integrity() {
    echo -e "\n${CYAN}[6/7] Testing Backup Integrity...${NC}"
    
    if [ -z "$TEST_BACKUP_PATH" ]; then
        test_result "Backup integrity check" "false" "No backup path available"
        return 1
    fi
    
    # Check backup info file
    if [ -f "$TEST_BACKUP_PATH/backup_info.txt" ]; then
        test_result "Backup info file exists" "true"
    else
        test_result "Backup info file exists" "false"
    fi
    
    # Check backup structure
    if [ -d "$TEST_BACKUP_PATH/files" ]; then
        test_result "Backup files directory exists" "true"
    else
        test_result "Backup files directory exists" "false"
    fi
    
    # Verify backup size
    BACKUP_SIZE=$(du -sm "$TEST_BACKUP_PATH" | cut -f1)
    if [ "$BACKUP_SIZE" -gt 0 ]; then
        test_result "Backup has content" "true" "Size: ${BACKUP_SIZE} MB"
    else
        test_result "Backup has content" "false"
    fi
    
    return 0
}

test_cleanup() {
    echo -e "\n${CYAN}[7/7] Testing Cleanup...${NC}"
    
    # Remove test marker file
    if rm -f "$APP_DIR/$TEST_MARKER_FILE" 2>/dev/null; then
        test_result "Test marker file removed" "true"
    else
        test_result "Test marker file removed" "false"
    fi
    
    echo -e "\n${YELLOW}⚠️  Test backups are preserved in: $BACKUP_DIR${NC}"
    echo "   You can safely delete this directory after reviewing the results."
    
    return 0
}

show_test_results() {
    echo ""
    echo "========================================"
    echo "         TEST RESULTS SUMMARY"
    echo "========================================"
    echo ""
    
    PASS_RATE=0
    if [ "$TOTAL_TESTS" -gt 0 ]; then
        PASS_RATE=$(( (PASSED_TESTS * 100) / TOTAL_TESTS ))
    fi
    
    echo "Total Tests:  $TOTAL_TESTS"
    echo -e "Passed:       ${GREEN}$PASSED_TESTS${NC}"
    
    if [ "$FAILED_TESTS" -eq 0 ]; then
        echo -e "Failed:       ${GREEN}$FAILED_TESTS${NC}"
    else
        echo -e "Failed:       ${RED}$FAILED_TESTS${NC}"
    fi
    
    if [ "$PASS_RATE" -ge 90 ]; then
        echo -e "Pass Rate:    ${GREEN}$PASS_RATE%${NC}"
    elif [ "$PASS_RATE" -ge 70 ]; then
        echo -e "Pass Rate:    ${YELLOW}$PASS_RATE%${NC}"
    else
        echo -e "Pass Rate:    ${RED}$PASS_RATE%${NC}"
    fi
    
    echo ""
    echo "========================================"
    echo ""
    
    if [ "$FAILED_TESTS" -eq 0 ]; then
        echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
        echo "  Rollback procedures are working correctly."
    else
        echo -e "${RED}✗ SOME TESTS FAILED${NC}"
        echo "  Review the failed tests above and fix issues."
    fi
    
    echo ""
    
    # Save test report
    REPORT_PATH="$BACKUP_DIR/rollback_test_report.txt"
    {
        echo "ROLLBACK TEST REPORT"
        echo "Generated: $(date)"
        echo "========================================"
        echo ""
        echo "Test Configuration:"
        echo "- Backup Directory: $BACKUP_DIR"
        echo "- Application Directory: $APP_DIR"
        echo "- Skip Database: $SKIP_DATABASE"
        echo ""
        echo "Test Results:"
        echo "- Total Tests: $TOTAL_TESTS"
        echo "- Passed: $PASSED_TESTS"
        echo "- Failed: $FAILED_TESTS"
        echo "- Pass Rate: $PASS_RATE%"
    } > "$REPORT_PATH"
    
    echo "Test report saved: $REPORT_PATH"
    
    return $([ "$FAILED_TESTS" -eq 0 ] && echo 0 || echo 1)
}

# Main execution
print_header

# Run test suite
test_prerequisites
test_backup_creation
test_marker_modification
test_rollback_execution
test_rollback_verification
test_backup_integrity
test_cleanup

# Show results
show_test_results
exit $?













