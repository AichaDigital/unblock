# Chat History Not Visible in UI Despite Data Being Present in Database

## Issue Summary
All chat history has disappeared from the Cursor UI, but the underlying SQLite database contains all 226 conversations with complete data.

## Environment
- **Cursor Version**: 1.7.54
- **OS**: macOS 26.0.1 (Build 25A362)
- **Database Size**: 3.4 GB
- **Number of Conversations in DB**: 226 (verified via SQLite query)

## Problem Description
After opening Cursor today, the entire chat history is no longer visible in the interface. Only the current active conversation is shown. However, when directly querying the database, all historical conversations are intact.

## Technical Investigation Performed

### Database Verification
```bash
# Location of database
~/Library/Application Support/Cursor/User/globalStorage/state.vscdb

# Verified tables exist
sqlite3 state.vscdb "SELECT name FROM sqlite_master WHERE type='table';"
# Returns: ItemTable, cursorDiskKV

# Verified conversation count
sqlite3 state.vscdb "SELECT COUNT(DISTINCT SUBSTR(key, 10, 36)) FROM cursorDiskKV WHERE key LIKE 'bubbleId:%';"
# Returns: 226 conversations
```

### Key Findings
1. **Data is NOT lost** - All 226 conversations exist in `cursorDiskKV` table with `bubbleId:` prefix
2. **Total records**: 119,653 entries in the cursorDiskKV table
3. **Backup file** also contains identical data (created at 08:45 today)
4. **Database integrity** appears intact (SQLite 3.x database, valid structure)

## What I've Tried
- ✅ Restarted Cursor completely
- ✅ Verified database is not corrupted
- ✅ Checked both current and backup databases - both contain all data
- ✅ Searched for "Chat History" panel/view in UI - not found or not working

## Expected Behavior
All 226 historical conversations should be visible and accessible through the Cursor chat history interface.

## Actual Behavior
Only the current active conversation is visible. No access to historical chats through the UI, despite all data being present in the database.

## Question
Is this a known UI issue in version 1.7.54? Is there a specific panel or command to view chat history that might have changed recently? 

The data is clearly there - it seems to be purely a UI/display issue where Cursor is not reading or showing the conversations from the database.

## Workaround Needed
If this cannot be fixed immediately, is there a way to export/view conversations directly, or roll back to a version where the history panel worked correctly?

---

Any help would be greatly appreciated. Having access to previous conversations is critical for my development workflow.

