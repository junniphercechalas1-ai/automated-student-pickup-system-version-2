-- Match the notifications foreign-key column name to the plural table name.
do $$
declare
  old_column_exists boolean;
  new_column_exists boolean;
begin
  if to_regclass('public.sms_queue') is null then
    raise exception 'Required table public.sms_queue does not exist.';
  end if;

  select exists (
    select 1
    from information_schema.columns c
    where c.table_schema = 'public'
      and c.table_name = 'sms_queue'
      and c.column_name = 'notification_id'
  ) into old_column_exists;

  select exists (
    select 1
    from information_schema.columns c
    where c.table_schema = 'public'
      and c.table_name = 'sms_queue'
      and c.column_name = 'notifications_id'
  ) into new_column_exists;

  if old_column_exists and new_column_exists then
    raise exception 'Both notification_id and notifications_id exist on public.sms_queue; resolve the duplicate columns before running this migration.';
  elsif old_column_exists then
    alter table public.sms_queue rename column notification_id to notifications_id;
  elsif not new_column_exists then
    raise exception 'Neither notification_id nor notifications_id exists on public.sms_queue; run the SMS queue link migration first.';
  end if;

  if exists (
    select 1
    from pg_constraint
    where conrelid = 'public.sms_queue'::regclass
      and conname = 'sms_queue_notification_id_fkey'
  ) and not exists (
    select 1
    from pg_constraint
    where conrelid = 'public.sms_queue'::regclass
      and conname = 'sms_queue_notifications_id_fkey'
  ) then
    alter table public.sms_queue
      rename constraint sms_queue_notification_id_fkey
      to sms_queue_notifications_id_fkey;
  end if;

  if to_regclass('public.sms_queue_notification_id_idx') is not null
    and to_regclass('public.sms_queue_notifications_id_idx') is null then
    alter index public.sms_queue_notification_id_idx
      rename to sms_queue_notifications_id_idx;
  end if;
end;
$$;
