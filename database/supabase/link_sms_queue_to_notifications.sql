-- Link a queued SMS to the notification record that created it.
-- Existing queue rows remain valid with a NULL notifications_id.

alter table public.sms_queue
  add column if not exists notifications_id uuid;

do $$
begin
  if not exists (
    select 1
    from pg_constraint
    where conname = 'sms_queue_notifications_id_fkey'
      and conrelid = 'public.sms_queue'::regclass
  ) then
    alter table public.sms_queue
      add constraint sms_queue_notifications_id_fkey
      foreign key (notifications_id)
      references public.notifications(id)
      on delete set null;
  end if;
end;
$$;

create index if not exists sms_queue_notifications_id_idx
  on public.sms_queue (notifications_id);
