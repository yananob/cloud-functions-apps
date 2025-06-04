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
    $bucket = $storage->bucket($config["storage"]["bucket"]); // Initialize bucket once

    foreach ($config["firestore"] as $target) {
        $backup_type = $target["backup_type"] ?? null;
        $path = $target["path"] ?? null;

        if (!$backup_type || !$path) {
            $logger->log("Skipping target due to missing 'backup_type' or 'path': " . json_encode($target));
            continue;
        }

        $logger->log("Processing backup_type: [" . $backup_type . "], path: [" . $path . "]");

        try {
            if ($backup_type === "collection_group") {
                $collectionId = $path; // Assuming 'path' is the collection ID for collection_group
                $logger->log("Processing CollectionGroup ID: [" . $collectionId . "]");

                $documentsSnapshot = $db_accessor->collectionGroup($collectionId)->documents();

                // Group documents by their actual collection path
                $collectionsData = [];
                foreach ($documentsSnapshot as $doc) {
                    if ($doc->exists()) {
                        $collectionPath = $doc->reference()->parent()->path();
                        if (!isset($collectionsData[$collectionPath])) {
                            $collectionsData[$collectionPath] = [];
                        }
                        $collectionsData[$collectionPath][] = $doc;
                    }
                }

                if (empty($collectionsData)) {
                    $logger->log("No documents found for CollectionGroup ID: [" . $collectionId . "]");
                    continue;
                }

                foreach ($collectionsData as $actualCollectionPath => $docsInCollection) {
                    $logger->log("Saving collection from group: [" . $actualCollectionPath . "], documents: " . count($docsInCollection));
                    $tmp_filepath = __save_csv($docsInCollection); // Pass the array of DocumentSnapshots

                    if ($tmp_filepath) {
                        $uploadPath = date('Y-m-d') . DIRECTORY_SEPARATOR .
                                      "collection_group_" . str_replace(DIRECTORY_SEPARATOR, "_", $collectionId) . DIRECTORY_SEPARATOR .
                                      str_replace(DIRECTORY_SEPARATOR, "_", $actualCollectionPath) . ".csv";

                        $bucket->upload(
                            fopen($tmp_filepath, 'r'),
                            ["name" => $uploadPath]
                        );
                        unlink($tmp_filepath);
                        $logger->log("Successfully backed up to: " . $uploadPath);
                    }
                }

            } elseif ($backup_type === "document") {
                $logger->log("Processing Document: [" . $path . "]");
                $documentSnapshot = $db_accessor->document($path)->snapshot();

                if ($documentSnapshot->exists()) {
                    // __save_csv expects an iterable of documents. Wrap the single snapshot in an array.
                    $tmp_filepath = __save_csv([$documentSnapshot]);

                    if ($tmp_filepath) {
                        $uploadPath = date('Y-m-d') . DIRECTORY_SEPARATOR .
                                      "document_" . str_replace(DIRECTORY_SEPARATOR, "_", $path) . ".csv";

                        $bucket->upload(
                            fopen($tmp_filepath, 'r'),
                            ["name" => $uploadPath]
                        );
                        unlink($tmp_filepath);
                        $logger->log("Successfully backed up to: " . $uploadPath);
                    }
                } else {
                    $logger->log("Document not found: [" . $path . "]");
                }
            } else {
                // This case handles the old "collection" type or any other unspecified type
                // For backward compatibility or specific handling of "collection" type if needed
                $logger->log("Processing Collection: [" . $path . "]");
                $collectionDocuments = $db_accessor->collection($path)->documents();
                // Check if collection has documents
                $docsForCsv = iterator_to_array($collectionDocuments); // Convert iterator to array to check emptiness / pass to __save_csv

                if (empty($docsForCsv)) {
                    $logger->log("Collection is empty or does not exist: [" . $path . "]");
                    continue;
                }

                $tmp_filepath = __save_csv($docsForCsv);

                if ($tmp_filepath) {
                    $uploadPath = date('Y-m-d') . DIRECTORY_SEPARATOR .
                                  "collection_" . str_replace(DIRECTORY_SEPARATOR, "_", $path) . ".csv";
                    $bucket->upload(
                        fopen($tmp_filepath, 'r'),
                        ["name" => $uploadPath]
                    );
                    unlink($tmp_filepath);
                    $logger->log("Successfully backed up to: " . $uploadPath);
                }
            }
        } catch (Exception $e) {
            $logger->log("Error processing target (path: " . $path . ", type: " . $backup_type . "): " . $e->getMessage());
        }
    }

    $logger->log("All tasks completed.");
}

/**
 * Saves an iterable of Firestore documents to a CSV file.
 *
 * @param iterable<Google\Cloud\Firestore\DocumentSnapshot> $documents An iterable of DocumentSnapshot objects.
 * @return string|false The path to the temporary CSV file, or false on failure.
 */
function __save_csv(iterable $documents): string|false
{
    // Ensure there are documents to process
    $docsArray = is_array($documents) ? $documents : iterator_to_array($documents);

    if (empty($docsArray)) {
        // No documents, perhaps log this or return early.
        // For now, return false as we can't create a CSV.
        // Consider creating an empty CSV if that's desired.
        // Logger isn't available here, so can't log.
        return false;
    }

    $tmpfname = tempnam(__DIR__ . DIRECTORY_SEPARATOR . "tmp", "firestore_backup_");
    if ($tmpfname === false) {
        // error_log("Failed to create temporary file in " . __DIR__ . DIRECTORY_SEPARATOR . "tmp");
        return false; // Failed to create temp file
    }
    $fp = fopen($tmpfname, "w");
    if ($fp === false) {
        // error_log("Failed to open temporary file for writing: " . $tmpfname);
        return false; // Failed to open temp file
    }

    try {
        $keys = [];
        // Determine CSV headers from all documents to ensure all keys are captured.
        // The original logic used first 100, which is fine, but iterating all is more robust if performance allows.
        // For potentially very large collections, the 100-doc scan is a pragmatic choice.
        // Let's stick to a limited scan for headers to avoid iterating all docs twice for large collections.
        $docsToScanForHeaders = array_slice($docsArray, 0, 100);

        foreach ($docsToScanForHeaders as $doc) {
            // $doc is already a DocumentSnapshot
            $docData = $doc->data();
            if (is_array($docData)) { // Ensure data is an array
                $keys = array_unique(array_merge($keys, array_keys($docData)));
            }
        }

        if (empty($keys) && count($docsArray) > 0) {
            // This can happen if documents exist but have no data (e.g. only subcollections).
            // Or if all documents are empty. Create a CSV with a placeholder or skip.
            // For now, let's ensure at least one key if docs exist, perhaps an ID.
            // However, $doc->data() would be empty.
            // If all docs are truly empty, fputcsv might write an empty line for headers if $keys is empty.
            // Let's ensure $keys is not completely empty if there are docs.
            // This part needs careful consideration based on desired output for empty docs.
            // For now, if keys are empty, we will write an empty header row.
        }

        fputcsv($fp, $keys); // Write header row

        // Write data rows
        foreach ($docsArray as $doc) {
            // $doc is already a DocumentSnapshot
            $docData = $doc->data();
            $data = [];
            foreach ($keys as $key) {
                // Handle various data types for CSV compatibility
                $value = $docData[$key] ?? "";
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value); // Convert arrays/objects to JSON string
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $data[$key] = $value;
            }
            fputcsv($fp, $data);
        }
    } finally {
        fclose($fp);
    }
    return $tmpfname;
}
