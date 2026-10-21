-- Run only after the application deployment and junction-table backfill.
do $$
begin
  if exists (
    select 1
    from information_schema.columns
    where table_schema = 'public'
      and table_name = 'parents'
      and column_name = 'student_id'
  ) and exists (
    select 1
    from public.parents p
    where p.student_id is not null
      and not exists (
        select 1
        from public.parents_students ps
        where ps.parents_id = p.id
          and ps.students_id = p.student_id
      )
  ) then
    raise exception 'Cannot drop parents.student_id: one or more parent-student links have not been copied to parents_students.';
  end if;
end
$$;

alter table public.parents drop column if exists student_id;
