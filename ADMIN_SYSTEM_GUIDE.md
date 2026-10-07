# Admin System Implementation Guide

## Overview
Your admin system provides a complete dashboard for managing students, parents, and staff with role-based access control. All data is stored and retrieved from Supabase.

## Architecture

### Components Created

1. **Admin Model** (`app/Models/Admin.php`)
   - Handles admin user authentication
   - Password hashing and verification
   - Role-based permissions (admin, super_admin)

2. **Admin Controller** (`app/Http/Controllers/AdminController.php`)
   - Manages all admin operations
   - Handles login/logout
   - CRUD operations for students, parents, staff
   - Report generation

3. **Admin Middleware** (`app/Http/Middleware/EnsureAdminIsLoggedIn.php`)
   - Protects admin routes
   - Redirects unauthorized access to login

4. **Admin Routes** (in `routes/web.php`)
   - Public: `/admin/login` (GET/POST)
   - Protected: `/admin/dashboard`, `/admin/students`, `/admin/parents`, `/admin/staff`, `/admin/reports/pickups`

5. **Admin Views** (in `resources/views/admin/`)
   - `login.blade.php` - Login page
   - `dashboard.blade.php` - Main admin dashboard with statistics
   - `students.blade.php` - Student management interface
   - `parents.blade.php` - Parent management interface
   - `staff.blade.php` - Staff management interface
   - `reports.blade.php` - Pickup statistics and reports

### Database Schema

A new `admins` table has been added to your Supabase database:
```sql
CREATE TABLE public.admins (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  full_name TEXT,
  role TEXT DEFAULT 'admin', -- admin, super_admin
  is_active BOOLEAN DEFAULT true,
  last_login TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

Enhanced existing tables with role and status fields:
- `students`: Added `is_active` boolean and `parent_ids` array
- `parents`: Added `is_active` boolean and `student_id` foreign key
- `staff`: Added `role` text field and `is_active` boolean

## Getting Started

### 1. Create Your First Admin Account

Run this SQL in your Supabase dashboard:

```sql
INSERT INTO public.admins (username, email, password_hash, full_name, role, is_active)
VALUES (
  'admin',
  'admin@orionacademy.edu',
  crypt('your_secure_password', gen_salt('bf')),
  'Administrator',
  'super_admin',
  true
);
```

**Note:** Replace `your_secure_password` with a secure password. The system uses Laravel's Hash for password storage.

Alternatively, use Laravel to hash the password:
```bash
php artisan tinker
```

Then in tinker:
```php
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

Admin::create([
    'username' => 'admin',
    'email' => 'admin@orionacademy.edu',
    'password_hash' => Hash::make('your_secure_password'),
    'full_name' => 'Administrator',
    'role' => 'super_admin',
    'is_active' => true,
]);
```

### 2. Access the Admin Portal

1. Navigate to `http://localhost:8000/admin/login`
2. Enter your username and password
3. Click "Login to Admin Panel"
4. You'll be redirected to the admin dashboard

### Phone-only admin sign-in and password recovery

The admin portal signs in with a mobile number and password. Password recovery sends a one-time code using the configured SMS module.

The current Laravel admin endpoints read the singular `public.admin` table. Run this once in Supabase SQL Editor to add the login phone column and prevent duplicate numbers:

```sql
alter table public.admin add column if not exists phone_number text;
create unique index if not exists admin_phone_number_unique
  on public.admin (phone_number)
  where phone_number is not null;
```

Then set the phone for the existing administrator row, replacing the email and number below with your own values. Store the number in international format (for example `+639171234567`):

```sql
update public.admin
set phone_number = '+639171234567'
where lower(email) = lower('admin@example.com');
```

Do not remove the legacy email column yet; it may still be used internally to verify existing Supabase Auth credentials during transition. It is no longer requested by the admin login or password-reset screens.

## Features

### Dashboard
- **Statistics Overview**: Display total counts of students, parents, and staff
- **Quick Actions**: Shortcuts to manage different entities
- **Role Information**: Shows your admin role and last login

### Student Management
- ✅ View all students with pagination
- ✅ Add new students (name, class, QR code)
- ✅ Edit student information
- ✅ Delete students
- ✅ Toggle student active/inactive status
- ✅ Link students to parents

### Parent Management
- ✅ View all parents/guardians
- ✅ Add new parent records (name, phone, relationship)
- ✅ Edit parent information
- ✅ Delete parent records
- ✅ Manage parent-student relationships
- ✅ Toggle parent active/inactive status

### Staff Management
- ✅ View all staff members
- ✅ Approve/disapprove staff registrations
- ✅ Assign roles (staff, supervisor)
- ✅ Enable/disable staff accounts
- ✅ View staff contact information

### Approval-gated account creation
- New parent and staff registrations are saved as encrypted pending requests after the phone OTP is verified.
- A Supabase Auth user and the matching parent/staff profile are created only after an administrator approves the request.
- Declining a staged request removes it without creating an Auth user. Declining an existing legacy account permanently deletes its Supabase Auth user and linked profile.
- Parent and staff receive an SMS after an approval or decline. If SMS delivery fails, the account decision remains saved, the admin is warned, and the failure is logged.
- Pending passwords are encrypted with Laravel `APP_KEY` in the application's database. Keep the same `APP_KEY` available when requests are approved; losing or changing it makes pending passwords unreadable.

Before deploying this change, run `php artisan migrate --force` against the same persistent Laravel database used by the web application. The new `pending_registrations` table is required for signup and approval.

Registrations made before this change may already have a Supabase Auth user while awaiting approval. They are intentionally left untouched so their existing credentials and profiles are not deleted; manage those legacy pending accounts in the existing admin list.

### Reports & Analytics
- ✅ View pickup statistics
- ✅ Group pickups by date
- ✅ See which student was picked up at what time
- ✅ View total pickup count

## API Endpoints

### Authentication
- `GET /admin/login` - Show login form
- `POST /admin/login` - Process login

### Admin Dashboard
- `GET /admin/dashboard` - Show admin dashboard

### Students (Protected)
- `GET /admin/students` - Get all students (JSON)
- `POST /admin/students` - Create new student
- `PATCH /admin/students/{id}` - Update student
- `DELETE /admin/students/{id}` - Delete student

### Parents (Protected)
- `GET /admin/parents` - Get all parents (JSON)
- `POST /admin/parents` - Create new parent
- `PATCH /admin/parents/{id}` - Update parent
- `DELETE /admin/parents/{id}` - Delete parent

### Staff (Protected)
- `GET /admin/staff` - Get all staff (JSON)
- `PATCH /admin/staff/{id}` - Update staff (approve, role, status)

### Reports (Protected)
- `GET /admin/reports/pickups` - Get pickup statistics (JSON)

### Session Management
- `POST /admin/logout` - Logout and clear session

## Parent and Staff Username Sign-in

Parent and staff choose a username during registration and use it with their password to sign in. Supabase Auth still verifies passwords; the app maps each username to a non-deliverable internal email alias (`<username>@accounts.orion.invalid`) because Supabase Phone sign-in is disabled. The alias is not a contact email and receives no messages. Keep Supabase Email sign-in enabled; Phone sign-in and Supabase/Twilio SMS are not needed for login.

Registration still verifies ownership of the mobile number using the app's configured SMS module, then holds the request until an administrator approves it. On approval, the app creates the Supabase Auth user with the internal username alias and verified phone. Password recovery continues to use the configured SMS module. Parent contact email is optional and remains profile-only. Staff records must allow a null email column; run this once in the Supabase SQL Editor on existing projects:

```sql
alter table public.staff alter column email drop not null;
```

New registrations require usernames. Older approved accounts can use **Already registered? Set up your username** on the login page: they verify their registered mobile number using the app's SMS module, choose a username, and keep their existing password and Auth account.

Existing accounts linked to parent or staff profiles can have their phone number populated and marked confirmed with:

```powershell
php artisan supabase:confirm-legacy-phones
php artisan supabase:confirm-legacy-phones --apply
```

The first command is a dry run; the second applies the confirmation. It keeps legacy email identities intact. These commands require `VITE_SUPABASE_URL` and `SUPABASE_SERVICE_KEY` (or `SUPABASE_SERVICE_ROLE_KEY`) in the server environment. The Supabase Send SMS hook is not used for this flow.

## Security Considerations

1. **Authentication**: Session-based using Laravel's session middleware
2. **Authorization**: Protected routes require valid admin session
3. **Password Security**: Passwords are hashed using Laravel's Hash facade (bcrypt)
4. **CSRF Protection**: All POST/PATCH/DELETE requests require CSRF tokens
5. **Supabase RLS**: Configured policies for admin table access
6. **Role-Based Access**: Different permission levels (admin vs super_admin)

## Extending the System

### Add New Admin Users
Use the same method as creating your first admin account.

### Customize Admin Roles
Edit the `Admin` model to add new role types:
```php
public function hasPermission($permission)
{
    // Implement permission logic
}
```

### Add More Analytics
Create new methods in `AdminController`:
```php
public function getAnalytics()
{
    // Custom analytics logic
}
```

## Troubleshooting

### Admin Login Not Working
1. Verify the admin record exists in the `admins` table
2. Check that `is_active` is set to `true`
3. Ensure password hash was created correctly
4. Check Laravel error logs: `storage/logs/laravel.log`

### CRUD Operations Failing
1. Verify Supabase URL and service key are set in `.env`
2. Check Supabase RLS policies allow service role access
3. Ensure field names match database schema
4. Check browser console for AJAX errors

### Styling Issues
- Make sure Tailwind CSS is compiled: `npm run dev`
- Verify Vite is running
- Check for CSS conflicts with existing styles

## Next Steps

1. **Create multiple admin accounts** for different staff members
2. **Configure email notifications** when new accounts are created
3. **Add audit logging** to track admin actions
4. **Implement backup/export** functionality for reports
5. **Add custom filters** to student/parent/staff lists
6. **Create role templates** for different staff types

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check browser console for JavaScript errors
3. Review Supabase API responses in Network tab
4. Ensure all environment variables are set correctly
