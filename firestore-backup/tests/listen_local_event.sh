#!/bin/bash
set -eu

export GOOGLE_APPLICATION_CREDENTIALS="./configs/gcp_serviceaccount.json"

export FUNCTION_TARGET=main_event
export FUNCTION_SIGNATURE_TYPE=cloudevent
php -S localhost:8080 vendor/bin/router.php
