alter table public.admin
  add column if not exists auth_users_id uuid references auth.users(id) on delete cascade,
  add column if not exists email text,
  add column if not exists username text;

create unique index if not exists idx_admin_auth_users_id
  on public.admin (auth_users_id)
  where auth_users_id is not null;

do $$
begin
  if not exists (
    select 1
    from information_schema.columns
    where table_schema = 'public'
      and table_name = 'admin'
      and column_name = 'phone_number'
  ) then
    raise exception 'Cannot link admin profiles: public.admin.phone_number does not exist.';
  end if;

  if not exists (
    select 1
    from information_schema.columns
    where table_schema = 'auth'
      and table_name = 'users'
      and column_name = 'phone'
  ) then
    raise exception 'Cannot link admin profiles: auth.users.phone does not exist.';
  end if;
end
$$;

update public.admin as admin_profile
set phone_number = auth_user.phone
from auth.users as auth_user
where admin_profile.auth_users_id = auth_user.id
  and admin_profile.phone_number is null
  and nullif(auth_user.phone, '') is not null
  and not exists (
    select 1
    from public.admin as other_admin
    where other_admin.id <> admin_profile.id
      and other_admin.phone_number = auth_user.phone
  );

update public.admin as admin_profile
set email = auth_user.email
from auth.users as auth_user
where admin_profile.auth_users_id = auth_user.id
  and nullif(trim(admin_profile.email), '') is null
  and nullif(trim(auth_user.email), '') is not null;

with username_bases as (
  select
    admin_profile.id,
    case
      when length(regexp_replace(lower(split_part(coalesce(auth_user.email, admin_profile.email, ''), '@', 1)), '[^a-z0-9._-]', '', 'g')) >= 3
        then left(regexp_replace(lower(split_part(coalesce(auth_user.email, admin_profile.email, ''), '@', 1)), '[^a-z0-9._-]', '', 'g'), 30)
      else 'admin_' || left(replace(admin_profile.id::text, '-', ''), 8)
    end as username_base
  from public.admin as admin_profile
  left join auth.users as auth_user on auth_user.id = admin_profile.auth_users_id
  where admin_profile.username is null
),
ranked_usernames as (
  select
    id,
    username_base,
    count(*) over (partition by username_base) as matching_count
  from username_bases
)
update public.admin as admin_profile
set username = case
  when ranked_usernames.matching_count = 1 then ranked_usernames.username_base
  else left(ranked_usernames.username_base, 21) || '_' || left(replace(ranked_usernames.id::text, '-', ''), 8)
end
from ranked_usernames
where admin_profile.id = ranked_usernames.id
  and admin_profile.username is null;

create unique index if not exists idx_admin_username
  on public.admin (lower(username))
  where username is not null;

with phone_matches as (
  select distinct admin_profile.id as admin_id, auth_user.id as auth_users_id
  from public.admin as admin_profile
  join auth.users as auth_user
    on (
      case
        when length(regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g')) = 11
          and regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g') like '09%'
          then '63' || substr(regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g'), 2)
        when length(regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g')) = 10
          and regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g') like '9%'
          then '63' || regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g')
        else regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g')
      end
    ) = (
      case
        when length(regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g')) = 11
          and regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g') like '09%'
          then '63' || substr(regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g'), 2)
        when length(regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g')) = 10
          and regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g') like '9%'
          then '63' || regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g')
        else regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g')
      end
    )
  where admin_profile.auth_users_id is null
    and nullif(regexp_replace(coalesce(admin_profile.phone_number, ''), '[^0-9]', '', 'g'), '') is not null
    and nullif(regexp_replace(coalesce(auth_user.phone, ''), '[^0-9]', '', 'g'), '') is not null
),
unambiguous_matches as (
  select phone_matches.admin_id, phone_matches.auth_users_id
  from phone_matches
  join (
    select admin_id
    from phone_matches
    group by admin_id
    having count(*) = 1
  ) as one_auth_user_per_admin using (admin_id)
  join (
    select auth_users_id
    from phone_matches
    group by auth_users_id
    having count(*) = 1
  ) as one_admin_per_auth_user using (auth_users_id)
)
update public.admin as admin_profile
set auth_users_id = unambiguous_matches.auth_users_id
from unambiguous_matches
where admin_profile.id = unambiguous_matches.admin_id
  and admin_profile.auth_users_id is null;
