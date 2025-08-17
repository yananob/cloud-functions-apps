#!/bin/bash
set -eu

# 以下ファイルがない状態にして、スクリプトを実行することで、googleapi_token.jsonを作成する
rm -f ./credentials/googleapi_token.json
php ./create_refresh_token.php
