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
        "keyFilePath" => __DIR__ . '/configs/firebase.json'
    ]);
    $storage = new StorageClient([
        'keyFile' => json_decode(file_get_contents(__DIR__ . '/configs/gcp_serviceaccount.json'), true)
    ]);

    $config = Utils::getConfig(__DIR__ . '/configs/config.json');

    foreach ($config["firestore"] as $target) {
        $logger->log("Processing [" . $target["path"] . "]");

        $tmp_filepath = null;
        if ($target["type"] === "collection") {
            $tmp_filepath = __save_csv($db_accessor->collection($target["path"])->documents());
        }
        // document:
        // $backup_doc = $db_accessor->document("daily-quotes-test/admin")->snapshot()->data();

        $bucket = $storage->bucket($config["storage"]["bucket"]);
        $bucket->upload(
            fopen($tmp_filepath, 'r'),
            [
                "name" => date('Y-m-d') . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, "_", $target["path"]) . ".csv",
            ]
        );
    }

    $logger->log("Succeeded.");
}

function __save_csv(QuerySnapshot $documents): string
{
    $tmpfname = tempnam(__DIR__ . DIRECTORY_SEPARATOR . "tmp", "temp.csv");
    $fp = fopen($tmpfname, "w");
    try {
        $keys = [];
        foreach ($documents as $idx => $doc) {
            $docData = $doc->data();
            // 最初の100行で、項目を判断する
            if ($idx > 100) {
                break;
            }
            $keys = array_unique(array_merge($keys, array_keys($docData)));
        }

        foreach ($documents as $idx => $doc) {
            $docData = $doc->data();
            if ($idx === 0) {
                fputcsv($fp, $keys);
            }
            $data = [];
            foreach ($keys as $key) {
                $data[$key] = isset($docData[$key]) ? $docData[$key] : "";
            }
            fputcsv($fp, $data);
        }
    } finally {
        fclose($fp);
    }
    return $tmpfname;
}
