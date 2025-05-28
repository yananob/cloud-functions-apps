#!/bin/bash
set -eu

./vendor/bin/phpstan analyze -c phpstan.neon

TEST_TARGET=""
if [ $# -eq 1 ];then
    TEST_TARGET=$1.php
fi

echo "Running PHPUnit $TEST_TARGET..."
./vendor/bin/phpunit --colors=auto --display-notices --display-warnings --display-errors tests/$TEST_TARGET
