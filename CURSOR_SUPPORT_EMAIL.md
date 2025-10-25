Subject: Critical Issue: Complete Chat History Missing from UI (Data Present in Database)

---

Dear Cursor Support Team,

I'm writing to report a critical issue where my entire chat history has disappeared from the Cursor interface, despite all data being intact in the underlying database.

**Issue Details:**

- **Version**: Cursor 1.7.54
- **OS**: macOS 26.0.1 (Build 25A362)
- **Date Occurred**: October 24, 2025
- **Severity**: High - Unable to access any historical conversations

**Problem:**
As of today, the Cursor UI only shows the current active conversation. All previous chat history (226 conversations spanning months of work) is no longer visible or accessible through the interface.

**Technical Verification:**
I have verified that this is NOT a data loss issue - the data is completely intact:

```
Database Location: ~/Library/Application Support/Cursor/User/globalStorage/state.vscdb
Database Size: 3.4 GB
Conversations in Database: 226 (verified via direct SQLite query)
Total Records: 119,653 entries in cursorDiskKV table
All conversations stored with 'bubbleId:' key prefix
```

Both the main database and the backup file (state.vscdb.backup) contain identical, complete data. The SQLite database structure is valid and uncorrupted.

**What I've Tried:**
- Completely restarted Cursor multiple times
- Verified database integrity using sqlite3 CLI
- Searched for chat history panel/view in the UI
- Checked both current and backup databases

**Root Cause:**
This appears to be a UI rendering issue where Cursor is not properly reading or displaying conversations from the database, rather than actual data loss. The disconnect is between the data layer (which is intact) and the presentation layer (which shows nothing).

**Impact:**
This is severely impacting my productivity as I regularly reference previous conversations for:
- Project context and decisions made
- Code patterns and solutions
- Complex troubleshooting histories
- Development workflow continuity

**Request:**
Could you please:
1. Confirm if this is a known issue in version 1.7.54
2. Provide a fix or workaround to restore UI access to chat history
3. If necessary, provide instructions for database repair/re-indexing
4. Suggest if I should rollback to a previous version

I'm available for any debugging steps or log files you might need to diagnose this issue.

Thank you for your prompt attention to this matter.

---

**System Information:**
- Cursor Version: 1.7.54
- macOS Version: 26.0.1 (Build 25A362)
- Database Path: ~/Library/Application Support/Cursor/User/globalStorage/state.vscdb
- Database Size: 3.4 GB
- Conversations Count: 226
- Issue First Noticed: October 24, 2025, 08:45 AM

Best regards,
[Your Name]

