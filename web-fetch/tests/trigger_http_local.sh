#!/bin/bash
set -eu

curl -X POST \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "url=http://example.com/testpage" \
    http://localhost:8080
echo "" # Add a newline for cleaner output in terminal
