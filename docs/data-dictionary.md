# Student Pickup System Data Dictionary

## Scope and source

This is a working data dictionary for the Supabase `public` schema, based on
the schema, constraints, index, and pickup-count query results supplied on
2026-10-07, together with a scan of this Laravel project. It describes the
schema snapshot, not a live connection to Supabase. Confirm the snapshot
against the current Supabase database before submitting this as final.

The dictionary includes the eight tables present in the supplied column
export: `admin`, `notifications`, `parents`, `parents_students`, `pickups`,
`sms_queue`, `staff`, and `students`.

For sizes, PostgreSQL `text` has no declared maximum length. `bigint` is a
signed 64-bit integer; `uuid` is a 128-bit identifier; `boolean` is a logical
true/false value; and `timestamp with time zone` stores a date and time with
time-zone semantics. Physical storage for variable-length values depends on
their contents.

In the tables below, `—` means the supplied schema export reported no default
or no constraint of that kind. The column export did not include PostgreSQL
identity metadata, so a blank default on an integer key does not establish
whether it is generated as an identity.

## Data source and application

- **Database:** Supabase-hosted PostgreSQL, `public` schema.
- **Application:** Laravel web application. It accesses Supabase tables
  through Supabase REST endpoints and uses Supabase Auth for account identity.
  Parent and staff sign in with usernames; the application maps each username
  to an internal email-format Auth identifier.
- **SMS:** When `SMS_PROVIDER=supabase_queue` is configured, Laravel writes
  SMS work to `sms_queue`; a separate `php artisan sms:work` process sends
  queued messages through the local GSM modem. `notifications` stores SMS
  test/reminder records.
- **Data warehouse / data lake:** None identified in the scanned project.
- **Owners and editors:** Not identifiable from the schema or source scan.
  Document the responsible capstone team members or roles here.
- **Social metadata:** No ratings, comments, tags, or other social metadata
  identified in these tables.

The Laravel application also has its own configured database and local
tables (for example, support messages and pending registrations). Those are
not part of this Supabase `public` schema dictionary.

## Supabase Auth identity (external schema)

`auth.users` is managed by Supabase and is not one of the eight application
tables in the `public` schema export. Include it in an ERD as an external
authentication entity because public profile tables reference its `id`.

For parent and staff username login, the application normalizes the username
and generates an Auth email in the form
`<normalized-username>@accounts.orion.invalid`. Supabase Auth then
authenticates using that email-format identifier and the user's password.
The application also stores `username` and `role` in Auth user metadata.
Consequently, `auth.users.email` is the Auth login identifier in this flow;
it should not be described as the user's real contact email. The username
and role are metadata, not columns in the `auth.users` table.

The `email` columns in `admin`, `parents`, and `staff` are separate fields
in the application schema. Do not assume they are the username or are
synchronized with the Auth identifier. Confirm how each is populated before
describing it as a verified contact address or removing it.

## Table: `admin`

Stores administrator profile data used by administrator account and
management flows.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique administrator profile identifier. | `uuid` (128-bit identifier) | No | `gen_random_uuid()` | Primary key |
| `first_name` | Administrator's first name. | `text` (unbounded) | Yes | — | — |
| `middle_name` | Administrator's middle name. | `text` (unbounded) | Yes | — | — |
| `last_name` | Administrator's last name. | `text` (unbounded) | Yes | — | — |
| `email` | Email value stored on the administrator profile; administrator authentication code may use this value. | `text` (unbounded) | No | — | Unique; also has a separate non-unique `idx_admins_email` index |
| `is_active` | Indicates whether the administrator profile is active. | `boolean` | Yes | `true` | — |
| `auth_user_id` | Links the profile to a Supabase Auth user. | `uuid` (128-bit identifier) | Yes | — | `idx_admins_auth_user_id` index; referenced Auth relation not included in the supplied constraint export |
| `phone_number` | Administrator phone number. | `text` (unbounded) | Yes | — | Partial unique index when not null: `admin_phone_number_unique` |

## Table: `notifications`

Stores notification/SMS test and reminder details recorded by the application.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique notification record identifier. | `uuid` (128-bit identifier) | No | `gen_random_uuid()` | Primary key |
| `auth_user_id` | Optional recipient/owner Supabase Auth user. | `uuid` (128-bit identifier) | Yes | — | Foreign key to `auth.users(id)`; delete sets this field to null |
| `student_id` | Optional student associated with the notification. | `bigint` (signed 64-bit integer) | Yes | — | Foreign key to `students(id)`; delete sets this field to null |
| `notification_type` | Category of notification. The application writes `sms_test` and `sms_reminder`. | `text` (unbounded) | No | — | — |
| `recipient_phone` | Phone number the notification is for, if applicable. | `text` (unbounded) | Yes | — | — |
| `subject` | Optional notification subject. | `text` (unbounded) | Yes | — | — |
| `message` | Notification message body. | `text` (unbounded) | No | — | — |
| `sent_at` | Time the notification was recorded as sent. | `timestamp with time zone` | Yes | — | — |
| `created_at` | Time the notification row was created. | `timestamp with time zone` | Yes | `timezone('utc'::text, now())` | — |

## Table: `parents`

Stores parent/guardian profile and contact details, including account
approval and active state. Parent-to-student associations are represented by
the `parents_students` junction table. The `student_id` column shown below also
exists in the supplied schema snapshot, but it is not the canonical
relationship for the many-to-many model.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique parent profile identifier. | `bigint` (signed 64-bit integer) | No | — | Primary key |
| `student_id` | Legacy/direct student reference retained in the supplied schema snapshot; the canonical parent-student associations belong in `parents_students`. | `bigint` (signed 64-bit integer) | Yes | — | Foreign key to `students(id)`; delete cascades to this parent row |
| `first_name` | Parent's first name. | `text` (unbounded) | Yes | — | — |
| `middle_name` | Parent's middle name. | `text` (unbounded) | Yes | — | — |
| `last_name` | Parent's last name. | `text` (unbounded) | Yes | — | — |
| `relationship` | Relationship label present on the parent profile; in the many-to-many model, the association-specific label is `parents_students.relationship`. | `text` (unbounded) | No | — | — |
| `phone_number` | Parent/guardian phone number used for contact and SMS. | `text` (unbounded) | No | — | — |
| `email` | Optional email value stored on the parent profile; distinct from the generated Supabase Auth identifier used for username login. | `text` (unbounded) | Yes | — | — |
| `created_at` | Time the parent profile was created. | `timestamp with time zone` | Yes | `timezone('utc'::text, now())` | — |
| `updated_at` | Time the parent profile was last updated. | `timestamp with time zone` | Yes | `timezone('utc'::text, now())` | — |
| `is_active` | Indicates whether the parent profile is active. | `boolean` | Yes | `true` | — |
| `auth_user_id` | Supabase Auth account linked to this parent profile. | `uuid` (128-bit identifier) | Yes | — | Foreign key to `auth.users(id)`; delete cascades to this parent row; partial unique index `idx_parents_auth_user_id` when not null |
| `is_approved` | Indicates whether an administrator approved the parent account. | `boolean` | Yes | `false` | Index `idx_parents_is_approved` |

## Table: `parents_students`

Junction table implementing the many-to-many relationship between parents and
students. One parent can be associated with multiple students, and one student
can be associated with multiple parents. The `relationship` value describes
the parent's relationship to the student for that association.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique parent-student association identifier. | `bigint` (signed 64-bit integer) | No | — | Primary key |
| `parent_id` | Parent in this association. | `bigint` (signed 64-bit integer) | No | — | Foreign key to `parents(id)`; delete cascades to this association |
| `student_id` | Student in this association. | `bigint` (signed 64-bit integer) | No | — | Foreign key to `students(id)`; delete cascades to this association |
| `relationship` | Optional relationship label for this parent-student association. | `text` (unbounded) | Yes | — | — |
| `created_at` | Time the association was created. | `timestamp with time zone` | No | `now()` | — |
| `updated_at` | Time the association was last updated. | `timestamp with time zone` | No | `now()` | — |

The pair `(parent_id, student_id)` has a composite unique constraint, so the
same parent-student association cannot be inserted twice.

## Table: `pickups`

Stores student pickup scan/confirmation events.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique pickup event identifier. | `bigint` (signed 64-bit integer) | No | — | Primary key |
| `student_id` | Student involved in the pickup. | `bigint` (signed 64-bit integer) | No | — | Foreign key to `students(id)`; deleting a student cascades to pickup records |
| `parent_id` | Parent/guardian associated with the pickup, when supplied by the scan. | `bigint` (signed 64-bit integer) | Yes | — | No foreign-key constraint was present in the supplied constraint results |
| `picked_at` | Date and time the pickup was recorded. | `timestamp with time zone` | No | — | — |
| `created_at` | Time the pickup row was created. | `timestamp with time zone` | Yes | `timezone('utc'::text, now())` | — |

## Table: `sms_queue`

Stores SMS messages waiting for, undergoing, or completing GSM-modem delivery.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique queued-message identifier. | `uuid` (128-bit identifier) | No | `gen_random_uuid()` | Primary key |
| `recipient_phone` | Destination phone number. | `text` (unbounded) | No | — | — |
| `message` | SMS body to send. | `text` (unbounded) | No | — | — |
| `status` | Queue state: `pending`, `processing`, `sent`, or `failed`. | `text` (unbounded) | No | `'pending'::text` | Check constraint restricts values to the four listed states |
| `provider_id` | Optional message/provider identifier returned after sending. | `text` (unbounded) | Yes | — | — |
| `error_message` | Optional error detail when sending fails. | `text` (unbounded) | Yes | — | — |
| `expires_at` | Optional deadline after which a queued message must not be sent. | `timestamp with time zone` | Yes | — | — |
| `created_at` | Time the message entered the queue; the worker orders pending rows by this field. | `timestamp with time zone` | No | `now()` | Partial index `sms_queue_pending_created_at_idx` for rows with `status = 'pending'` |
| `claimed_at` | Time a worker claimed the message for processing. | `timestamp with time zone` | Yes | — | — |
| `completed_at` | Time the worker recorded a sent or failed outcome. | `timestamp with time zone` | Yes | — | — |

## Table: `staff`

Stores staff profile, contact, approval, and active-state information. The
profile identifier is also the linked Supabase Auth user identifier.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Staff profile identifier and linked Supabase Auth user ID. | `uuid` (128-bit identifier) | No | — | Primary key and foreign key to `auth.users(id)`; delete cascades to this staff row |
| `first_name` | Staff member's first name. | `text` (unbounded) | Yes | — | — |
| `middle_name` | Staff member's middle name. | `text` (unbounded) | Yes | — | — |
| `last_name` | Staff member's last name. | `text` (unbounded) | Yes | — | — |
| `email` | Optional email value stored on the staff profile; distinct from the generated Supabase Auth identifier used for username login. | `text` (unbounded) | Yes | — | Unique; also has a separate non-unique `idx_staff_email` index |
| `phone_number` | Staff member's contact phone number. | `text` (unbounded) | Yes | — | — |
| `is_approved` | Indicates whether an administrator approved the staff account. | `boolean` | Yes | `false` | — |
| `created_at` | Time the staff profile was created. | `timestamp with time zone` | Yes | `now()` | — |
| `is_active` | Indicates whether the staff profile is active. | `boolean` | Yes | `true` | — |

## Table: `students`

Stores student identity and classroom details used by pickup and parent
association flows.

| Field | Definition | Type / size | Null? | Default | Key / constraint / index |
|---|---|---|---|---|---|
| `id` | Unique student identifier. | `bigint` (signed 64-bit integer) | No | — | Primary key |
| `first_name` | Student's first name. | `text` (unbounded) | Yes | — | — |
| `middle_name` | Student's middle name. | `text` (unbounded) | Yes | — | — |
| `last_name` | Student's last name. | `text` (unbounded) | Yes | — | — |
| `grade_level` | Student's grade. Admin creation validation accepts Grades 1–6. | `text` (unbounded) | Yes | — | Application validation; no database check constraint shown |
| `section` | Student's classroom section. Admin creation validation accepts Sections A–E. | `text` (unbounded) | Yes | — | Application validation; no database check constraint shown |
| `created_at` | Time the student record was created. | `timestamp with time zone` | Yes | `timezone('utc'::text, now())` | — |
| `updated_at` | Time the student record was last updated. | `timestamp with time zone` | Yes | `timezone('utc'::text, now())` | — |

## Index inventory

The supplied `pg_indexes` results showed these indexes. Primary-key and
unique-constraint indexes are included here because they are present in the
database, even where their function is already described above.

| Table | Index | Purpose / columns |
|---|---|---|
| `admin` | `admin_phone_number_unique` | Unique `phone_number` values when not null |
| `admin` | `admins_email_key` | Unique `email` |
| `admin` | `admins_pkey` | Primary key on `id` |
| `admin` | `idx_admins_auth_user_id` | Lookup by `auth_user_id` |
| `admin` | `idx_admins_email` | Lookup by `email`; overlaps the unique email index |
| `notifications` | `notifications_pkey1` | Primary key on `id` |
| `parents` | `idx_parents_auth_user_id` | Unique `auth_user_id` values when not null |
| `parents` | `idx_parents_is_approved` | Lookup/filter by `is_approved` |
| `parents` | `parents_pkey` | Primary key on `id` |
| `parents_students` | `parents_students_parent_id_student_id_key` | Unique pair `(parent_id, student_id)` |
| `parents_students` | `parents_students_pkey` | Primary key on `id` |
| `pickups` | `pickups_pkey` | Primary key on `id` |
| `sms_queue` | `sms_queue_pending_created_at_idx` | `created_at` for rows where `status = 'pending'` |
| `sms_queue` | `sms_queue_pkey` | Primary key on `id` |
| `staff` | `idx_staff_email` | Lookup by `email`; overlaps the unique email index |
| `staff` | `staff_email_key` | Unique `email` |
| `staff` | `staff_pkey` | Primary key on `id` |
| `students` | `students_pkey` | Primary key on `id` |

## Transformations and validation rules found in the application

- Parent phone numbers are normalized by `App\Support\PhoneNumber`: common
  Philippine mobile formats beginning `09`, `639`, or `9` are converted to
  `+63...`; other values are handled as defined in that class.
- In some create/update endpoints, a submitted `full_name` is split into
  `first_name`, `middle_name`, and `last_name` using `App\Support\NameParts`.
- SMS queue status progresses through `pending` → `processing` → `sent` or
  `failed`. The worker may mark expired rows as failed.
- Pickup timestamps are sent as ISO-8601 timestamps by the Laravel route.
- Student grade and section are validated in the admin create endpoint.
  Other endpoints may have different validation, so these are application
  rules, not database-enforced checks.

## Descriptive statistics

The supplied aggregate query returned **2 pickup records for October 2026**
(month start shown as `2026-10-01 00:00:00+00`). This is a point-in-time
result supplied by the project team, not a live statistic. No histograms,
min/max summaries, or other descriptive statistics were provided. Avoid
including statistics for names, phone numbers, or SMS message content.

## Schema / code items to verify before final submission

1. The supplied column export skips ordinal positions in some tables (for
   example, `admin` positions 6 and 8–10 are absent). Confirm the export
   contains every current column before treating this dictionary as complete.
2. The local application references `admin.password_hash`, student
   `is_active`, and Supabase tables `qr_codes` and `entry_records`, but those
   fields/tables were not in the supplied schema export. Confirm whether they
   exist in the live `public` schema, are legacy code, or should be removed
   from the application.
3. The intended relationship is many-to-many through `parents_students`.
   However, the supplied schema also contains `parents.student_id`, and the
   scanned Laravel code reads/writes that direct field; it did not show direct
   use of `parents_students`. Refactor the application to read/write the
   junction table and migrate any existing direct associations before
   considering removal of `parents.student_id`.
4. The `bigint` `id` fields in `parents`, `parents_students`, `pickups`, and
   `students` had no `column_default` in the supplied export. Check PostgreSQL
   identity metadata and test the insert flow to confirm how these IDs are
   generated.
5. The supplied constraints list did not show a foreign key from
   `pickups.parent_id` to `parents.id`.
6. Check the overlapping email indexes on `admin` and `staff` with the
   database owner before removing any; this dictionary records them but does
   not recommend schema changes.

## Provenance, ownership, and related queries

- **Schema inspection:** Supplied query results from
  `information_schema.columns`, `pg_constraint` / `pg_get_constraintdef`,
  and `pg_indexes`.
- **Pickup statistic:** Supplied query grouped `public.pickups` by month
  using `date_trunc('month', picked_at)` and counted rows.
- **Application source reviewed:** Laravel routes and controllers,
  `App\Services\SmsService`, `App\Services\SupabaseSmsQueue`,
  `App\Console\Commands\WorkSmsQueue`, `App\Support\PhoneNumber`, and staff
  pickup JavaScript.
- **Schema creation/change dates:** Table timestamps are documented per
  field above. Supabase object creation dates, actual editor identities, and
  social metadata were not available in the supplied results. Use the
  team's migration/Git history and Supabase audit logs if those are required
  by the capstone rubric.
