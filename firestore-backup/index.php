<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\QuerySnapshot;
use Google\Cloud\Storage\StorageClient;
use yananob\MyTools\Logger;
use yananob\MyTools\Utils;

FunctionsFramework::cloudEvent('main', 'main');

function main(CloudEventInterface $event): void
{
    $logger = new Logger("firestore-backup");

    $db_accessor = new FirestoreClient([
        "keyFilePath" => __DIR__ . '/configs/firebase.json' // Firebase設定ファイルのパス
    ]);
    $storage = new StorageClient([
        'keyFile' => json_decode(file_get_contents(__DIR__ . '/configs/gcp_serviceaccount.json'), true) // GCPサービスアカウントキーのパス
    ]);

    $config = Utils::getConfig(__DIR__ . '/configs/config.json');
    // ストレージバケットを一度初期化
    $bucket = $storage->bucket($config["storage"]["bucket"]);
    // 日付プレフィックスを生成 (YYYY-MM-DD)
    $date_prefix = date('Y-m-d');

    // 設定ファイル内の各Firestoreターゲットに対してループ処理
    foreach ($config["firestore"] as $target) {
        // backup_type と path を取得。未設定の場合はnull
        $backup_type = $target["backup_type"] ?? null;
        $path = $target["path"] ?? null;

        // backup_type または path がない場合は、このターゲットの処理をスキップ
        if (!$backup_type || !$path) {
            $logger->log("Skipping target due to missing 'backup_type' or 'path': " . json_encode($target));
            continue;
        }

        $logger->log("Processing backup_type: [" . $backup_type . "], path: [" . $path . "]");

        try {
            // backup_type に基づいて適切なバックアップ関数を呼び出し
            if ($backup_type === "collection_group") {
                __backup_collection_group($db_accessor, $bucket, $path, $logger, $date_prefix);
            } elseif ($backup_type === "document") {
                __backup_document($db_accessor, $bucket, $path, $logger, $date_prefix);
            } else {
                // フォールバックとして単一コレクションのバックアップ処理を実行
                __backup_single_collection($db_accessor, $bucket, $path, $logger, $date_prefix);
            }
        } catch (Exception $e) {
            // エラーハンドリング: ターゲット処理中に例外が発生した場合のログ
            $logger->log("Error processing target (path: " . $path . ", type: " . $backup_type . "): " . $e->getMessage());
        }
    }

    $logger->log("All tasks completed."); // 全てのターゲット処理完了のログ
}

/**
 * 指定されたコレクションID (collection_group) に基づいてバックアップを実行する。
 * path パラメータはコレクションIDとして解釈される。
 */
function __backup_collection_group(
    \Google\Cloud\Firestore\FirestoreClient $db_accessor,
    \Google\Cloud\Storage\Bucket $bucket,
    string $collection_id_from_path, // 設定ファイルの path をコレクションIDとして使用
    \yananob\MyTools\Logger $logger,
    string $date_prefix
): void {
    $logger->log("Processing CollectionGroup ID: [" . $collection_id_from_path . "]");

    // 指定されたコレクションIDを持つ全てのコレクションからドキュメントを取得
    $documentsSnapshot = $db_accessor->collectionGroup($collection_id_from_path)->documents();

    // ドキュメントを実際のコレクションパスごとにグループ化
    $collectionsData = [];
    foreach ($documentsSnapshot as $doc) {
        if ($doc->exists()) { // ドキュメントが存在する場合のみ処理
            $collectionPath = $doc->reference()->parent()->path(); // ドキュメントの親（コレクション）のパスを取得
            if (!isset($collectionsData[$collectionPath])) {
                $collectionsData[$collectionPath] = [];
            }
            $collectionsData[$collectionPath][] = $doc;
        }
    }

    // グループ化されたデータがない場合（対象ドキュメントが0件だった場合）
    if (empty($collectionsData)) {
        $logger->log("No documents found for CollectionGroup ID: [" . $collection_id_from_path . "]");
        return; // このコレクションIDの処理は終了
    }

    // 各コレクションパスごとにCSVファイルを作成してアップロード
    foreach ($collectionsData as $actualCollectionPath => $docsInCollection) {
        $logger->log("Saving collection from group: [" . $actualCollectionPath . "], documents: " . count($docsInCollection));
        // ドキュメントの配列を __save_csv 関数に渡してCSVファイルを作成
        $tmp_filepath = __save_csv($docsInCollection);

        if ($tmp_filepath) { // CSVファイルが正常に作成された場合
            // アップロードパスの命名規則: YYYY-MM-DD/collection_group_設定されたID/実際のコレクションパス.csv
            $uploadPath = $date_prefix . DIRECTORY_SEPARATOR .
                          "collection_group_" . str_replace(DIRECTORY_SEPARATOR, "_", $collection_id_from_path) . DIRECTORY_SEPARATOR .
                          str_replace(DIRECTORY_SEPARATOR, "_", $actualCollectionPath) . ".csv";

            $bucket->upload(
                fopen($tmp_filepath, 'r'),
                ["name" => $uploadPath]
            );
            unlink($tmp_filepath); // 一時ファイルを削除
            $logger->log("Successfully backed up to: " . $uploadPath);
        }
    }
}

/**
 * 指定されたドキュメントパスに基づいてバックアップを実行する。
 */
function __backup_document(
    \Google\Cloud\Firestore\FirestoreClient $db_accessor,
    \Google\Cloud\Storage\Bucket $bucket,
    string $document_path,
    \yananob\MyTools\Logger $logger,
    string $date_prefix
): void {
    $logger->log("Processing Document: [" . $document_path . "]");
    // 指定されたパスのドキュメントスナップショットを取得
    $documentSnapshot = $db_accessor->document($document_path)->snapshot();

    if ($documentSnapshot->exists()) { // ドキュメントが存在する場合
        // __save_csv はドキュメントのイテラブルを期待するため、単一スナップショットを配列でラップ
        $tmp_filepath = __save_csv([$documentSnapshot]);

        if ($tmp_filepath) { // CSVファイルが正常に作成された場合
            // アップロードパスの命名規則: YYYY-MM-DD/document_ドキュメントパス.csv
            $uploadPath = $date_prefix . DIRECTORY_SEPARATOR .
                          "document_" . str_replace(DIRECTORY_SEPARATOR, "_", $document_path) . ".csv";

            $bucket->upload(
                fopen($tmp_filepath, 'r'),
                ["name" => $uploadPath]
            );
            unlink($tmp_filepath); // 一時ファイルを削除
            $logger->log("Successfully backed up to: " . $uploadPath);
        }
    } else {
        // ドキュメントが存在しない場合のログ
        $logger->log("Document not found: [" . $document_path . "]");
    }
}

/**
 * 指定されたコレクションパス (単一コレクション) に基づいてバックアップを実行する。
 * これは、backup_type が 'collection_group' や 'document' 以外の場合のフォールバック処理。
 */
function __backup_single_collection(
    \Google\Cloud\Firestore\FirestoreClient $db_accessor,
    \Google\Cloud\Storage\Bucket $bucket,
    string $collection_path, // 設定ファイルの path をコレクションパスとして使用
    \yananob\MyTools\Logger $logger,
    string $date_prefix
): void {
    $logger->log("Processing Collection: [" . $collection_path . "]");
    $collectionDocuments = $db_accessor->collection($collection_path)->documents();
    // コレクションにドキュメントがあるか確認するため、イテレータを配列に変換
    $docsForCsv = iterator_to_array($collectionDocuments);

    // ドキュメントが空の場合はスキップ
    if (empty($docsForCsv)) {
        $logger->log("Collection is empty or does not exist: [" . $collection_path . "]");
        return; // このコレクションの処理は終了
    }

    $tmp_filepath = __save_csv($docsForCsv);

    if ($tmp_filepath) {
         // アップロードパスの命名規則: YYYY-MM-DD/collection_コレクションパス.csv
        $uploadPath = $date_prefix . DIRECTORY_SEPARATOR .
                      "collection_" . str_replace(DIRECTORY_SEPARATOR, "_", $collection_path) . ".csv";
        $bucket->upload(
            fopen($tmp_filepath, 'r'),
            ["name" => $uploadPath]
        );
        unlink($tmp_filepath); // 一時ファイルを削除
        $logger->log("Successfully backed up to: " . $uploadPath);
    }
}

/**
 * FirestoreドキュメントのイテラブルをCSVファイルに保存する。
 *
 * @param iterable<Google\Cloud\Firestore\DocumentSnapshot> $documents DocumentSnapshotオブジェクトのイテラブル。
 * @return string|false 一時CSVファイルのパス。失敗した場合はfalse。
 */
function __save_csv(iterable $documents): string|false
{
    // 処理するドキュメントがあることを確認
    // $documents が配列でなければ iterator_to_array で配列に変換
    $docsArray = is_array($documents) ? $documents : iterator_to_array($documents);

    // ドキュメントが空の場合、CSVは作成できないためfalseを返す
    if (empty($docsArray)) {
        // Loggerはここでは利用できないため、ログは出力しない
        return false;
    }

    // 一時ファイルを作成 (tmpディレクトリ内に `firestore_backup_` プレフィックスで作成)
    $tmpfname = tempnam(__DIR__ . DIRECTORY_SEPARATOR . "tmp", "firestore_backup_");
    if ($tmpfname === false) { // 一時ファイルの作成に失敗した場合
        return false;
    }
    $fp = fopen($tmpfname, "w"); // 書き込みモードで一時ファイルを開く
    if ($fp === false) { // ファイルオープンに失敗した場合
        return false;
    }

    try {
        $keys = []; // CSVヘッダー用のキーを格納する配列
        // CSVヘッダー行を決定するため、最初の100ドキュメントをスキャン
        // (大規模コレクションの場合、全ドキュメントのスキャンはパフォーマンスに影響するため限定的にする)
        $docsToScanForHeaders = array_slice($docsArray, 0, 100);

        foreach ($docsToScanForHeaders as $doc) {
            // $doc は DocumentSnapshot オブジェクト
            $docData = $doc->data(); // ドキュメントのデータを取得
            if (is_array($docData)) { // データが配列であることを確認
                // 既存のキーと新しいキーをマージし、重複を排除してヘッダーキーを蓄積
                $keys = array_unique(array_merge($keys, array_keys($docData)));
            }
        }

        // ドキュメントは存在するが、中身が空のフィールドばかりでキーが一つも見つからない場合への対応 (現状はそのまま進行)
        // 例えば、全ドキュメントが空データ {} の場合、$keys は空になる。
        // その場合、fputcsv は空のヘッダー行を書き込む。

        fputcsv($fp, $keys); // ヘッダー行をCSVファイルに書き込む

        // $keys が空の場合、意味のあるデータ行は書き込めないため、データ行の書き込み処理をスキップする。
        // これにより、ヘッダーが空の場合に不要な空行がデータとして書き込まれるのを防ぐ。
        if (!empty($keys)) {
            // データ行を書き込む
            foreach ($docsArray as $doc) {
                // $doc は DocumentSnapshot オブジェクト
                $docData = $doc->data();
                $data = []; // この行のデータを格納する配列
                foreach ($keys as $key) {
                    // CSV互換性のために様々なデータ型を処理
                    $value = $docData[$key] ?? ""; // キーが存在しない場合は空文字
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value); // 配列やオブジェクトはJSON文字列に変換
                    } elseif (is_bool($value)) {
                        $value = $value ? 'true' : 'false'; // boolean値は 'true'/'false' 文字列に変換
                    }
                    $data[$key] = $value;
                }
                fputcsv($fp, $data); // データ行をCSVファイルに書き込む
            }
        }
    } finally {
        fclose($fp); // ファイルポインタを必ず閉じる
    }
    return $tmpfname; // 作成された一時ファイルのパスを返す
}
