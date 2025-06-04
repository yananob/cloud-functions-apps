<?php declare(strict_types=1);

// Ensure this path correctly points to index.php relative to the directory where phpunit is run.
// run_tests.sh executes phpunit from the 'firestore-backup' directory.
require_once __DIR__ . '/../index.php';

use PHPUnit\Framework\TestCase;
use Google\Cloud\Firestore\DocumentSnapshot;
// We don't need full mocks for FirestoreClient etc. for __save_csv tests, only DocumentSnapshot.

class BackupTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up any temp files created by __save_csv tests
        $tmpFiles = glob(__DIR__ . '/../tmp/firestore_backup_*');
        if ($tmpFiles === false) {
            // Handle error or log, though for tests, failing is usually fine.
            echo "Error: Failed to glob for temporary files in " . __DIR__ . "/../tmp/\n";
        } else {
            foreach ($tmpFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    // --- Tests for __save_csv ---

    public function testSaveCsvReturnsFalseForEmptyData()
    {
        // __save_csv now converts iterable to array, so empty array is the direct test.
        $this->assertFalse(__save_csv([]), "Should return false for empty data array.");
    }

    public function testSaveCsvSimpleData()
    {
        $doc1SnapshotMock = $this->createMock(DocumentSnapshot::class);
        $doc1SnapshotMock->method('data')->willReturn(['id' => 1, 'name' => 'Test 1']);

        $doc2SnapshotMock = $this->createMock(DocumentSnapshot::class);
        $doc2SnapshotMock->method('data')->willReturn(['id' => 2, 'name' => 'Test 2', 'value' => 'Val']);

        $docs = [$doc1SnapshotMock, $doc2SnapshotMock];
        $csvFilePath = __save_csv($docs);

        $this->assertNotFalse($csvFilePath, "CSV file path should not be false.");
        $this->assertFileExists($csvFilePath, "CSV file should be created.");

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);

        $header = fgetcsv($csvFile);
        sort($header); // Sort for comparison as order can vary
        $this->assertEquals(['id', 'name', 'value'], $header, "CSV headers do not match.");

        $row1 = fgetcsv($csvFile);
        $row1Data = array_combine($header, $this->reorderRow($row1, $header, ['id', 'name', 'value']));
        $this->assertEquals('1', $row1Data['id']);
        $this->assertEquals('Test 1', $row1Data['name']);
        $this->assertEquals('', $row1Data['value']); // Empty for missing field

        $row2 = fgetcsv($csvFile);
        $row2Data = array_combine($header, $this->reorderRow($row2, $header, ['id', 'name', 'value']));
        $this->assertEquals('2', $row2Data['id']);
        $this->assertEquals('Test 2', $row2Data['name']);
        $this->assertEquals('Val', $row2Data['value']);

        $this->assertFalse(fgetcsv($csvFile), "Should be no more rows.");

        fclose($csvFile);
    }

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
        $this->assertNotFalse($csvFilePath);
        $this->assertFileExists($csvFilePath);

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);
        $header = fgetcsv($csvFile);
        $row = fgetcsv($csvFile);
        fclose($csvFile);

        $this->assertIsArray($header);
        $this->assertIsArray($row);

        //Combine header and row safely
        $rowData = [];
        if(count($header) == count($row)){
            $rowData = array_combine($header, $row);
        } else {
            $this->fail("Header and row count mismatch. Header: " . implode(',', $header) . " Row: " . implode(',', $row));
        }


        $this->assertEquals('{"nestedKey":"nestedValue","numArr":[10,20]}', $rowData['complexData']);
        $this->assertEquals('true', $rowData['isProcessed']);
        $this->assertEquals('false', $rowData['needsReview']);
    }

    public function testSaveCsvHeaderGenerationFromMultipleDocsWithDifferentFields()
    {
        $doc1Mock = $this->createMock(DocumentSnapshot::class);
        $doc1Mock->method('data')->willReturn(['fieldA' => 'A1', 'commonField' => 'B1']);

        $doc2Mock = $this->createMock(DocumentSnapshot::class);
        $doc2Mock->method('data')->willReturn(['commonField' => 'B2', 'fieldC' => 'C2']);

        $csvFilePath = __save_csv([$doc1Mock, $doc2Mock]);
        $this->assertNotFalse($csvFilePath);
        $this->assertFileExists($csvFilePath);

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);
        $header = fgetcsv($csvFile);
        fclose($csvFile);

        $this->assertIsArray($header);
        sort($header);
        $this->assertEquals(['commonField', 'fieldA', 'fieldC'], $header);
    }

    public function testSaveCsvWithEmptyDocumentData()
    {
        $docMock = $this->createMock(DocumentSnapshot::class);
        $docMock->method('data')->willReturn([]); // Document exists but has no fields

        $csvFilePath = __save_csv([$docMock]);
        $this->assertNotFalse($csvFilePath, "CSV file should be created even for empty data.");
        $this->assertFileExists($csvFilePath);

        $csvFile = fopen($csvFilePath, 'r');
        $this->assertIsResource($csvFile);
        $header = fgetcsv($csvFile); // Should be empty header
        $this->assertEquals([], $header ?: []); // fgetcsv returns null for empty lines, false at EOF. [] is fine.

        $row = fgetcsv($csvFile); // Should be no data rows, or one empty row if header was empty
        $this->assertFalse($row, "Should be no data row if header was empty, or header was the only line.");

        fclose($csvFile);
    }

    /**
     * Helper function to reorder CSV row data according to a desired header order.
     * This is useful because fputcsv writes fields in the order they appear in the data array,
     * but our header is sorted for consistent testing.
     */
    private function reorderRow(array $row, array $actualHeader, array $desiredHeader): array
    {
        $reorderedRow = [];
        $rowData = array_combine($actualHeader, $row); // Map actual header to row values
        foreach ($desiredHeader as $key) {
            $reorderedRow[] = $rowData[$key] ?? '';
        }
        return $reorderedRow;
    }

    // --- Main Logic Tests (Conceptual - Require Refactoring or Advanced Mocking) ---

    /**
     * @group main-logic
     */
    public function testMainLogicDocumentBackupConceptual()
    {
        $this->markTestIncomplete(
            'This test is conceptual and requires main() to be refactored for Dependency Injection ' .
            'or usage of a mocking tool like php-mock to intercept `new` calls and static methods.'
        );

        // 1. Setup:
        //    - Mock CloudEventInterface if calling main() directly.
        //    - Mock Utils::getConfig() to return a specific test configuration for 'document' backup.
        //      Example config:
        //      $config = [
        //          "firestore" => [["backup_type" => "document", "path" => "collection/doc1"]],
        //          "storage" => ["bucket" => "test-bucket"]
        //      ];
        //    - Mock `new FirestoreClient()`:
        //        - Mock `->document('collection/doc1')` to return a mock DocumentReference.
        //        - Mock DocumentReference `->snapshot()` to return a mock DocumentSnapshot.
        //        - Mock DocumentSnapshot `->exists()` to return true.
        //        - Mock DocumentSnapshot `->data()` to return ['field' => 'value'].
        //    - Mock `new StorageClient()`:
        //        - Mock `->bucket('test-bucket')` to return a mock Bucket.
        //        - Mock Bucket `->upload()`:
        //            - Expect it to be called once.
        //            - Verify the path argument (e.g., 'YYYY-MM-DD/document_collection_doc1.csv').
        //            - Verify that the source is a stream from a valid CSV file (content from __save_csv).
        //    - Mock `new Logger()`.
        //    - Mock `date()` global function to return a fixed date for predictable filenames.

        // 2. Execution:
        //    - Call `main($mockCloudEvent);` (or the refactored equivalent).

        // 3. Assertions:
        //    - Verify all expected mock interactions (e.g., `upload` was called with correct params).
        //    - Check for any logged error messages via the Logger mock.
    }

    /**
     * @group main-logic
     */
    public function testMainLogicCollectionGroupBackupConceptual()
    {
        $this->markTestIncomplete(
            'This test is conceptual and requires main() to be refactored for Dependency Injection ' .
            'or usage of a mocking tool like php-mock to intercept `new` calls and static methods.'
        );

        // 1. Setup:
        //    - Mock Utils::getConfig() for 'collection_group' backup.
        //      $config = [
        //          "firestore" => [["backup_type" => "collection_group", "path" => "myGroup"]], // "path" is collectionId
        //          "storage" => ["bucket" => "test-bucket"]
        //      ];
        //    - Mock `new FirestoreClient()`:
        //        - Mock `->collectionGroup('myGroup')` to return a mock Query.
        //        - Mock Query `->documents()` to return an iterable of mock DocumentSnapshots.
        //          - Doc1: from 'path/to/coll1/myGroup/doc1', data ['a' => 1]
        //            - Mock DocumentSnapshot1 `->exists()` true, `->data()` {'a':1}
        //            - Mock DocumentSnapshot1 `->reference()->parent()->path()` 'path/to/coll1/myGroup'
        //          - Doc2: from 'path/to/coll2/myGroup/doc2', data ['b' => 2]
        //            - Mock DocumentSnapshot2 `->exists()` true, `->data()` {'b':2}
        //            - Mock DocumentSnapshot2 `->reference()->parent()->path()` 'path/to/coll2/myGroup'
        //    - Mock `new StorageClient()` and `->bucket()->upload()`:
        //        - Expect upload for 'YYYY-MM-DD/collection_group_myGroup/path_to_coll1_myGroup.csv'.
        //        - Expect upload for 'YYYY-MM-DD/collection_group_myGroup/path_to_coll2_myGroup.csv'.
        //    - Mock `new Logger()`.
        //    - Mock `date()`.

        // 2. Execution:
        //    - Call `main($mockCloudEvent);`

        // 3. Assertions:
        //    - Verify `upload` calls with correct paths and content.
    }

    /**
     * @group main-logic
     */
    public function testMainLogicDocumentNotFoundConceptual()
    {
        $this->markTestIncomplete(
            'This test is conceptual and requires main() to be refactored for Dependency Injection ' .
            'or usage of a mocking tool like php-mock to intercept `new` calls and static methods.'
        );
        // Similar setup to testMainLogicDocumentBackupConceptual, but:
        // - Mock DocumentSnapshot `->exists()` to return false.
        // - Assert that `Bucket::upload()` is NOT called.
        // - Assert appropriate log message (e.g., "Document not found").
    }

    /**
     * @group main-logic
     */
    public function testMainLogicEmptyCollectionGroupConceptual()
    {
        $this->markTestIncomplete(
            'This test is conceptual and requires main() to be refactored for Dependency Injection ' .
            'or usage of a mocking tool like php-mock to intercept `new` calls and static methods.'
        );
        // Similar setup to testMainLogicCollectionGroupBackupConceptual, but:
        // - Mock Query `->documents()` to return an empty iterable.
        // - Assert that `Bucket::upload()` is NOT called for any collection.
        // - Assert appropriate log message (e.g., "No documents found for CollectionGroup ID").
    }
}
?>
