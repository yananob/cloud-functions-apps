#!/bin/bash
set -eu

# This script tests the POST functionality of the web-fetch HTTP endpoint.
# It submits a URL to be added to Pocket and Raindrop.

curl -X POST \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "url=http://example.com/testpage" \
    http://localhost:8080
echo "" # For cleaner terminal output
