-- Give staff profiles the same explicit Supabase Auth link used by parents and admins.
-- Existing staff rows use their primary key as the Auth user ID, so preserve that link.

alter table public.staff
  add column if not exists auth_users_id uuid references auth.users(id) on delete cascade;

update public.staff as staff_profile
set auth_users_id = auth_user.id
from auth.users as auth_user
where staff_profile.auth_users_id is null
  and staff_profile.id = auth_user.id;

do $$
begin
  if exists (
    select 1
    from public.staff
    where coalesce(is_active, true)
      and (
        auth_users_id is null
        or not exists (
          select 1
          from auth.users
          where auth.users.id = public.staff.auth_users_id
        )
      )
  ) then
    raise exception 'Every active staff profile needs a matching Supabase Auth user before enabling the explicit auth_users_id link.';
  end if;
end
$$;

create unique index if not exists idx_staff_auth_users_id
  on public.staff (auth_users_id)
  where auth_users_id is not null;
