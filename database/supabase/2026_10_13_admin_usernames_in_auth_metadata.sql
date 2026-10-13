-- Migrate admin usernames from public.admin to Supabase Auth metadata.
-- Review the preflight errors before rerunning; they identify accounts that need
-- username or Auth-account conflict resolution before this schema change.

do $$
begin
  if exists (
    select 1
    from public.admin
    where coalesce(is_active, true)
      and (
        auth_user_id is null
        or not exists (
          select 1
          from auth.users
          where auth.users.id = public.admin.auth_user_id
        )
      )
  ) then
    raise exception 'Every active admin needs a valid linked Supabase Auth user before removing the profile username column.';
  end if;

  if exists (
    select 1
    from public.admin
    where coalesce(is_active, true)
      and (
        username is null
        or username !~ '^[A-Za-z0-9][A-Za-z0-9._-]{2,29}$'
      )
  ) then
    raise exception 'Every active admin needs a valid username (3-30 letters, numbers, dots, underscores, or hyphens) before migrating.';
  end if;

  if exists (
    select lower(username)
    from public.admin
    where coalesce(is_active, true)
    group by lower(username)
    having count(*) > 1
  ) then
    raise exception 'Admin usernames must be unique before migrating.';
  end if;

  if exists (
    select 1
    from public.admin as admin_profile
    join auth.users as auth_user
      on lower(auth_user.email) = lower(admin_profile.username) || '@accounts.orion.invalid'
     and auth_user.id <> admin_profile.auth_user_id
    where coalesce(admin_profile.is_active, true)
  ) then
    raise exception 'An admin username conflicts with an existing Supabase Auth account. Resolve the duplicate username before migrating.';
  end if;
end
$$;

update auth.users as auth_user
set
  email = lower(trim(admin_profile.username)) || '@accounts.orion.invalid',
  raw_user_meta_data = coalesce(auth_user.raw_user_meta_data, '{}'::jsonb)
    || jsonb_build_object(
      'username', lower(trim(admin_profile.username)),
      'role', 'admin'
    ),
  email_confirmed_at = coalesce(auth_user.email_confirmed_at, now()),
  updated_at = now()
from public.admin as admin_profile
where admin_profile.auth_user_id = auth_user.id
  and coalesce(admin_profile.is_active, true);

alter table public.admin
  drop column if exists username,
  drop column if exists email;
