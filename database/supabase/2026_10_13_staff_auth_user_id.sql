-- Give staff profiles the same explicit Supabase Auth link used by parents and admins.
-- Existing staff rows use their primary key as the Auth user ID, so preserve that link.

alter table public.staff
  add column if not exists auth_user_id uuid references auth.users(id) on delete cascade;

update public.staff as staff_profile
set auth_user_id = auth_user.id
from auth.users as auth_user
where staff_profile.auth_user_id is null
  and staff_profile.id = auth_user.id;

do $$
begin
  if exists (
    select 1
    from public.staff
    where coalesce(is_active, true)
      and (
        auth_user_id is null
        or not exists (
          select 1
          from auth.users
          where auth.users.id = public.staff.auth_user_id
        )
      )
  ) then
    raise exception 'Every active staff profile needs a matching Supabase Auth user before enabling the explicit auth_user_id link.';
  end if;
end
$$;

create unique index if not exists idx_staff_auth_user_id
  on public.staff (auth_user_id)
  where auth_user_id is not null;
