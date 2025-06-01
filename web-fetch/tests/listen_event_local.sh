#!/bin/bash
set -eu

export FUNCTION_TARGET=main_event
export FUNCTION_SIGNATURE_TYPE=cloudevent
php -S localhost:8080 vendor/bin/router.php
