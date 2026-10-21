<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
"# automated-student-pickup-system" 

## Render and Supabase

The admin parent and student screens load records from Supabase through the Laravel server. In the Render service's environment settings, configure:

- `SUPABASE_URL`: the Supabase project URL.
- `SUPABASE_SERVICE_KEY`: the Supabase service-role key. Keep this server-side; never expose it through a `VITE_` variable.
- `VITE_SUPABASE_URL` and `VITE_SUPABASE_ANON_KEY`: the project URL and public anon key used by browser authentication. Make these available as Docker build arguments as well when building with the Dockerfile.

After saving environment changes, redeploy the service. The Dockerfile runs pending Laravel migrations at container startup. If the Render service uses a native runtime instead of the Dockerfile, run `php artisan migrate --force` as its deploy command.

Parent and staff signup creates a profile in the existing Supabase `parents` or `staff` table with `is_approved = false`; approval updates that profile, so no separate Supabase registration table is needed. To remove the temporary table from the earlier setup, run [`database/supabase/2026_10_21_drop_pending_registrations.sql`](./database/supabase/2026_10_21_drop_pending_registrations.sql) in Supabase SQL Editor after confirming it has no data. The script blocks the drop if it finds rows.


Admin usernames are stored in Supabase Auth user metadata, like parent and staff usernames, rather than in `public.admin`. Before deploying code that uses the pluralized Auth foreign-key names, run [`database/supabase/2026_10_21_rename_student_foreign_keys_to_students_id.sql`](./database/supabase/2026_10_21_rename_student_foreign_keys_to_students_id.sql) in the Supabase SQL Editor. If you still need to link admin profiles to Auth users, run [`database/supabase/2026_10_13_link_admins_to_auth_users.sql`](./database/supabase/2026_10_13_link_admins_to_auth_users.sql) after this rename. Then, before deploying the admin username change, run [`database/supabase/2026_10_13_admin_usernames_in_auth_metadata.sql`](./database/supabase/2026_10_13_admin_usernames_in_auth_metadata.sql). It keeps existing passwords, changes linked active admin Auth emails to the app's synthetic username identities, copies each active admin username and role into Auth metadata, then removes the profile-table `username` and `email` columns. Inactive admin profiles are ignored by migration validation and metadata updates. Resolve any migration preflight errors before retrying.

Staff profiles use the same explicit Auth link as parent and admin profiles. If this link has not yet been created, run [`database/supabase/2026_10_13_staff_auth_user_id.sql`](./database/supabase/2026_10_13_staff_auth_user_id.sql) before the rename migration above. It backfills existing links from the legacy `staff.id`-equals-Auth-ID relationship and stops if any active staff profile cannot be linked.

Parent-to-admin messaging has been removed. Existing support-message database records are retained but are no longer accessible through the application; parent and student profile records are stored in Supabase.

## GSM SMS bridge

Render cannot access a GSM modem plugged into a personal computer. To send bulk notifications with that modem, Render adds each SMS to the Supabase `sms_queue` table, and a worker running on the computer connected to the modem sends it.

Configure the Render service with `SMS_PROVIDER=supabase_queue`, `SUPABASE_URL`, and `SUPABASE_SERVICE_KEY`. The service-role key must be able to insert and update rows in `notifications` and `sms_queue`; the queue table's `id` must be generated, `status` must accept `pending`, `processing`, `sent`, and `failed`, and `provider_id` and `error_message` must be nullable. `expires_at`, `claimed_at`, and `completed_at` must allow the initial insert (nullable or defaulted). Keep the service-role key only in server-side environment settings or the laptop's local `.env`; do not use a `VITE_` variable or commit the key.

To relate account-decision notifications to their queued SMS, first verify
that `notifications.id` is a UUID, then run
[`database/supabase/link_sms_queue_to_notifications.sql`](./database/supabase/link_sms_queue_to_notifications.sql)
in the Supabase SQL Editor for a new setup. It adds a nullable foreign key from
`sms_queue.notifications_id` to `notifications.id`; existing queue rows remain
unlinked. If you already ran the previous version of this script, do not rerun
it; run
[`database/supabase/2026_10_21_rename_notification_foreign_key_to_notifications_id.sql`](./database/supabase/2026_10_21_rename_notification_foreign_key_to_notifications_id.sql)
to rename `notification_id` and its associated constraint/index. The Laravel
app creates the notification before enqueuing its SMS, and the local worker
records `sent_at` after delivery. Password-reset SMS messages are intentionally
not copied into `notifications`.

On the Windows laptop connected to the GSM modem:

1. Install the application dependencies and copy `.env.example` to `.env` if needed.
2. Set `SUPABASE_URL` and `SUPABASE_SERVICE_KEY` to the same Supabase project used by Render. Set `GSM_SERIAL_PORT` and `GSM_BAUD_RATE` for the connected modem.
3. Run `php artisan sms:work` and leave it running. It polls the queue every five seconds by default. Use `php artisan sms:work --once` to process at most one pending message.

The worker claims a row by changing `pending` to `processing` before sending. It then stores `sent` or `failed` and updates `provider_id`, `error_message`, and the claim/completion timestamps. The laptop must remain powered on, connected to the internet, and have the worker running to send queued messages.

Signup verification SMS rows include the OTP expiration time. If the worker does
not pick up a code before its five-minute expiry, it marks that queue row failed
instead of sending a code the parent can no longer use. A `sent` row means the
modem accepted the SMS for submission; it does not confirm delivery to the phone.
