#!/usr/bin/env bash

# This enables logging for local development
# Reference: https://supabase.com/docs/guides/getting-started/local-development#enabling-local-logging

# Template containing the logging structure
TEMPLATE_FILE=./tools/supabase-logging.template.txt

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
check_environment_variable "SUPABASE_LOGGING_GCP_PROJECT_ID"
check_environment_variable "SUPABASE_LOGGING_GCP_PROJECT_NUMBER"

echo "Environment variables configured correctly"

# Generate the logging config
# Expects different parameters
generate_logging_config() {
  local gcp_project_id="$1"
  local gcp_project_number="$2"

  if [ -f $TEMPLATE_FILE ]; then
    local result=$(cat $TEMPLATE_FILE \
      | sed "s/%GCP_PROJECT_ID%/$gcp_project_id/g" \
      | sed "s/%GCP_PROJECT_NUMBER%/$gcp_project_number/g" \
    )

    if [ ! -z "$result" ]; then
      echo "$result"
    else
      echo "Failed to generate the logging configuration"
    fi
  fi
}

echo "Generating the logging configuration"
logging_config=$(generate_logging_config "$SUPABASE_LOGGING_GCP_PROJECT_ID" "$SUPABASE_LOGGING_GCP_PROJECT_NUMBER")

# Those are all the lines in the original config.toml file that we need to replace
lines_to_replace="\[analytics\]\n\
enabled = false\n\
port = 54327\n\
vector_port = 54328\n\
# Setup BigQuery project to enable log viewer on local development stack.\n\
# See: https:\/\/logflare.app\/guides\/bigquery-setup\n\
gcp_project_id = \"\"\n\
gcp_project_number = \"\"\n\
gcp_jwt_path = \"supabase\/gcloud.json\"\n"

echo "Injecting the logging configuration"
# Doing it in two steps because sed does not like the replacement value (issue with some characters that should be escaped)
sed --in-place --null-data "s/${lines_to_replace}/%REPLACE_ME%\n/" supabase/config.toml
perl -pi -e "s|%REPLACE_ME%|${logging_config}|g" supabase/config.toml

echo "Done injecting the logging configuration"
