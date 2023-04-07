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
alter table public.users enable row level security;
alter table public.user_profiles enable row level security;
alter table public.customers enable row level security;
alter table public.prices enable row level security;
alter table public.products enable row level security;
alter table public.subscriptions enable row level security;

alter table public.communities enable row level security;
alter table public.resource_collections enable row level security;
alter table public.resources enable row level security;
alter table public.tags enable row level security;

alter table public._community_admins enable row level security;
alter table public._community_members enable row level security;
alter table public._community_owners enable row level security;
alter table public._resource_tags enable row level security;
alter table public._prisma_migrations enable row level security;

-- --------------------------------------------------------
-- Create functions to automatically fill-in the users and user_profiles tables on the public schema when users are added/changed/deleted in the auth.users table
-- --------------------------------------------------------

-- inserts a row into public."users" when a new row is added to auth."users"
create or replace function public.handle_new_user()
  returns trigger as $$
begin
  -- FIXME when a new user is created on auth."users", maybe there is already a row in public."users" with the same email
  -- In that case we need to update the existing row and not try to create a new one
  -- See https://github.com/DeveloPassion/knowii/issues/299
  -- Create the user in public.users
  insert into public.users (user_id_external, username, email)
    values (new.id, new.email, new.email);

  return new;
end;
$$ language plpgsql security definer;

-- trigger the function every time a user (supabase) is created
drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();

-- inserts a row into public."user_profiles"
create or replace function public.create_new_user_profile()
  returns trigger as $$
  declare
    given_name text;
    family_name text;
  begin
  -- Fetch user metadata
  select raw_user_meta_data -> 'given_name' as given_name, raw_user_meta_data -> 'family_name' as family_name
  into given_name, family_name
  from auth.users
  where id = new.user_id_external::uuid;

  -- Create the user profile in public.user_profiles
  insert into public.user_profiles (user_id, user_id_external, given_name, family_name)
  values (new.id, new.user_id_external, given_name, family_name);

  return new;
end;
$$ language plpgsql security definer;

-- trigger the function every time a user is created
drop trigger if exists on_public_user_created on public.users;
create trigger on_public_user_created
  after insert on public.users
  for each row execute procedure public.create_new_user_profile();

-- update a row in public."Users" when the email is updated
create or replace function public.handle_updated_user()
  returns trigger as $$
begin
  update public.users
  set email = new.email
  where user_id_external = new.id::uuid;

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
  -- We don't delete users, but clean their account and profile
  --delete from public.users where id = old.id::text;
  update public.users
  -- we remove the external user id (supabase) but keep the email to enable future account recovery if needed
  set user_id_external = null
  where user_id_external = new.id::uuid;

  update public.user_profiles
  -- we clear all fields we can clear
  set user_id_external = null, -- first of all the external user id
      image_url = '',
      phone = '',
      given_name = '',
      family_name = '',
      website = '',
      twitter = '',
      facebook = '',
      instagram = '',
      tiktok = '',
      github = '',
      bio = '',
      location = '',
      organization_name = '',
      organization_link = ''
  where user_id_external = new.id::uuid;

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
create policy "User can read own subscription" on subscriptions for select using (auth.uid() = user_id_external);

drop policy if exists "Users can only access their own account" on users;
create policy "Users can only access their own account" on users for all using (auth.uid() = user_id_external);

drop policy if exists "User profiles are public" on user_profiles;
create policy "User profiles are public" on user_profiles for select using (true);

drop policy if exists "Users can edit their own user profile" on user_profiles;
create policy "Users can edit their own user profile" on user_profiles for update using (auth.uid() = user_id_external);

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
