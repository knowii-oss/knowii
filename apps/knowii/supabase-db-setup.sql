-- Make sure the user_id field is linked with the user
alter table customers add constraint customers_user_id_fk foreign key (user_id) references auth.users(id);
alter table subscriptions add constraint subscriptions_user_id_fk foreign key (user_id) references auth.users(id);
alter table clients add constraint clients_user_id_fk foreign key (user_id) references auth.users(id);

-- Enable row level security
alter table customers enable row level security;
alter table products enable row level security;
alter table prices enable row level security;
alter table subscriptions enable row level security;
alter table clients enable row level security;
alter table communities enable row level security;

-- Create policies
drop policy if exists "Allow public read-only access" on products;
create policy "Allow public read-only access" on products for select using (true);
drop policy if exists "Allow public read-only access" on prices;
create policy "Allow public read-only access" on prices for select using (true);
drop policy if exists "User can read own subscriptio" on subscriptions;
create policy "User can read own subscription" on subscriptions for select using (auth.uid() = user_id);

drop policy if exists "Users can only access their own clients" on clients;
create policy "Users can only access their own clients" on clients for all using (auth.uid() = user_id);

-- Make sure that the default security rules of Supabase are in place
-- Needed as those might be broken by Prisma when running 'prisma migrate dev' multiple times
-- Reference: https://supabase.com/docs/guides/integrations/prisma#troubleshooting
grant usage on schema public to postgres, anon, authenticated, service_role;

grant all privileges on all tables in schema public to postgres, anon, authenticated, service_role;
grant all privileges on all functions in schema public to postgres, anon, authenticated, service_role;
grant all privileges on all sequences in schema public to postgres, anon, authenticated, service_role;

alter default privileges in schema public grant all on tables to postgres, anon, authenticated, service_role;
alter default privileges in schema public grant all on functions to postgres, anon, authenticated, service_role;
alter default privileges in schema public grant all on sequences to postgres, anon, authenticated, service_role;
