-- Rename foreign-key columns to match the requested plural naming.
do $$
declare
  v_table_name text;
  old_column_exists boolean;
  new_column_exists boolean;
  item record;
begin
  foreach v_table_name in array array['pickups', 'parents_students', 'notifications']
  loop
    if to_regclass(format('public.%I', v_table_name)) is null then
      raise exception 'Required table public.% does not exist.', v_table_name;
    end if;

    select exists (
      select 1
      from information_schema.columns c
      where c.table_schema = 'public'
        and c.table_name = v_table_name
        and c.column_name = 'student_id'
    ) into old_column_exists;

    select exists (
      select 1
      from information_schema.columns c
      where c.table_schema = 'public'
        and c.table_name = v_table_name
        and c.column_name = 'students_id'
    ) into new_column_exists;

    if old_column_exists and new_column_exists then
      raise exception 'Both student_id and students_id exist on public.%; resolve the duplicate columns before running this migration.', v_table_name;
    elsif old_column_exists then
      execute format('alter table public.%I rename column student_id to students_id', v_table_name);
    elsif not new_column_exists then
      raise exception 'Neither student_id nor students_id exists on public.%.', v_table_name;
    end if;
  end loop;

  foreach v_table_name in array array['pickups', 'parents_students']
  loop
    select exists (
      select 1
      from information_schema.columns c
      where c.table_schema = 'public'
        and c.table_name = v_table_name
        and c.column_name = 'parent_id'
    ) into old_column_exists;

    select exists (
      select 1
      from information_schema.columns c
      where c.table_schema = 'public'
        and c.table_name = v_table_name
        and c.column_name = 'parents_id'
    ) into new_column_exists;

    if old_column_exists and new_column_exists then
      raise exception 'Both parent_id and parents_id exist on public.%; resolve the duplicate columns before running this migration.', v_table_name;
    elsif old_column_exists then
      execute format('alter table public.%I rename column parent_id to parents_id', v_table_name);
    elsif not new_column_exists then
      raise exception 'Neither parent_id nor parents_id exists on public.%.', v_table_name;
    end if;
  end loop;

  foreach v_table_name in array array['parents', 'staff', 'admin', 'notifications']
  loop
    if to_regclass(format('public.%I', v_table_name)) is null then
      continue;
    end if;

    select exists (
      select 1
      from information_schema.columns c
      where c.table_schema = 'public'
        and c.table_name = v_table_name
        and c.column_name = 'auth_user_id'
    ) into old_column_exists;

    select exists (
      select 1
      from information_schema.columns c
      where c.table_schema = 'public'
        and c.table_name = v_table_name
        and c.column_name = 'auth_users_id'
    ) into new_column_exists;

    if old_column_exists and new_column_exists then
      raise exception 'Both auth_user_id and auth_users_id exist on public.%; resolve the duplicate columns before running this migration.', v_table_name;
    elsif old_column_exists then
      execute format('alter table public.%I rename column auth_user_id to auth_users_id', v_table_name);
    end if;
  end loop;

  for item in
    select * from (values
      ('pickups', 'pickups_student_id_fkey', 'pickups_students_id_fkey'),
      ('parents_students', 'parents_students_student_id_fkey', 'parents_students_students_id_fkey'),
      ('notifications', 'notifications_student_id_fkey', 'notifications_students_id_fkey'),
      ('pickups', 'pickups_parent_id_fkey', 'pickups_parents_id_fkey'),
      ('parents_students', 'parents_students_parent_id_fkey', 'parents_students_parents_id_fkey'),
      ('parents', 'parents_auth_user_id_fkey', 'parents_auth_users_id_fkey'),
      ('staff', 'staff_auth_user_id_fkey', 'staff_auth_users_id_fkey'),
      ('admin', 'admin_auth_user_id_fkey', 'admin_auth_users_id_fkey'),
      ('notifications', 'notifications_auth_user_id_fkey', 'notifications_auth_users_id_fkey')
    ) as constraints_to_rename(table_name, old_name, new_name)
  loop
    if exists (
      select 1 from pg_constraint c
      where c.conrelid = to_regclass(format('public.%I', item.table_name))
        and c.conname = item.old_name
    ) and not exists (
      select 1 from pg_constraint c
      where c.conrelid = to_regclass(format('public.%I', item.table_name))
        and c.conname = item.new_name
    ) then
      execute format(
        'alter table public.%I rename constraint %I to %I',
        item.table_name,
        item.old_name,
        item.new_name
      );
    end if;
  end loop;

  for item in
    select * from (values
      ('idx_parents_auth_user_id', 'idx_parents_auth_users_id'),
      ('idx_staff_auth_user_id', 'idx_staff_auth_users_id'),
      ('idx_admin_auth_user_id', 'idx_admin_auth_users_id')
    ) as indexes_to_rename(old_name, new_name)
  loop
    if to_regclass(format('public.%I', item.old_name)) is not null
      and to_regclass(format('public.%I', item.new_name)) is null then
      execute format('alter index public.%I rename to %I', item.old_name, item.new_name);
    end if;
  end loop;

  foreach v_table_name in array array[
    'parents_students_parent_id_student_id_key',
    'parents_students_parent_id_students_id_key',
    'parents_students_parents_id_student_id_key',
    'parents_students_parents_id_students_id_key'
  ]
  loop
    if exists (
      select 1 from pg_constraint c
      where c.conrelid = 'public.parents_students'::regclass
        and c.conname = v_table_name
    ) and not exists (
      select 1 from pg_constraint c
      where c.conrelid = 'public.parents_students'::regclass
        and c.conname = 'parents_students_parents_id_students_id_key'
    ) then
      execute format(
        'alter table public.parents_students rename constraint %I to %I',
        v_table_name,
        'parents_students_parents_id_students_id_key'
      );
    elsif to_regclass(format('public.%I', v_table_name)) is not null
      and to_regclass('public.parents_students_parents_id_students_id_key') is null then
      execute format(
        'alter index public.%I rename to %I',
        v_table_name,
        'parents_students_parents_id_students_id_key'
      );
    end if;
  end loop;

  if to_regclass('public.parents_students_student_id_idx') is not null
    and to_regclass('public.parents_students_students_id_idx') is null then
    alter index public.parents_students_student_id_idx
      rename to parents_students_students_id_idx;
  end if;
end
$$;
