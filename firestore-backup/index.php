<?php declare(strict_types=1);

// Composerのオートローダーを読み込む
require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\QuerySnapshot;
use Google\Cloud\Storage\StorageClient;
use yananob\MyTools\Logger;
use yananob\MyTools\Utils;

// CloudEvent関数として 'main' 関数を登録
FunctionsFramework::cloudEvent('main', 'main');

// CloudEventを処理するメイン関数
// FirestoreのコレクションをCloud StorageにCSVファイルとしてバックアップする
function main(CloudEventInterface $event): void
{
    // ロガーを初期化
    $logger = new Logger("firestore-backup");

    // Firestoreクライアントを初期化
    $db_accessor = new FirestoreClient([
        "keyFilePath" => __DIR__ . '/configs/firebase.json' // Firebase設定ファイルのパス
    ]);
    // Storageクライアントを初期化
    $storage = new StorageClient([
        'keyFile' => json_decode(file_get_contents(__DIR__ . '/configs/gcp_serviceaccount.json'), true) // GCPサービスアカウントキーのパス
    ]);

    // 設定ファイルを読み込み
    $config = Utils::getConfig(__DIR__ . '/configs/config.json');

    // 設定ファイルで指定されたFirestoreの各ターゲットに対して処理を実行
    foreach ($config["firestore"] as $target) {
        $logger->log("Processing [" . $target["path"] . "]"); // 処理中のパスをログに出力

        $tmp_filepath = null;
        // ターゲットタイプが "collection" の場合
        if ($target["type"] === "collection") {
            // コレクションのドキュメントをCSVファイルに保存
            $tmp_filepath = __save_csv($db_accessor->collection($target["path"])->documents());
        }
        // TODO: "document" タイプの処理も追加する場合はここに記述
        // document:
        // $backup_doc = $db_accessor->document("daily-quotes-test/admin")->snapshot()->data();

        // Cloud Storageのバケットを取得
        $bucket = $storage->bucket($config["storage"]["bucket"]);
        // CSVファイルをCloud Storageにアップロード
        $bucket->upload(
            fopen($tmp_filepath, 'r'), // 一時CSVファイルを開く
            [
                // アップロード先のファイル名 (日付/Firestoreパス.csv)
                "name" => date('Y-m-d') . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, "_", $target["path"]) . ".csv",
            ]
        );
    }

    $logger->log("Succeeded."); // 成功ログ
}

// FirestoreのQuerySnapshotをCSVファイルに保存し、一時ファイル名を返す関数
function __save_csv(QuerySnapshot $documents): string
{
    // 一時ファイルを作成
    $tmpfname = tempnam(__DIR__ . DIRECTORY_SEPARATOR . "tmp", "temp.csv");
    $fp = fopen($tmpfname, "w"); // 書き込みモードで一時ファイルを開く
    try {
        $keys = [];
        // 最初の100ドキュメントを調べてCSVのヘッダー行を決定する
        foreach ($documents as $idx => $doc) {
            $docData = $doc->data();
            // 最初の100行で、項目を判断する (101行目以降はスキップ)
            if ($idx > 100) {
                break;
            }
            // ドキュメントのキーを抽出し、既存のキーとマージしてユニークなキーリストを作成
            $keys = array_unique(array_merge($keys, array_keys($docData)));
        }

        // 全ドキュメントを処理してCSVデータを作成
        foreach ($documents as $idx => $doc) {
            $docData = $doc->data();
            // 最初のドキュメントの場合、ヘッダー行をCSVに書き込む
            if ($idx === 0) {
                fputcsv($fp, $keys);
            }
            $data = [];
            // ヘッダーキーに基づいて各行のデータを作成
            foreach ($keys as $key) {
                // 対応するキーの値が存在すればそれを、なければ空文字を設定
                $data[$key] = isset($docData[$key]) ? $docData[$key] : "";
            }
            // データをCSVファイルに書き込む
            fputcsv($fp, $data);
        }
    } finally {
        // ファイルポインタを閉じる
        fclose($fp);
    }
    // 一時ファイル名を返す
    return $tmpfname;
}
