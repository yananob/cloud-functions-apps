<?php declare(strict_types=1);

<?php declare(strict_types=1);

// phpunit が実行されるディレクトリからの相対パスで index.php を正しくポイントするようにします。
// run_tests.sh は 'firestore-backup' ディレクトリから phpunit を実行します。
require_once __DIR__ . '/../index.php';

use PHPUnit\Framework\TestCase;
use Google\Cloud\Firestore\DocumentSnapshot;
// __save_csv 関数のテストでは、FirestoreClientなどの完全なモックは不要で、DocumentSnapshot のモックのみが必要です。

/**
 * BackupTest クラス
 * firestore-backup スクリプトのユニットテストを提供します。
 * 主に __save_csv 関数の詳細なテストと、main 関数のロジックに関する概念的なテストを含みます。
 */
class BackupTest extends TestCase
{
    /**
     * 各テストメソッドの後に呼び出され、一時ファイルをクリーンアップします。
     */
    protected function tearDown(): void
    {
        // __save_csv テストによって作成された一時ファイルをクリーンアップします。
        $tmpFiles = glob(__DIR__ . '/../tmp/firestore_backup_*');
        if ($tmpFiles === false) {
            // エラー処理またはロギング。ただし、テストにおいては失敗することが通常許容されます。
            echo "エラー: " . __DIR__ . "/../tmp/ 内の一時ファイルのglobに失敗しました。\n";
        } else {
            foreach ($tmpFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    // --- __save_csv 関数のテスト ---

    /**
     * testSaveCsvReturnsFalseForEmptyData
     * __save_csv 関数が空のデータ配列に対して false を返すことをテストします。
     * - シナリオ: 入力データが空。
     * - 期待される結果: __save_csv は false を返す。
     * - アサーション: assertFalse。
     */
    public function testSaveCsvReturnsFalseForEmptyData()
    {
        // __save_csv はイテラブルを配列に変換するため、空配列が直接のテストケースとなります。
        $this->assertFalse(__save_csv([]), "空のデータ配列に対しては false が返されるべきです。");
    }

    /**
     * testSaveCsvSimpleData
     * __save_csv 関数が単純なデータ構造を正しくCSVに変換できることをテストします。
     * - シナリオ: いくつかのフィールドを持つ複数のドキュメント。一部のドキュメントには存在しないフィールドも含む。
     * - 期待される結果: 正しいヘッダーとデータ行を持つCSVファイルが生成される。
     * - アサーション: ファイルの存在、ヘッダーの正確性（順序不問）、各データ行の内容の正確性。
     */
    public function testSaveCsvSimpleData()
    {
        $doc1SnapshotMock = $this->createMock(DocumentSnapshot::class);
        $doc1SnapshotMock->method('data')->willReturn(['id' => 1, 'name' => 'Test 1']);

        $doc2SnapshotMock = $this->createMock(DocumentSnapshot::class);
        $doc2SnapshotMock->method('data')->willReturn(['id' => 2, 'name' => 'Test 2', 'value' => 'Val']);

        $docs = [$doc1SnapshotMock, $doc2SnapshotMock];
        $csvFilePath = __save_csv($docs);

        $this->assertNotFalse($csvFilePath, "CSVファイルパスは false であるべきではありません。");
        $this->assertFileExists($csvFilePath, "CSVファイルが作成されるべきです。");

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile, "CSVファイルは有効なリソースであるべきです。");

        $header = fgetcsv($csvFile);
        sort($header); // 順序が異なる場合があるため、比較前にソートします。
        $this->assertEquals(['id', 'name', 'value'], $header, "CSVヘッダーが一致しません。");

        $row1 = fgetcsv($csvFile);
        // ヘッダーの順序に合わせてデータを再配列してから比較します。
        $row1Data = array_combine($header, $this->reorderRow($row1, $header, ['id', 'name', 'value']));
        $this->assertEquals('1', $row1Data['id']);
        $this->assertEquals('Test 1', $row1Data['name']);
        $this->assertEquals('', $row1Data['value']); // 存在しないフィールドは空文字になることを確認します。

        $row2 = fgetcsv($csvFile);
        $row2Data = array_combine($header, $this->reorderRow($row2, $header, ['id', 'name', 'value']));
        $this->assertEquals('2', $row2Data['id']);
        $this->assertEquals('Test 2', $row2Data['name']);
        $this->assertEquals('Val', $row2Data['value']);

        $this->assertFalse(fgetcsv($csvFile), "これ以上行がないはずです。");

        fclose($csvFile);
    }

    /**
     * testSaveCsvNestedDataAndBooleans
     * __save_csv 関数がネストされたデータ（配列/オブジェクト）とブール値を正しく処理することをテストします。
     * - シナリオ: ネストされた配列/オブジェクトとブール値を含むドキュメント。
     * - 期待される結果: ネストされたデータはJSON文字列に、ブール値は 'true'/'false' 文字列に変換される。
     * - アサーション: JSON文字列とブール値文字列の正確性。
     */
    public function testSaveCsvNestedDataAndBooleans()
    {
        $docSnapshotMock = $this->createMock(DocumentSnapshot::class);
        $docSnapshotMock->method('data')->willReturn([
            'id' => 'doc1',
            'complexData' => ['nestedKey' => 'nestedValue', 'numArr' => [10, 20]],
            'isProcessed' => true,
            'needsReview' => false
        ]);

        $csvFilePath = __save_csv([$docSnapshotMock]);
        $this->assertNotFalse($csvFilePath, "CSVファイルパスは false であるべきではありません。");
        $this->assertFileExists($csvFilePath, "CSVファイルが作成されるべきです。");

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);
        $header = fgetcsv($csvFile);
        $row = fgetcsv($csvFile);
        fclose($csvFile);

        $this->assertIsArray($header, "ヘッダーは配列であるべきです。");
        $this->assertIsArray($row, "行データは配列であるべきです。");

        // ヘッダーと行を安全に結合します。
        $rowData = [];
        if(count($header) == count($row)){
            $rowData = array_combine($header, $row);
        } else {
            $this->fail("ヘッダーと行の要素数が一致しません。ヘッダー: " . implode(',', $header) . " 行: " . implode(',', $row));
        }

        $this->assertEquals('{"nestedKey":"nestedValue","numArr":[10,20]}', $rowData['complexData'], "ネストされたデータはJSON文字列に変換されるべきです。");
        $this->assertEquals('true', $rowData['isProcessed'], "ブール値(true)は 'true' 文字列に変換されるべきです。");
        $this->assertEquals('false', $rowData['needsReview'], "ブール値(false)は 'false' 文字列に変換されるべきです。");
    }

    /**
     * testSaveCsvHeaderGenerationFromMultipleDocsWithDifferentFields
     * __save_csv 関数が異なるフィールドセットを持つ複数のドキュメントからヘッダーを正しく生成することをテストします。
     * - シナリオ: 各ドキュメントが異なるフィールドを持つ。
     * - 期待される結果: 全てのドキュメントの全フィールドを含む統合されたヘッダーが生成される。
     * - アサーション: ヘッダーが全てのユニークなフィールド名を含むこと（順序不問）。
     */
    public function testSaveCsvHeaderGenerationFromMultipleDocsWithDifferentFields()
    {
        $doc1Mock = $this->createMock(DocumentSnapshot::class);
        $doc1Mock->method('data')->willReturn(['fieldA' => 'A1', 'commonField' => 'B1']);

        $doc2Mock = $this->createMock(DocumentSnapshot::class);
        $doc2Mock->method('data')->willReturn(['commonField' => 'B2', 'fieldC' => 'C2']);

        $csvFilePath = __save_csv([$doc1Mock, $doc2Mock]);
        $this->assertNotFalse($csvFilePath, "CSVファイルパスは false であるべきではありません。");
        $this->assertFileExists($csvFilePath, "CSVファイルが作成されるべきです。");

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);
        $header = fgetcsv($csvFile);
        fclose($csvFile);

        $this->assertIsArray($header, "ヘッダーは配列であるべきです。");
        sort($header); // 順序が異なる場合があるため、比較前にソートします。
        $this->assertEquals(['commonField', 'fieldA', 'fieldC'], $header, "ヘッダーには全てのユニークなフィールドが含まれるべきです。");
    }

    /**
     * testSaveCsvWithEmptyDocumentData
     * __save_csv 関数がデータフィールドを持たないドキュメント（空のドキュメント）を処理することをテストします。
     * - シナリオ: ドキュメントは存在するが、データフィールドがない (例: `data()` が `[]` を返す)。
     * - 期待される結果: CSVファイルは作成されるが、ヘッダー行は空で、データ行も存在しない。
     * - アサーション: ファイルの存在、空のヘッダー、データ行がないこと。
     */
    public function testSaveCsvWithEmptyDocumentData()
    {
        $docMock = $this->createMock(DocumentSnapshot::class);
        $docMock->method('data')->willReturn([]); // ドキュメントは存在するがフィールドがないケース

        $csvFilePath = __save_csv([$docMock]);
        $this->assertNotFalse($csvFilePath, "空データでもCSVファイルは作成されるべきです。");
        $this->assertFileExists($csvFilePath, "CSVファイルが作成されるべきです。");

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);
        $header = fgetcsv($csvFile); // ヘッダーは空になるはずです。
        // fputcsv で空配列を書き込むと空行が出力され、fgetcsv はそれを [null] として読み取ります。
        $this->assertEquals([null], $header, "空ドキュメントでヘッダー行が空の場合、fgetcsv は [null] を返すべきです。");

        $row = fgetcsv($csvFile); // ヘッダーが空の場合、データ行はないはずです。
        $this->assertFalse($row, "ヘッダーが空の場合、データ行は存在しないはずです（またはヘッダーが唯一の行）。");

        fclose($csvFile);
    }

    /**
     * CSV行データを目的のヘッダー順序に従って並べ替えるヘルパー関数。
     * fputcsv はデータ配列のフィールド順に書き込むため、テストで一貫性を保つためにヘッダーをソートする場合に役立ちます。
     * @param array $row 実際のCSV行データ配列。
     * @param array $actualHeader CSVから読み取った実際のヘッダー配列。
     * @param array $desiredHeader テストで期待するフィールド順のヘッダー配列。
     * @return array 目的の順序に並べ替えられた行データ配列。
     */
    private function reorderRow(array $row, array $actualHeader, array $desiredHeader): array
    {
        $reorderedRow = [];
        // 実際のヘッダーと行の値をマッピングします。
        $rowData = array_combine($actualHeader, $row);
        // 期待するヘッダーの順序で値を取得します。
        foreach ($desiredHeader as $key) {
            $reorderedRow[] = $rowData[$key] ?? '';
        }
        return $reorderedRow;
    }

    // --- main関数のロジックテスト (概念的 - リファクタリングまたは高度なモックが必要) ---

    /**
     * @group main-logic
     * testMainLogicDocumentBackupConceptual
     * main関数のドキュメントバックアップロジックに関する概念的なテスト。
     * - 注意: このテストは概念的なものであり、main()関数が依存性注入のためにリファクタリングされるか、
     *         php-mockのようなモックツールを使用して `new`呼び出しや静的メソッドをインターセプトする必要がある。
     * - 検証対象: 'document'タイプのバックアップ設定が与えられた場合、main関数が以下の動作をすること。
     *   - FirestoreClientとStorageClientを正しく使用する。
     *   - 正しいパスでドキュメントを取得する。
     *   - __save_csvを呼び出す。
     *   - 正しい命名規則でGCSにファイルをアップロードする。
     */
    public function testMainLogicDocumentBackupConceptual()
    {
        $this->markTestIncomplete(
            'このテストは概念的なものであり、main()が依存性注入のためにリファクタリングされるか、' .
            'php-mockのようなモックツールを使用して`new`呼び出しや静的メソッドをインターセプトする必要があります。'
        );

        // 1. セットアップ:
        //    - main()を直接呼び出す場合はCloudEventInterfaceをモックする。
        //    - Utils::getConfig()をモックし、'document'バックアップ用の特定のテスト設定を返すようにする。
        //      設定例:
        //      $config = [
        //          "firestore" => [["backup_type" => "document", "path" => "collection/doc1"]],
        //          "storage" => ["bucket" => "test-bucket"]
        //      ];
        //    - `new FirestoreClient()`をモックする:
        //        - `->document('collection/doc1')`がモックDocumentReferenceを返すようにする。
        //        - DocumentReference `->snapshot()`がモックDocumentSnapshotを返すようにする。
        //        - DocumentSnapshot `->exists()`がtrueを返すようにする。
        //        - DocumentSnapshot `->data()`が['field' => 'value']を返すようにする。
        //    - `new StorageClient()`をモックする:
        //        - `->bucket('test-bucket')`がモックBucketを返すようにする。
        //        - Bucket `->upload()`をモックする:
        //            - 一度呼び出されることを期待する。
        //            - path引数（例: 'YYYY-MM-DD/document_collection_doc1.csv'）を検証する。
        //            - sourceが有効なCSVファイル（__save_csvからのコンテンツ）からのストリームであることを検証する。
        //    - `new Logger()`をモックする。
        //    - グローバル関数`date()`をモックし、予測可能なファイル名のために固定日付を返すようにする。

        // 2. 実行:
        //    - `main($mockCloudEvent);`（またはリファクタリングされた同等のもの）を呼び出す。

        // 3. アサーション:
        //    - 期待される全てのモックインタラクション（例: `upload`が正しいパラメータで呼び出されたこと）を検証する。
        //    - Loggerモック経由でログに記録されたエラーメッセージを確認する。
    }

    /**
     * @group main-logic
     * testMainLogicCollectionGroupBackupConceptual
     * main関数のコレクション グループ バックアップ ロジックに関する概念的なテスト。
     * - 注意: 上記と同様、このテストは概念的であり、リファクタリングまたは高度なモックツールが必要。
     * - 検証対象: 'collection_group'タイプのバックアップ設定が与えられた場合、main関数が以下の動作をすること。
     *   - collectionGroup()を正しいコレクションIDで呼び出す。
     *   - 結果のドキュメントを実際のコレクションパスでグループ化する。
     *   - グループ化されたコレクションごとに__save_csvを呼び出す。
     *   - 正しい命名規則で各CSVファイルをGCSにアップロードする。
     */
    public function testMainLogicCollectionGroupBackupConceptual()
    {
        $this->markTestIncomplete(
            'このテストは概念的なものであり、main()が依存性注入のためにリファクタリングされるか、' .
            'php-mockのようなモックツールを使用して`new`呼び出しや静的メソッドをインターセプトする必要があります。'
        );

        // 1. セットアップ:
        //    - 'collection_group'バックアップ用にUtils::getConfig()をモックする。
        //      $config = [
        //          "firestore" => [["backup_type" => "collection_group", "path" => "myGroup"]], // "path" はコレクションID
        //          "storage" => ["bucket" => "test-bucket"]
        //      ];
        //    - `new FirestoreClient()`をモックする:
        //        - `->collectionGroup('myGroup')`がモックQueryを返すようにする。
        //        - Query `->documents()`がモックDocumentSnapshotのイテラブルを返すようにする。
        //          - Doc1: 'path/to/coll1/myGroup/doc1' から、データ ['a' => 1]
        //            - Mock DocumentSnapshot1 `->exists()` true, `->data()` {'a':1}
        //            - Mock DocumentSnapshot1 `->reference()->parent()->path()` 'path/to/coll1/myGroup'
        //          - Doc2: 'path/to/coll2/myGroup/doc2' から、データ ['b' => 2]
        //            - Mock DocumentSnapshot2 `->exists()` true, `->data()` {'b':2}
        //            - Mock DocumentSnapshot2 `->reference()->parent()->path()` 'path/to/coll2/myGroup'
        //    - `new StorageClient()` と `->bucket()->upload()`をモックする:
        //        - 'YYYY-MM-DD/collection_group_myGroup/path_to_coll1_myGroup.csv' のアップロードを期待する。
        //        - 'YYYY-MM-DD/collection_group_myGroup/path_to_coll2_myGroup.csv' のアップロードを期待する。
        //    - `new Logger()`をモックする。
        //    - `date()`をモックする。

        // 2. 実行:
        //    - `main($mockCloudEvent);`を呼び出す。

        // 3. アサーション:
        //    - 正しいパスとコンテンツで`upload`が呼び出されたことを検証する。
    }

    /**
     * @group main-logic
     * testMainLogicDocumentNotFoundConceptual
     * main関数でドキュメントが見つからない場合の処理に関する概念的なテスト。
     * - 注意: 上記と同様、このテストは概念的。
     * - 検証対象: 'document'タイプのバックアップで指定されたドキュメントが存在しない場合、
     *   - Bucket::upload() が呼び出されないこと。
     *   - 適切なログメッセージ（例: "Document not found"）が記録されること。
     */
    public function testMainLogicDocumentNotFoundConceptual()
    {
        $this->markTestIncomplete(
            'このテストは概念的なものであり、main()が依存性注入のためにリファクタリングされるか、' .
            'php-mockのようなモックツールを使用して`new`呼び出しや静的メソッドをインターセプトする必要があります。'
        );
        // testMainLogicDocumentBackupConceptual と同様のセットアップだが、以下が異なる:
        // - DocumentSnapshot `->exists()` が false を返すようにモックする。
        // - `Bucket::upload()` が呼び出されないことをアサートする。
        // - 適切なログメッセージ（例: "Document not found"）をアサートする。
    }

    /**
     * @group main-logic
     * testMainLogicEmptyCollectionGroupConceptual
     * main関数でコレクション グループが空の場合の処理に関する概念的なテスト。
     * - 注意: 上記と同様、このテストは概念的。
     * - 検証対象: 'collection_group'タイプのバックアップでクエリ結果が空の場合、
     *   - Bucket::upload() がどのコレクションに対しても呼び出されないこと。
     *   - 適切なログメッセージ（例: "No documents found for CollectionGroup ID"）が記録されること。
     */
    public function testMainLogicEmptyCollectionGroupConceptual()
    {
        $this->markTestIncomplete(
            'このテストは概念的なものであり、main()が依存性注入のためにリファクタリングされるか、' .
            'php-mockのようなモックツールを使用して`new`呼び出しや静的メソッドをインターセプトする必要があります。'
        );
        // testMainLogicCollectionGroupBackupConceptual と同様のセットアップだが、以下が異なる:
        // - Query `->documents()` が空のイテラブルを返すようにモックする。
        // - `Bucket::upload()` がどのコレクションに対しても呼び出されないことをアサートする。
        // - 適切なログメッセージ（例: "No documents found for CollectionGroup ID"）をアサートする。
    }
}
?>
