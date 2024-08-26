#!/usr/bin/env sh

# This is necessary to have full control over the ports (stability)
# And to avoid this known issue: https://github.com/docker/for-win/issues/9272

# API port
sed --in-place 's/\(^port = 54321\).*/port = 12345/' supabase/config.toml

# DB port
sed --in-place 's/\(^port = 54322\).*/port = 12346/' supabase/config.toml

# Studio
sed --in-place 's/\(^port = 54323\).*/port = 12347/' supabase/config.toml

# Email testing
sed --in-place 's/\(^port = 54324\).*/port = 12348/' supabase/config.toml
sed --in-place 's/\(^smtp_port = 54325\).*/smtp_port = 12349/' supabase/config.toml
sed --in-place 's/\(^pop3_port = 54326\).*/pop3_port = 12350/' supabase/config.toml

# Site URL and redirect URLs
sed --in-place 's,site_url = "http://localhost:3000".*,site_url = "http://localhost:4200",g' supabase/config.toml
sed --in-place 's,additional_redirect_urls = \["https://localhost:3000"\].*,additional_redirect_urls = \["https://localhost:4200"\],g' supabase/config.toml
