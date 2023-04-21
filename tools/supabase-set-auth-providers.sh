#!/usr/bin/env bash

# This configures the different auth providers for local development
# Reference: https://github.com/orgs/supabase/discussions/2818

# Currently enabled providers:
# google
# github

# Later:
# twitter
# apple
# linkedin
# discord
# slack

# Template containing the structure for adding a provider
TEMPLATE_FILE=./tools/supabase-auth-provider.template.txt

# Function that checks if an environment variable exists
check_environment_variable() {
  local env_var_name=$1
  echo "Checking for presence of $env_var_name environment variable...."

  if [ -z $(eval echo \"\$$env_var_name\") ];
  then
    >&2 echo "Failure - $env_var_name environment variable not set.  Please set this environment variable and try again.";
    exit 1;
  #else
  #  echo "Its there: $(eval echo \"\$$env_var_name\")"
  fi
}

echo "Checking environment variables"

# Make sure the variables have been configured
check_environment_variable "AUTH_PROVIDER_GOOGLE_CLIENT_ID"
check_environment_variable "AUTH_PROVIDER_GOOGLE_SECRET"
check_environment_variable "AUTH_PROVIDER_GOOGLE_REDIRECT_URI"

check_environment_variable "AUTH_PROVIDER_GITHUB_CLIENT_ID"
check_environment_variable "AUTH_PROVIDER_GITHUB_SECRET"
check_environment_variable "AUTH_PROVIDER_GITHUB_REDIRECT_URI"

echo "Environment variables configured correctly"

# Generate the auth provider config for the given provider
# Expects different parameters
generate_auth_providers_config() {
  local provider_name="$1"
  local provider_client_id="$2"
  local provider_secret="$3"
  local provider_redirect_uri="$4"

  if [ -f $TEMPLATE_FILE ]; then
    local result=$(cat $TEMPLATE_FILE \
      | sed "s/%PROVIDER_NAME%/$provider_name/g" \
      | sed "s/%PROVIDER_CLIENT_ID%/$provider_client_id/g" \
      | sed "s/%PROVIDER_SECRET%/$provider_secret/g" \
      | sed "s*%PROVIDER_REDIRECT_URI%*$provider_redirect_uri*g" \
    )

    if [ ! -z "$result" ]; then
      echo "$result"
    else
      echo "Failed to generate the auth providers configuration"
    fi
  fi
}

echo "Generating the auth providers configuration for Google"
google_auth_provider_config=$(generate_auth_providers_config "google" "$AUTH_PROVIDER_GOOGLE_CLIENT_ID" "$AUTH_PROVIDER_GOOGLE_SECRET" "$AUTH_PROVIDER_GOOGLE_REDIRECT_URI")
echo "Generating the auth providers configuration for Github"
github_auth_provider_config=$(generate_auth_providers_config "github" "$AUTH_PROVIDER_GITHUB_CLIENT_ID" "$AUTH_PROVIDER_GITHUB_SECRET" "$AUTH_PROVIDER_GITHUB_REDIRECT_URI")

auth_providers_config="${google_auth_provider_config}\n\n${github_auth_provider_config}\n"

# Those are all the lines in the original config.toml file that we need to replace
lines_to_replace="\[auth\.external\.apple\]\nenabled = false\n\
client_id = \"\"\nsecret = \"\"\n\
# Overrides the default auth redirectUrl.\n\
redirect_uri = \"\"\n\
# Overrides the default auth provider URL. Used to support self-hosted gitlab, single-tenant Azure,\n\
# or any other third-party OIDC providers.\n\
url = \"\"\n"

echo "Injecting the auth providers configuration"
# Doing it in two steps because sed does not like the replacement value (issue with some characters that should be escaped)
sed --in-place --null-data "s/${lines_to_replace}/%REPLACE_ME%/" supabase/config.toml
perl -pi -e "s|%REPLACE_ME%|${auth_providers_config}|g" supabase/config.toml

echo "Done injecting the auth providers configuration"
