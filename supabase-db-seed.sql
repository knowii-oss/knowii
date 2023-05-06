-- --------------------------------------------------------
-- Create functions to proxy supabase auth schema functions
-- and allow using those within the Prisma schema without
-- issues due to using multiple schemas (knowing we don't want to
-- touch the auth schema
-- --------------------------------------------------------
create or replace function public.auth_email()
  returns text as
$$
begin
  return auth.email();
end;
$$ language plpgsql;

create or replace function public.auth_jwt()
  returns text as
$$
begin
  return auth.jwt();
end;
$$ language plpgsql;

create or replace function public.auth_role()
  returns text as
$$
begin
  return auth.role();
end;
$$ language plpgsql;

create or replace function public.auth_uid()
  returns text as
$$
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
-- Functions to generate usernames
-- --------------------------------------------------------
create or replace function public.replace_diacritics(full_name text)
  returns text
  language plpgsql
  strict
as
$$
declare
  plain_name text := '';
  i          int;
begin
  for i in 1..length(full_name)
    loop
      plain_name := plain_name || case substr(full_name, i, 1)
                                    WHEN 'á' THEN 'a'
                                    WHEN 'à' THEN 'a'
                                    WHEN 'ã' THEN 'a'
                                    WHEN 'â' THEN 'a'
                                    WHEN 'ä' THEN 'a'
                                    WHEN 'é' THEN 'e'
                                    WHEN 'è' THEN 'e'
                                    WHEN 'ê' THEN 'e'
                                    WHEN 'ë' THEN 'e'
                                    WHEN 'í' THEN 'i'
                                    WHEN 'ì' THEN 'i'
                                    WHEN 'î' THEN 'i'
                                    WHEN 'ï' THEN 'i'
                                    WHEN 'ó' THEN 'o'
                                    WHEN 'ò' THEN 'o'
                                    WHEN 'õ' THEN 'o'
                                    WHEN 'ô' THEN 'o'
                                    WHEN 'ö' THEN 'o'
                                    WHEN 'ú' THEN 'u'
                                    WHEN 'ù' THEN 'u'
                                    WHEN 'û' THEN 'u'
                                    WHEN 'ü' THEN 'u'
                                    WHEN 'ç' THEN 'c'
                                    WHEN 'ñ' THEN 'n'
                                    ELSE substr(full_name, i, 1)
        END;
    end loop;
  return plain_name;
end;
$$;

create or replace function public.random_between(low int, high int)
  returns integer
  language plpgsql
  strict
as
$$
begin
  return floor(random() * (high - low + 1) + low);
end;
$$;

-- clean the given username (remove unwanted characters)
create or replace function public.clean_username(username text)
  returns text
  language plpgsql
  strict
as
$$
declare
  clean_username text;
begin
  -- trim
  clean_username = trim(username); -- limit the initial length

  -- remove characters that aren't ascii, space or '-'
  clean_username = regexp_replace(clean_username, '[^a-zA-Z0-9 -_]+', '', 'g');

  -- replace diacritics
  username = public.replace_diacritics(clean_username);

  -- remove trailing whitespace and '-' (begin and end of the string)
  clean_username = regexp_replace(clean_username, '\s+$', '');
  clean_username = regexp_replace(clean_username, '^\s+', '');
  clean_username = regexp_replace(clean_username, '^-+', '');
  clean_username = regexp_replace(clean_username, '-+$', '');

  -- replace '-' and spaces
  clean_username = regexp_replace(clean_username, '-', '_', 'g');
  clean_username = regexp_replace(clean_username, ' ', '_', 'g');

  -- all lowercase
  clean_username = lower(clean_username);

  return clean_username;
end;
$$;

create or replace function public.generate_username(full_name text)
  returns text
  security definer -- Required so that we can call this without a user session in the API
  language plpgsql
  strict
as
$$
declare
  adjectives           text[] := ARRAY [
    'abandoned', 'able', 'absolute', 'academic', 'acceptable', 'acclaimed', 'accomplished', 'accurate', 'achievable', 'active', 'actual', 'adaptable', 'adaptive', 'adequate', 'adjacent', 'admirable', 'advanced', 'advantageous', 'adventurous', 'aesthetic', 'agile', 'airtight', 'alert', 'alluring', 'alternative', 'altruistic', 'amazing', 'ambitious', 'amiable', 'amicable', 'amusing', 'anchored', 'ancient', 'angelic', 'angular', 'another', 'antique', 'apparent', 'appealing', 'aquatic', 'arched', 'ardent', 'aromatic', 'artistic', 'aspiring', 'assertive', 'astonishing', 'athletic', 'atmospheric', 'attentive', 'attractive', 'authentic', 'automatic', 'available', 'average', 'aware', 'awesome', 'awkward', 'azure', 'graceful', 'joyful', 'brave', 'curious', 'enthusiast', 'fierce', 'happy', 'kind', 'lively', 'noble', 'polite', 'silly', 'witty', 'yummy', 'blissful', 'courageous', 'dazzling', 'ecstatic', 'elegant', 'exquisite', 'fantastic', 'friendly', 'gleaming', 'glowing', 'harmonious', 'heavenly', 'inspiring', 'jubilant', 'lovely', 'magnificent', 'majestic', 'miraculous', 'perfect', 'radiant', 'wise', 'other', 'new', 'good', 'old', 'little', 'great', 'small', 'young', 'long', 'black', 'high', 'only', 'big', 'right', 'large', 'real', 'sure', 'different', 'important', 'public', 'possible', 'full', 'whole', 'certain', 'human', 'major', 'social', 'true', 'economic', 'open', 'early', 'free', 'national', 'strong', 'special', 'clear', 'local', 'private', 'short', 'poor', 'recent', 'dark', 'fine', 'foreign', 'ready', 'cold', 'low', 'heavy', 'serious', 'single', 'left', 'necessary', 'general', 'easy', 'likely', 'beautiful', 'happy', 'past', 'close', 'common', 'simple', 'natural','main', 'various', 'available', 'nice', 'present', 'final', 'sorry', 'entire', 'current','similar', 'deep', 'huge', 'rich', 'nuclear', 'strange', 'quiet', 'front', 'wide', 'modern', 'concerned', 'very', 'alone', 'particular', 'bright', 'supposed', 'basic', 'aware', 'total', 'legal', 'original','soft', 'alive', 'interested', 'tall', 'warm', 'popular', 'tiny', 'top', 'normal', 'powerful', 'silent', 'impossible', 'quick', 'safe', 'thin','familiar', 'gray','fresh', 'physical', 'individual', 'willing', 'crazy', 'sick', 'angry', 'perfect', 'tired', 'wild', 'moral', 'brown', 'dangerous', 'famous', 'terrible', 'successful', 'fair', 'professional', 'official', 'obvious', 'glad', 'central', 'chief', 'effective', 'light', 'complete', 'interesting', 'thick', 'proper', 'involved', 'responsible', 'narrow', 'civil', 'industrial', 'dry', 'yellow', 'specific', 'sharp','sudden', 'direct', 'following','growing', 'significant', 'traditional'
    ];
  nouns                text[] := ARRAY [
    'anchor', 'apple', 'balloon', 'beach', 'book', 'breeze', 'brush', 'candy', 'canyon', 'campfire', 'coconut', 'compass', 'computer', 'coral', 'crab', 'cruise', 'cave', 'diamond', 'desert', 'emerald', 'flower', 'galaxy', 'gem', 'grape', 'guitar', 'hat', 'helicopter', 'hill', 'honey', 'island', 'jellyfish', 'jetpack', 'journal', 'kayak', 'kiwi', 'lamp', 'lighthouse', 'map', 'melon', 'mermaid', 'micro', 'moon', 'mountain', 'neuron', 'ocean', 'obsidian', 'orange', 'paddle', 'palm', 'parachute', 'paradise', 'pear', 'pebble', 'pen', 'pirate', 'painting', 'rain', 'rainbow', 'raincoat', 'river', 'ruby', 'sage', 'sand', 'sailboat', 'sapphire', 'scarf', 'seashell', 'seaweed', 'sea', 'shoe', 'sock', 'space', 'spaceship', 'spectrum', 'star', 'sunset', 'sundown', 'surfboard', 'sweater', 'table', 'telescope', 'tent', 'topaz', 'tree', 'umbrella', 'universe', 'violet', 'volcano', 'water', 'wave', 'amygdala', 'axion', 'blackhole', 'brain', 'cognition', 'cosmos', 'cranium', 'dendrite', 'dopamine', 'galactic', 'hippocampus', 'interstellar', 'neural', 'neuroscience', 'orbit', 'planetarium', 'proton', 'psychology', 'quasar', 'radiation', 'solar', 'spacetime', 'synapse', 'telescope', 'universe', 'wormhole', 'Quartz', 'Calcite', 'Halite', 'Pyrite', 'Magnetit', 'Galena', 'Olivine', 'Malachit', 'Hematite', 'Fluorite', 'Apatite', 'Biotite', 'Gypsum', 'Sphaleri', 'Chalcopy', 'Orthocla', 'Muscovit', 'Magnetit', 'Hornblen', 'Pyroxene', 'Talc', 'Feldspar', 'Zircon', 'Augite', 'Barite', 'Cinnabar', 'Hematite', 'Hessoni', 'Chromite', 'Molybden', 'Pyrolusi', 'Staurolit', 'Wolfram', 'Cassiter', 'Garnet', 'Rhodonit', 'Tourmali', 'Baryte', 'Chalcant', 'Magnetit', 'Prehnite', 'Bauxite', 'Celestite', 'Kyanite', 'Siderite', 'Spinel', 'Topaz', 'Zoisite', 'Albite', 'Andalus', 'Aragonit', 'Azurite', 'Beryl', 'Brucite', 'Cerussit', 'Danburit', 'Enstatit', 'Forsteri', 'Galaxite', 'Goethit', 'Graphite', 'Ilmenit', 'Kamacite', 'Kaolinit', 'Lepidoli', 'Magnetit', 'Marcasit', 'Monazite', 'Natrolit', 'Olivine', 'Opal', 'Phlogopi', 'Picroili', 'Quartz', 'Realgar', 'Rhodochr', 'Scheelit', 'Smithson', 'Sodalite', 'Sphene', 'Sylvanit', 'Tetrahed', 'Titanite', 'Uvarovi', 'Vesuvian', 'Willemite', 'Xenotime', 'Yttrialit', 'Zirconol', 'Alunite', 'Antigor', 'Arsenop', 'Axinite', 'Bertrand', 'Bismutit', 'Bornite', 'Carnegie', 'Chabazit', 'Chrysoco', 'Clinocl', 'Cordieri', 'Cuprite', 'Diaspor', 'Edenite', 'Faujasit', 'Ferroaxi', 'Fluorapa', 'Gibbsite', 'Gold', 'Hisinger', 'Hydrozi', 'Jadeite', 'Kermesit', 'Kolwezi', 'Kunzite', 'Lazulite', 'Linarite', 'Manganoc', 'Metator', 'Nadorit', 'Neotoci', 'Olivenit', 'Pharmaco', 'Phosgeni', 'Planchei', 'Pumpelly', 'Pyrrhoti', 'Rhodonit', 'Sainfeld', 'Saponit', 'Scheelit', 'Sideriti', 'Simpsoni', 'Sphaleri', 'Stannite', 'Stibnit', 'Tremolit', 'Turquois', 'Tyuyamun', 'man', 'world', 'hand', 'room', 'face', 'thing', 'place', 'door', 'woman', 'house', 'money', 'father', 'government', 'country', 'mother', 'water', 'state', 'family', 'voice', 'fact', 'moment', 'power', 'city', 'business', 'war', 'school', 'system', 'car', 'number', 'office', 'point', 'body', 'air', 'mind', 'home', 'company', 'group', 'boy', 'problem', 'bed', 'death', 'hair', 'child', 'sense', 'job', 'light', 'question', 'idea', 'law', 'word', 'party', 'food', 'floor', 'book', 'reason', 'story', 'son', 'heart', 'friend', 'interest', 'right', 'town', 'history', 'land', 'program', 'game', 'control', 'matter', 'policy', 'oil', 'window', 'nation', 'position', 'ground', 'blood', 'action', 'wall', 'street', 'husband', 'fire', 'mouth', 'arm', 'sound', 'service', 'chance', 'information', 'price', 'building', 'road', 'paper', 'court', 'attention', 'space', 'trouble', 'form', 'society', 'art', 'market', 'force', 'effect', 'nature', 'chair', 'period', 'order', 'television', 'president', 'tax', 'field', 'glass', 'thought', 'industry', 'process', 'phone', 'plan', 'center', 'truth'
    ];
  animals              TEXT[] := ARRAY [
    'aardvark', 'albatross', 'ant', 'anteater', 'antelope', 'armadillo', 'baboon', 'badger', 'bat', 'beaver', 'bee', 'bison', 'boar', 'buffalo', 'butterfly', 'camel', 'caribou', 'caterpillar', 'chameleon', 'cheetah', 'chicken', 'chimpanzee', 'clam', 'cobra', 'cockroach', 'condor', 'cougar', 'cow', 'coyote', 'crab', 'crane', 'crocodile', 'crow', 'deer', 'dingo', 'dolphin', 'dove', 'dragonfly', 'duck', 'eagle', 'echidna', 'eel', 'elephant', 'emu', 'falcon', 'ferret', 'finch', 'firefly', 'flamingo', 'fox', 'gazelle', 'gecko', 'gerbil', 'gibbon', 'giraffe', 'gnu', 'goat', 'goose', 'gorilla', 'grasshopper', ' guinea pig ', 'hamster', 'hare', 'hawk', 'hedgehog', 'heron', 'hornet', 'horse', 'hyena', 'ibex', 'ibis', 'jackal', 'jellyfish', 'kangaroo', 'koala', 'kudu', 'lemming', 'lemur', 'leopard', 'lizard', 'llama', 'lobster', 'lynx', 'macaw', 'magpie', 'manatee', 'mandrill', 'marmot', 'meerkat', 'mink', 'mole', 'mongoose', 'moose', 'mosquito', 'mule', 'muskrat', 'narwhal', 'newt', 'nightingale', 'ocelot', 'octopus', 'opossum', 'orca', 'ostrich', 'otter', 'owl', 'ox', 'oyster', 'panda', 'panther', 'parrot', 'peacock', 'pelican', 'penguin', 'pheasant', 'pig', 'pigeon', 'porcupine', 'possum', 'quail', 'rabbit', 'raccoon', 'ram', 'rat', 'rattlesnake', 'raven', 'reindeer', 'rhinoceros', 'roadrunner', 'robin', 'rooster', 'salmon', 'sardine', 'scorpion', 'seahorse', 'sheep', 'shrimp', 'sloth', 'snail', 'snake', 'sparrow', 'squirrel', 'starfish', 'stork', 'swan', 'tadpole', 'termite', 'tiger', 'toad', 'tortoise', 'toucan', 'trout', 'turkey', 'turtle', 'vulture', 'wallaby', 'walrus', 'weasel', 'whale', 'wildcat', 'wolf', 'wombat', 'woodpecker', 'yak', 'zebra'
    ];
  colors               TEXT[] := ARRAY [
    'amber', 'ashen', 'aubergine', 'auburn', 'beige', 'black', 'blue', 'blush', 'brass', 'bronze', 'bubblegum', 'canary', 'celadon', 'cerulean', 'chartreuse', 'cherry', 'cinnamon', 'cocoa', 'cobalt', 'coral', 'cornflower', 'cream', 'crimson', 'dandelion', 'dusty', 'electricblue', 'forest', 'fuchsia', 'golden', 'gray', 'green', 'indigo', 'jade', 'jungle', 'khaki', 'lavender', 'lemon', 'lime', 'lilac', 'magenta', 'mandarin', 'mauve', 'mahogany', 'maroon', 'mint', 'mustard', 'navy', 'ochre', 'olive', 'onyx', 'opals', 'opalescent', 'orange', 'pearl', 'peach', 'pastel', 'pink', 'purple', 'raspberry', 'red', 'rose', 'ruby', 'rust', 'sable', 'saffron', 'salmon', 'sand', 'scarlet', 'sepia', 'sienna', 'silver', 'skyblue', 'slate', 'smoky', 'sunset', 'sunshine', 'tangerine', 'tan', 'taupe', 'teak', 'teal', 'tealish', 'tomato', 'turmeric', 'turquoise', 'ultraviolet', 'verdigris', 'vermilion', 'violet', 'viridian', 'white', 'yellow', 'rouge', 'bleu', 'vert', 'jaune', 'noir', 'orange', 'gris', 'rose', 'brun'
    ];
  username             text;
  max_length           int    := 29; -- username = 30 characters + '_' + 1-6 random digits
  random_suffix        text;
  random_suffix_length int    := 6;
  i                    int;
begin
  RAISE NOTICE 'Generating a random username';

  -- limit the initial length
  username = left(full_name, max_length);

  -- trim
  username = trim(username);

  -- generate a random starting point if necessary
  if username = '' then
    username := colors[ceil(random() * array_length(colors, 1))];
  end if;

  -- clean the username
  username = public.clean_username(username);

  -- generate random words
  while length(username) < max_length
    loop
      case ceil(random() * 2)
        when 1 then if array_length(animals, 1) > 0 and array_length(nouns, 1) > 0 then
          username := username || '_' || nouns[ceil(random() * array_length(nouns, 1))] || '_' ||
                      animals[ceil(random() * array_length(animals, 1))];
        else
          username := username || '_' || adjectives[ceil(random() * array_length(adjectives, 1))] || '_' ||
                      colors[ceil(random() * array_length(colors, 1))] || '_' ||
                      nouns[ceil(random() * array_length(nouns, 1))];
        end if;
        when 2
          then if array_length(adjectives, 1) > 0 and array_length(colors, 1) > 0 and array_length(nouns, 1) > 0 then
            username := username || '_' || adjectives[ceil(random() * array_length(adjectives, 1))] || '_' ||
                        colors[ceil(random() * array_length(colors, 1))] || '_' ||
                        nouns[ceil(random() * array_length(nouns, 1))];
          end if;
        end case;
    end loop;

  -- generate and add random suffix (digits)
  random_suffix = public.random_between(1, 999999) as text;
  username = concat(username, '_', random_suffix);

  -- all lowercase
  username = lower(username);

  -- reduce the string length until it respects the limit
  while length(username) >= (max_length + random_suffix_length)
    loop
      RAISE NOTICE 'Reducing the length of the username';

      -- remove the last word before the digits
      username = regexp_replace(username, '(.*_)([a-zA-Z]+_)(\d+)', '\1\3');
    end loop;

  return username;
end;
$$;

-- --------------------------------------------------------
-- Create functions to automatically fill-in the users and user_profiles tables on the public schema when users are added/changed/deleted in the auth.users table
-- --------------------------------------------------------

-- Check if a username is available. Returns true if it is
create or replace function public.is_username_available(username_to_check text)
  returns boolean
  security definer -- Required so that we can call this without a user session in the API
  language plpgsql
  strict
as
$$
declare
  is_available boolean;
  clean_username text;
begin
  clean_username = public.clean_username(username_to_check);

  RAISE WARNING 'Checking availability of the following username: %', clean_username;

  select count(id) = 0 as is_available
  into is_available
  from public.users
  where username = clean_username;

  if is_available = true then
    RAISE WARNING 'That username is available';
  else
    RAISE WARNING 'That username is NOT available';
  end if;

  return is_available;
end;
$$;

-- inserts a row into public."users" when a new row is added to auth."users"
create or replace function public.handle_new_user()
  returns trigger
  security definer
  language plpgsql
  as
$$
declare
  provider_name text;
  user_name     text;
  name          text;
  username_is_available boolean;
begin
  raise warning 'Creating a new user';
  raise notice 'Identifying how the user was created';

  -- Identify how the user was created
  select raw_app_meta_data ->> 'provider'
  into provider_name
  from auth.users
  where id = new.id;

  if provider_name = 'email' then
    raise notice 'User was created via Knowii signup form';

    -- We provide a user_name field in the metadata
    select raw_user_meta_data ->> 'user_name'
    into user_name
    from auth.users
    where id = new.id;
  elsif provider_name = 'github' then
    raise notice 'User was created via Github';

    -- Github provides a user_name field in the metadata
    select raw_user_meta_data ->> 'user_name' as user_name
    into user_name
    from auth.users
    where id = new.id;
  elsif provider_name = 'google' then
    raise warning 'User was created via Google';

    -- Need to derive a username from the user's name because there is no user_name field available
    select raw_user_meta_data ->>'name' as name
    into name
    from auth.users
    where id = new.id;

    user_name = public.generate_username(name);
  else
    raise warning 'User was created via unsupported provider: %. Generating a random username', provider_name;

    -- Generate a fully random username
    user_name = public.generate_username('');
  end if;

  loop
    raise notice 'Ensuring that the username is available';

    username_is_available = public.is_username_available(user_name);

    if username_is_available = false then
      raise warning 'The chosen username is not available. Generating a random one';
      -- generate a random username
      user_name = public.generate_username(user_name);
    end if;

    exit when username_is_available = true;
  end loop;

  raise warning 'Username for the new user: %', user_name;

  insert into public.users (user_id_external, username, email)
  values (new.id, user_name, new.email);

  return new;
end;
$$;

-- trigger the function every time a user (supabase) is created
drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert
  on auth.users
  for each row
execute procedure public.handle_new_user();

-- inserts a row into public.user_profiles
create or replace function public.create_new_user_profile()
  returns trigger
  security definer
  language plpgsql
  as
$$
declare
  name       text;
  avatar_url text;
begin
  -- Fetch user metadata
  select raw_user_meta_data ->>'name' as name, raw_user_meta_data ->>'avatar_url' as avatar_url
  into name, avatar_url
  from auth.users
  where id = new.user_id_external;

  -- Create the user profile in public.user_profiles
  insert into public.user_profiles (user_id, user_id_external, name, avatar_url)
  values (new.id, new.user_id_external, name, avatar_url);

  return new;
end;
$$;

-- trigger the function every time a user is created
drop trigger if exists on_public_user_created on public.users;
create trigger on_public_user_created
  after insert on public.users
  for each row execute procedure public.create_new_user_profile();

-- update a row in public.Users when the email is updated
create or replace function public.handle_updated_user()
  returns trigger
  security definer
  language plpgsql
  as
$$
begin
  update public.users
  set email = new.email -- We only change the email, NOT existing the username
  where user_id_external = new.id::uuid;

  -- Update the avatar url just in case
  update public.user_profiles
  set avatar_url = new.raw_user_meta_data ->>'avatar_url'
  where user_id_external = new.id::uuid;

  return new;
end;
$$;

-- trigger the function every time a user is updated
drop trigger if exists on_auth_user_updated on auth.users;
create trigger on_auth_user_updated
  after update
  on auth.users
  for each row
execute procedure public.handle_updated_user();

-- delete a row from public.Users when the user is deleted
create or replace function public.handle_deleted_user()
  returns trigger
  security definer
  language plpgsql
  as
$$
begin
  -- We don't delete users, but clean their account and profile
  --delete from public.users where id = old.id::text;
  update public.users
    -- we remove the external user id (supabase) but keep the email to enable future account recovery if needed
  set user_id_external = null
  where user_id_external = new.id::uuid;

  update public.user_profiles
    -- we clear all fields we can clear
  set user_id_external  = null, -- first of all the external user id
      avatar_url        = '',
      phone             = '',
      name              = '',
      website           = '',
      twitter           = '',
      facebook          = '',
      instagram         = '',
      tiktok            = '',
      github            = '',
      bio               = '',
      location          = '',
      organization_name = '',
      organization_link = ''
  where user_id_external = new.id::uuid;

  return old;
end;
$$;

-- trigger the function every time a user is deleted
drop trigger if exists on_auth_user_deleted on auth.users;
create trigger on_auth_user_deleted
  after delete
  on auth.users
  for each row
execute procedure public.handle_deleted_user();

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
