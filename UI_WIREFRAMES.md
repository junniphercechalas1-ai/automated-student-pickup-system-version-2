# Automated Student Pickup Verification System

## Overview
This document describes the complete page-by-page wireframe specification for the system titled:

**AUTOMATED STUDENT PICKUP VERIFICATION SYSTEM FOR PARENTS AND GUARDIANS WITH QR CODE AND SMS NOTIFICATIONS FOR ORION CHRISTIAN ACADEMY OF THE PHILIPPINES**

It follows the required process, database entities, and scope exactly.

---

# 1. Parent/Guardian Login

## Purpose
Login screen for registered parents/guardians to access their account and QR code.

## Layout
- centered white card on a light blue background
- page title: `Parent / Guardian Login`
- subtitle: `Please sign in to access your assigned pickup QR code.`
- fields:
  - `Username`
  - `Password` (masked)
- primary button: `LOGIN`
- secondary links below:
  - `Forgot Password?`
  - `Need help? Contact school admin`
- footer note:
  - `Your QR code is only available through your authenticated account.`

## UI behavior
- show error if login fails
- on success redirect to `Parent/Guardian Dashboard`

---

# 2. Parent/Guardian Dashboard

## Purpose
Landing page after parent login, summarizing student, QR code status, and reminders.

## Layout
### Sidebar navigation
- Dashboard
- My Student
- My QR Code
- Pickup History
- Notifications
- Profile
- Logout

### Top header
- greeting: `Welcome, Maria Dela Cruz`
- subtitle: `Your authorized pickup information is ready.`

### Main dashboard cards
1. **My Student**
   - name: `Juan Dela Cruz`
   - `Grade 3 – Excellence`
   - `Student ID: STU-001`
   - button: `View Student`
2. **My QR Code**
   - QR icon preview
   - `Status: ACTIVE`
   - button: `View QR Code`
3. **Dismissal Reminder**
   - `Today's Dismissal`
   - `3:30 PM`
   - message: `SMS reminder will be sent to your registered mobile number.`

### Secondary content
- quick summary list:
  - `Registered contact: 09XXXXXXXXX`
  - `Relationship: Mother`
  - `Last pickup: July 10, 2026` (optional)

---

# 3. My Student

## Purpose
View-only parent page with student information and relationship details.

## Layout
- page title: `My Student`
- card with details:
  - `Student ID: STU-001`
  - `Name: Juan Dela Cruz`
  - `Grade Level: Grade 3`
  - `Section: Excellence`
  - `Your Relationship: Mother`
- note block: `This page is view-only for security.`

---

# 4. My QR Code

## Purpose
Display the parent/guardian QR code securely in the authenticated account.

## Layout
- page title: `MY PICKUP QR CODE`
- large centered QR code panel
- details below the QR code:
  - `Parent/Guardian: Maria Dela Cruz`
  - `Relationship: Mother`
  - `Authorized Student: Juan Dela Cruz`
  - `Grade/Section: Grade 3 – Excellence`
  - `QR Status: ACTIVE`
- instruction block:
  - `Present this QR code directly to the authorized school personnel at the school gate during student pickup.`
- security message block:
  - `For security purposes, this QR code is available only through your authenticated account.`
- no download, no print, no share controls

---

# 5. SMS Reminder Example

## Purpose
Show how dismissal reminders will be presented to the parent/guardian.

## Layout
- page title: `SMS Reminder Example`
- phone-style preview card
- message content:
  - `Orion Christian Academy: This is a reminder that it is time to pick up Juan Dela Cruz. Please proceed to the school and present your assigned QR code for verification.`
- metadata below:
  - `To: 09XXXXXXXXX`
  - `Scheduled time: 3:15 PM`
- note:
  - `This SMS is only a reminder. QR verification is still required.`

---

# 6. School Staff/Admin Login

## Purpose
Login screen for authorized staff who manage students, guardians, QR codes, and pickup verification.

## Layout
- centered card with dark navy header
- title: `School Staff Login`
- subtitle: `Authorized personnel only.`
- fields:
  - `Username`
  - `Password` (masked)
- button: `LOGIN`
- note: `Only authorized school personnel may access admin and QR scanner functions.`

---

# 7. Admin Dashboard

## Purpose
Main admin landing page with summary metrics and recent pickup activity.

## Layout
### Sidebar navigation
- Dashboard
- Students
- Parents/Guardians
- QR Codes
- Pickup Verification
- Pickup Records
- SMS Notifications
- Logout

### Header
- title: `Dashboard`
- welcome text: `Good afternoon, Admin` or `Welcome back, School Staff`

### Summary cards
- `Total Students`
- `Total Registered Parents/Guardians`
- `Today's Verified Pickups`
- `Pending / Not Yet Picked Up`

### Recent Pickup Records table
- columns:
  - `Pickup ID`
  - `Student`
  - `Parent/Guardian`
  - `Relationship`
  - `Date`
  - `Time`
  - `Verification Status`
- single row example:
  - `PU-0001`, `Juan Dela Cruz`, `Maria Dela Cruz`, `Mother`, `July 10, 2026`, `3:31 PM`, `Verified`

---

# 8. Student Management

## Purpose
Admin page to view all students and quickly add or edit student records.

## Layout
- page title: `Students`
- toolbar:
  - search box: `Search student...`
  - button: `+ ADD STUDENT`
- table columns:
  - `Student ID`
  - `Student Name`
  - `Grade Level`
  - `Section`
  - `Authorized Parent/Guardian`
  - `Actions`
- action buttons: `View`, `Edit`
- table example row:
  - `STU-001`, `Juan Dela Cruz`, `Grade 3`, `Excellence`, `Maria Dela Cruz`, `View | Edit`

---

# 9. Add/Edit Student

## Purpose
Form for admin to create or modify a student record.

## Layout
- page title: `Add Student` or `Edit Student`
- form fields:
  - `Student ID`
  - `First Name`
  - `Last Name`
  - `Grade Level`
  - `Section`
- buttons:
  - primary: `Save Student`
  - secondary: `Cancel`
- success message: `Student successfully registered.`

---

# 10. Parent/Guardian Management

## Purpose
Admin page to view and manage authorized parent/guardian records.

## Layout
- page title: `Parents / Guardians`
- toolbar:
  - search box: `Search guardian...`
  - button: `+ ADD PARENT/GUARDIAN`
- table columns:
  - `Guardian ID`
  - `Full Name`
  - `Relationship`
  - `Student`
  - `Contact Number`
  - `QR Status`
  - `Actions`
- action buttons: `View`, `Edit`
- example row:
  - `PG-001`, `Maria Dela Cruz`, `Mother`, `Juan Dela Cruz`, `09XXXXXXXXX`, `ACTIVE`, `View | Edit`

---

# 11. Add/Edit Parent/Guardian

## Purpose
Form to register or update an authorized parent/guardian and connect them to a student.

## Layout
- page title: `Add Parent/Guardian` or `Edit Parent/Guardian`
- form fields:
  - `Guardian ID`
  - `Student` select dropdown: `STU-001 — Juan Dela Cruz`
  - `Full Name`
  - `Relationship` select: `Mother`, `Father`, `Guardian`
  - `Contact Number`
  - `Username`
  - `Password` (masked)
- buttons:
  - `Save Parent/Guardian`
  - `Cancel`
- success message:
  - `Parent/Guardian successfully registered and connected to Juan Dela Cruz.`

---

# 12. QR Code Management

## Purpose
Admin page to view assigned QR codes and their status.

## Layout
- page title: `QR Codes`
- toolbar:
  - search box: `Search QR code...`
- table columns:
  - `QR ID`
  - `Parent/Guardian`
  - `Student`
  - `QR Status`
  - `Actions`
- action button: `View`
- badges: `ACTIVE` (green), `INACTIVE` (red)
- note: do not invent extra QR management actions beyond status if not implemented.

---

# 13. QR Scanner / Pickup Verification

## Purpose
Dedicated interface for school personnel to scan pickup QR codes.

## Layout
- page title: `STUDENT PICKUP VERIFICATION`
- subtitle: `Scan Parent/Guardian QR Code`
- scanner area:
  - large rectangular scanning frame
  - camera icon and instruction: `Position the parent/guardian QR code inside the scanning frame.`
- below scanner:
  - status text: `Waiting for QR scan...`
  - small list of last scanned attempts or area for messages

## Behavior
- after scan, show record lookup result or failure
- use `qr_value` to identify QR_CODE record
- check QR status, guardian, student connection
- if valid show verification pane
- if invalid show failed screen

---

# 14. Successful Verification

## Purpose
Show verification result and allow staff to confirm pickup.

## Layout
- large green header: `VERIFIED`
- subtext: `Registered and authorized parent/guardian.`
- three detail cards:
  1. **Student Information**
     - `Student ID: STU-001`
     - `Student: Juan Dela Cruz`
     - `Grade/Section: Grade 3 – Excellence`
  2. **Authorized Pickup Person**
     - `Guardian ID: PG-001`
     - `Name: Maria Dela Cruz`
     - `Relationship: Mother`
     - `Contact Number: 09XXXXXXXXX`
  3. **QR Information**
     - `QR ID: QR-001`
     - `Status: ACTIVE`
- primary button: `CONFIRM STUDENT PICKUP`
- secondary button: `Scan Again`

---

# 15. Failed Verification

## Purpose
Show clear failure when QR verification does not succeed.

## Layout
- large red header: `NOT AUTHORIZED`
- message: `This QR code could not be verified or is not authorized for student pickup.`
- optional reason block:
  - `Invalid QR` or `QR status inactive` or `Guardian not found`
- buttons:
  - `SCAN AGAIN`
  - `CANCEL`
- no `Confirm Student Pickup` button displayed

---

# 16. Confirm Pickup

## Purpose
Final confirmation screen after staff approves a verified pickup.

## Layout
- title: `Pickup Confirmed`
- success panel with green check icon
- details:
  - `Pickup ID: PU-0001`
  - `Student: Juan Dela Cruz`
  - `Picked Up By: Maria Dela Cruz`
  - `Relationship: Mother`
  - `Date: July 10, 2026`
  - `Time: 3:31 PM`
  - `Status: VERIFIED`
- message: `Student pickup successfully recorded.`
- button: `Return to Scanner`

---

# 17. Pickup Records

## Purpose
Admin page to view all pickup history and filter by criteria.

## Layout
- page title: `Pickup Records`
- filters:
  - search box: `Search pickup...`
  - date filter
  - student filter
  - status filter
- table columns:
  - `Pickup ID`
  - `Student`
  - `Parent/Guardian`
  - `Relationship`
  - `QR ID`
  - `Pickup Date`
  - `Pickup Time`
  - `Verification Status`
- status badges: `VERIFIED` green, `NOT AUTHORIZED` red, `INVALID QR` red
- example row: `PU-0001`, `Juan Dela Cruz`, `Maria Dela Cruz`, `Mother`, `QR-001`, `July 10, 2026`, `3:31 PM`, `Verified`

---

# 18. Pickup Record Details

## Purpose
Detail view for a single pickup transaction.

## Layout
- page title: `Pickup Record Details`
- detail card with fields:
  - `Pickup ID: PU-0001`
  - `Student ID: STU-001`
  - `Student: Juan Dela Cruz`
  - `Guardian ID: PG-001`
  - `Guardian: Maria Dela Cruz`
  - `Relationship: Mother`
  - `Contact Number: 09XXXXXXXXX`
  - `QR ID: QR-001`
  - `Pickup Date: July 10, 2026`
  - `Pickup Time: 3:31 PM`
  - `Verification Status: VERIFIED`
- action: `Back to Pickup Records`

---

# 19. SMS Notification Records

## Purpose
Admin page to track SMS reminders and delivery status.

## Layout
- page title: `SMS Notification Records`
- filters:
  - search by parent or student
  - status filter
  - date filter
- table columns:
  - `Parent/Guardian`
  - `Student`
  - `Contact Number`
  - `Message`
  - `Scheduled Time`
  - `Sent Time`
  - `Delivery Status`
- statuses: `Sent`, `Delivered`, `Failed`, `Pending`
- example row:
  - `Maria Dela Cruz`, `Juan Dela Cruz`, `09XXXXXXXXX`, `Reminder: Please present QR at pickup.`, `03:15 PM`, `03:15 PM`, `Delivered`

---

# 20. Design Guidelines

## UI style rules
- white / light background with dark navy blue primary color
- light blue accent panels and buttons
- green for verified success states
- red for failed verification states
- consistent rounded cards and buttons
- minimal but professional shadows
- clear typography and spacing
- responsive layout with sidebar collapsing on smaller screens

## Behavior rules
- parent/guardian must log in before viewing QR code
- QR code visible only inside authenticated account
- no QR download/print/share controls
- verification process must show student and guardian details
- no pickup confirmation on failed validation
- admin features only visible to staff users
- masked password fields in forms

## Process alignment
The UI must support the exact workflow:
1. Admin registers student
2. Admin registers parent/guardian and links to student
3. System assigns QR code to parent/guardian
4. Parent/guardian logs in
5. Parent/guardian accesses QR code
6. SMS reminder is sent at dismissal
7. Parent/guardian arrives at school
8. Authorized staff scans QR
9. System verifies QR, guardian, and student
10. If verified, confirm pickup
11. Create pickup record
12. If not verified, show not authorized

---

# 21. Page Map

## Parent/Guardian pages
- Parent/Guardian Login
- Parent/Guardian Dashboard
- My Student
- My QR Code
- SMS Reminder Example

## Admin pages
- School Staff/Admin Login
- Admin Dashboard
- Student Management
- Add/Edit Student
- Parent/Guardian Management
- Add/Edit Parent/Guardian
- QR Code Management
- QR Scanner / Pickup Verification
- Successful Verification
- Failed Verification
- Confirm Pickup
- Pickup Records
- Pickup Record Details
- SMS Notification Records

---

# 22. Notes for implementation

- Use the relationships between entities directly in UI flows.
- Keep each form field aligned to actual database columns.
- Use clear success and error messages for every action.
- Keep the parent interface simple and focused on student and QR details.
- Keep the admin interface data-rich and management-oriented.
- Build the QR verification screen around the scanner result first, then allow confirmation.
- Honor the instruction not to add unrelated modules.
