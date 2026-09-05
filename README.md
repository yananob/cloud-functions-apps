# Cloud Function Apps

このリポジトリには、Google Cloud Functions (PHP 8.2 / 8.3) 上で動作する複数のアプリケーションが含まれています。

## コーディングルール
- コメントおよびドキュメントは日本語で記述します。
- コードは `declare(strict_types=1);` を指定し、PSR-12 コードスタイルに準拠します。

## 含まれる Cloud Functions 一覧

### 1. `firestore-backup`
- **概要**: Firestore コレクションのデータを定期的に CSV 形式に変換し、Google Cloud Storage (GCS) バケットへバックアップします。
- **トリガー**: CloudEvent (Pub/Sub イベントなど)
- **主要構成ファイル**:
  - `configs/config.json`: バックアップ対象の Firestore パスおよび保存先 GCS バケットの設定
  - `configs/firebase.json`: Firestore 接続キー情報
  - `configs/gcp_serviceaccount.json`: Service Account キー情報

### 2. `time-message`
- **概要**: 設定された時刻パターン（曜日、時間等）に一致した場合、LINE Messaging API を使用してプッシュメッセージを送信します。
- **トリガー**: CloudEvent (Cloud Scheduler 等からの Pub/Sub イベント)
- **主要構成ファイル**:
  - `configs/config.json`: 送信タイミング、ボット、送信先、メッセージ本文の設定
  - `configs/line.json`: LINE Bot アクセストークン・シークレット設定

### 3. `web-fetch`
- **概要**: 指定された URL を Raindrop.io ブックマークサービスに追加します。HTTP フォームからの追加および CloudEvent（定期スクレイピング/収集等）の両方に対応しています。
- **トリガー**: HTTP トリガー (`main_http`) / CloudEvent (`main_event`)
- **主要構成ファイル**:
  - `configs/config.json`: イベント実行時の対象 URL とタイミングの設定
  - `configs/raindrop.json`: Raindrop.io アクセストークン設定
  - `templates/index.html`: Web フォーム表示用テンプレート

### 4. `webhook-receive`
- **概要**: LINE Webhook からのリクエストを受信・解析し、送信元のタイプ（ユーザー、グループ、ルーム）に応じたレスポンスメッセージを送信します。
- **トリガー**: HTTP トリガー (`main`)
- **主要構成ファイル**:
  - `configs/line.json`: LINE Bot 設定

---

## ディレクトリ構成と共通コンポーネント

- `_myapps-common/`: デプロイスクリプトや GitHub Actions ワークフロー等の共通ユーティリティをまとめた Git サブモジュールです。
- `deploy_php_event.sh`: イベント駆動型 Cloud Functions のデプロイスクリプト（`_myapps-common/deploy/deploy_php_event.sh` へのシンボリックリンク）。
- `deploy_php_http.sh`: HTTP トリガー型 Cloud Functions のデプロイスクリプト（`_myapps-common/deploy/deploy_php_http.sh` へのシンボリックリンク）。

---

## 開発とテスト

### 依存関係のセットアップ
各 Cloud Function ディレクトリ配下で Composer を使用して依存関係をインストール・更新します。

```bash
cd <function-directory>
composer install
```

### 静的解析とテストの実行
各アプリケーションの `tests/run_tests.sh` スクリプトを実行することで、PHPStan による静的解析と PHPUnit による単体テストが実行されます。

```bash
cd <function-directory>
./tests/run_tests.sh
```

---

## デプロイ手順

`deploy_php_http.sh` または `deploy_php_event.sh` を使用してローカル環境からデプロイを行うことができます。

```bash
# HTTP 関数デプロイ例 (web-fetch ディレクトリ内)
cd web-fetch
../deploy_php_http.sh --name web-fetch --entry-point main_http

# イベント関数デプロイ例 (time-message ディレクトリ内)
cd time-message
../deploy_php_event.sh --name time-message --entry-point main --trigger-topic cron-topic
```
