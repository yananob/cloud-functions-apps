#!/bin/bash
set -eu

echo "Running PHPStan..."
./vendor/bin/phpstan analyze -c phpstan.neon

echo "Running PHPUnit..."
./vendor/bin/phpunit tests
