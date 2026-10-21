-- Remove the temporary public staging table without discarding pending applicants.
do $$
declare
  has_rows boolean;
begin
  if to_regclass('public.pending_registrations') is null then
    return;
  end if;

  execute 'select exists (select 1 from public.pending_registrations)' into has_rows;
  if has_rows then
    raise exception 'public.pending_registrations contains data; export or migrate its rows before dropping it.';
  end if;

  execute 'drop table public.pending_registrations';
end;
$$;
