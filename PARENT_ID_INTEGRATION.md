# Staff QR Scanner - Parent/Guardian Integration Fix

## Summary of Changes

The Staff QR Scanner now includes Parent/Guardian information in scan/pickup records.

### Changes Made

#### 1. **QR Code Format Updated** 
**Files Modified:**
- `resources/views/parent/qr-code.blade.php`
- `resources/views/parent/dashboard.blade.php`

**What Changed:**
- QR code now encodes a JSON object instead of plain text
- Old format: `"2024-1000"` (just student qr_code)
- New format: `{"parent_id": 5, "student_id": 3, "student_qr_code": "2024-1000"}`

**Example QR Payload:**
```json
{
  "parent_id": 42,
  "student_id": 15,
  "student_qr_code": "STU-2024-001"
}
```

**Why:** When staff scans the QR code, we now have the parent's ID, enabling us to link the scan record to the correct parent/guardian.

#### 2. **Scanner JavaScript Updated**
**File Modified:**
- `resources/js/staff-pickup.js`

**What Changed:**
- Scanner now parses QR payload as JSON (with fallback for plain text for backward compatibility)
- Extracts `parent_id` and `student_id` from QR
- Sends `parent_id` to backend when creating pickup record
- Added comprehensive logging for parent_id handling

**Example Flow:**
```
1. Staff scans QR → gets JSON payload
2. Parser extracts: parent_id = 42, student_qr_code = "STU-2024-001"
3. Resolves student from student_qr_code
4. Calls POST /supabase/pickups with both student_id and parent_id
5. Backend stores both in pickups table
```

#### 3. **Backend Endpoint Updated**
**File Modified:**
- `routes/web.php` → POST `/supabase/pickups`

**What Changed:**
- Endpoint now accepts optional `parent_id` parameter
- When parent_id is provided, it's included in the pickup record payload
- Backward compatible - still works if parent_id is not provided

**Request Body (New):**
```json
{
  "student_id": 15,
  "parent_id": 42,
  "picked_at": "2026-08-18T14:30:00Z"
}
```

---

## Required Supabase Schema Change

The `pickups` table in Supabase needs a new `parent_id` column to store the parent/guardian reference.

### Step 1: Add Column to Pickups Table

Open your Supabase project → SQL Editor → Run this command:

```sql
ALTER TABLE public.pickups 
ADD COLUMN parent_id bigint REFERENCES public.parents(id) ON DELETE SET NULL;

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_pickups_parent_id ON public.pickups (parent_id);
```

### Step 2: Verify the Column

Check that the column was added successfully:

```sql
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'pickups' 
ORDER BY ordinal_position;
```

Expected output:
```
id              | bigint     | NO
student_id      | bigint     | NO
picked_at       | timestamp  | NO
created_at      | timestamp  | NO
parent_id       | bigint     | YES  ← NEW COLUMN
```

---

## Schema Overview (Updated)

### pickups table
```sql
CREATE TABLE public.pickups (
  id              bigint PRIMARY KEY,
  student_id      bigint NOT NULL (references students.id),
  parent_id       bigint NULL (references parents.id),  ← NEW
  picked_at       timestamptz NOT NULL,
  created_at      timestamptz DEFAULT now()
);
```

### Relationship Flow
```
Parent (ID 42)
    ↓
student_parent_link (parent_id=42, student_id=15)
    ↓
Student (ID 15, qr_code="STU-2024-001")
    ↓
Pickup/Scan Record (student_id=15, parent_id=42, picked_at=now())
```

---

## Testing Checklist

### Before Testing
- [ ] Run the SQL ALTER TABLE command in Supabase SQL Editor
- [ ] Verify parent_id column was added to pickups table
- [ ] Rebuild frontend: `npm run build`
- [ ] Clear browser cache or hard refresh (Ctrl+F5)

### Testing Steps

1. **Parent logs in and views QR code**
   - Navigate to parent dashboard or QR code page
   - Observe that the QR code still displays (looks the same)
   - QR data is now JSON with parent_id

2. **Staff member scans the QR code**
   - Open staff pickup verification page
   - Click "Start scanner"
   - Point camera at parent's QR code
   - Check browser console (F12 → Console tab) for logs:
     ```
     ✅ onScanSuccess() - QR detected: {"parent_id": 42, ...}
     📋 QR payload parsed as JSON: Object
     ✓ Parent ID extracted from QR: 42
     📚 Student resolved: {id: 15, name: "...", ...}
     📤 Sending pickup record to backend
     ✓ Parent ID included in pickup payload
     📡 markPickup response status: 201
     📦 markPickup payload: {id: 999, student_id: 15, parent_id: 42, ...}
     ✓ setStatus: Student pickup marked.
     ```

3. **Verify in Supabase**
   - Go to Supabase → pickups table
   - Find the most recent record
   - Verify it has:
     - `student_id` ✓
     - `parent_id` ✓ (now populated!)
     - `picked_at` ✓

---

## Test Report Format

After one successful scan, provide:

```
QR payload type: JSON object
Parent/Guardian resolved: YES/NO
Parent/Guardian ID used: [ID number]
Student resolved: YES/NO
Student ID used: [ID number]
Staff/verifier resolved: [info if applicable]
Record created: YES/NO
Record location: public.pickups table
Record columns populated:
  - student_id: [value]
  - parent_id: [value]
  - picked_at: [timestamp]
  - created_at: [timestamp]
```

---

## Backward Compatibility

The changes are backward compatible:

1. **QR Code Format:**
   - New: JSON object with parent_id
   - Old: Plain text (student qr_code only)
   - Scanner handles both formats automatically

2. **API Endpoint:**
   - New: Accepts optional `parent_id` parameter
   - Old: Still works with just `student_id`
   - If parent_id not provided, column remains NULL

3. **Database:**
   - `parent_id` column is nullable
   - Existing records without parent_id continue to work

---

## Files Modified Summary

| File | Change | Reason |
|------|--------|--------|
| `resources/views/parent/qr-code.blade.php` | QR encodes JSON with parent_id | Include parent info in QR |
| `resources/views/parent/dashboard.blade.php` | Same QR format change | Consistent across views |
| `resources/js/staff-pickup.js` | Parse JSON QR, extract parent_id, pass to backend | Send parent info with scan |
| `routes/web.php` POST `/supabase/pickups` | Accept parent_id parameter | Store parent in pickup record |
| `database/migrations/2026_08_18_000000_add_parent_id_to_pickups.php` | Documents schema change | Reference for database structure |

---

## Troubleshooting

### Issue: QR code not scanning
- **Check:** Browser console for errors (F12 → Console)
- **Solution:** Clear cache, rebuild assets (`npm run build`), hard refresh

### Issue: Parent ID not appearing in database
- **Check:** Did you run the ALTER TABLE command in Supabase SQL Editor?
- **Solution:** Run the SQL command from `database/add_parent_id_to_pickups.sql`

### Issue: Parent ID shows as 0 or null
- **Check:** QR code is being generated correctly with parent ID
- **Solution:** Check browser console logs for "Parent ID extracted from QR"

### Issue: Old QR codes (plain text) not working
- **Check:** Scanner should still handle old format (backward compatible)
- **Solution:** If not working, regenerate new QR codes on parent dashboard

---

## Next Steps

1. **Add parent_id column to Supabase pickups table** (required)
2. **Test one real scan** and verify parent_id is saved
3. **Document pickup verification flow** for staff training
4. **(Optional)** Add staff verifier tracking
5. **(Optional)** Remove debug logging for production

---

**Last Updated:** 2026-08-18  
**Status:** Ready for Testing
