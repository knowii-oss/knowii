-- --------------------------------------------------------
-- Create functions to proxy supabase auth schema functions
-- and allow using those within the Prisma schema without
-- issues due to using multiple schemas (knowing we don't want to
-- touch the auth schema
-- --------------------------------------------------------
create or replace function public.auth_email()
  returns text as $$
begin
  return auth.email();
end;
$$ language plpgsql;

create or replace function public.auth_jwt()
  returns text as $$
begin
  return auth.jwt();
end;
$$ language plpgsql;

create or replace function public.auth_role()
  returns text as $$
begin
  return auth.role();
end;
$$ language plpgsql;

create or replace function public.auth_uid()
  returns text as $$
begin
  return auth.uid();
end;
$$ language plpgsql;

-- --------------------------------------------------------
-- Enable row level security
-- --------------------------------------------------------
alter table clients enable row level security;
alter table customers enable row level security;
alter table communities enable row level security;
alter table prices enable row level security;
alter table products enable row level security;
alter table subscriptions enable row level security;

-- --------------------------------------------------------
-- Make sure the user_id field is linked with the user
-- WARNING: Disabled because breaks with Prisma
-- WORKAROUND: Added triggers to automatically fill the 'clients' table
-- --------------------------------------------------------
--alter table clients add constraint clients_user_id_fk foreign key (user_id) references auth.users(id);
--alter table customers add constraint customers_user_id_fk foreign key (user_id) references auth.users(id);
--alter table subscriptions add constraint subscriptions_user_id_fk foreign key (user_id) references auth.users(id);

-- --------------------------------------------------------
-- Create functions to automatically fill-in the Clients table on the public schema when users are added/changed/deleted in the auth.users table
-- --------------------------------------------------------

-- inserts a row into public."Users"
create or replace function public.handle_new_user()
  returns trigger as $$
begin
  insert into public.clients (id, email)
  values (new.id, new.email);
  return new;
end;
$$ language plpgsql security definer;

-- trigger the function every time a user is created
drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();

-- update a row in public."Users" when the email is updated
create or replace function public.handle_updated_user()
  returns trigger as $$
begin
  update public.clients
  set email = new.email
  where id = new.id::text;
  return new;
end;
$$ language plpgsql security definer;

-- trigger the function every time a user is updated
drop trigger if exists on_auth_user_updated on auth.users;
create trigger on_auth_user_updated
  after update on auth.users
  for each row execute procedure public.handle_updated_user();

-- delete a row from public."Users" when the user is deleted
create or replace function public.handle_deleted_user()
  returns trigger as $$
begin
  delete from public.clients where id = old.id::text;
  return old;
end;
$$ language plpgsql security definer;

-- trigger the function every time a user is deleted
drop trigger if exists on_auth_user_deleted on auth.users;
create trigger on_auth_user_deleted
  after delete on auth.users
  for each row execute procedure public.handle_deleted_user();

-- --------------------------------------------------------
-- Create policies
-- --------------------------------------------------------
drop policy if exists "Allow public read-only access" on products;
create policy "Allow public read-only access" on products for select using (true);
drop policy if exists "Allow public read-only access" on prices;
create policy "Allow public read-only access" on prices for select using (true);
drop policy if exists "User can read own subscription" on subscriptions;
create policy "User can read own subscription" on subscriptions for select using (auth.uid() = user_id);

drop policy if exists "Users can only access their own clients" on clients;
create policy "Users can only access their own clients" on clients for all using (auth.uid() = user_id);

-- --------------------------------------------------------
-- Make sure that the default security rules of Supabase are in place
-- Needed as those might be broken by Prisma when running 'prisma migrate dev' multiple times
-- Reference: https://supabase.com/docs/guides/integrations/prisma#troubleshooting
-- --------------------------------------------------------
grant usage on schema public to postgres, anon, authenticated, service_role;

grant all privileges on all tables in schema public to postgres, anon, authenticated, service_role;
grant all privileges on all functions in schema public to postgres, anon, authenticated, service_role;
grant all privileges on all sequences in schema public to postgres, anon, authenticated, service_role;

alter default privileges in schema public grant all on tables to postgres, anon, authenticated, service_role;
alter default privileges in schema public grant all on functions to postgres, anon, authenticated, service_role;
alter default privileges in schema public grant all on sequences to postgres, anon, authenticated, service_role;
