<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

Google\CloudFunctions\FunctionsFramework::cloudEvent('main', 'main');
function main(CloudEvents\V1\CloudEventInterface $event): void
{
    $logger = new yananob\MyTools\Logger("firestore-backup");

    $db_accessor = new Google\Cloud\Firestore\FirestoreClient([
        "keyFilePath" => __DIR__ . '/configs/firebase.json'
    ]);
    $storage = new Google\Cloud\Storage\StorageClient(['keyFile' => json_decode(file_get_contents(__DIR__ . '/configs/gcp_serviceaccount.json'), true)
    ]);

    $config = \yananob\MyTools\Utils::getConfig(__DIR__ . '/configs/config.json');

    foreach ($config["firestore"] as $target) {
        $logger->log("Processing [" . $target["path"] . "]");

        $tmp_filepath = null;
        if ($target["type"] === "collection") {
            $tmp_filepath = __save_csv($target["columns"], $db_accessor->collection($target["path"])->orderBy('no')->documents());
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

function __save_csv(array $columns, Google\Cloud\Firestore\QuerySnapshot $documents): string
{
    $tmpfname = tempnam(__DIR__ . DIRECTORY_SEPARATOR . "tmp", "temp.csv");
    $fp = fopen($tmpfname, "w");
    try {
        fputcsv($fp, $columns);
        foreach ($documents as $doc) {
            $data = [];
            foreach ($columns as $column) {
                $data[$column] = $doc->data()[$column];
            }
            fputcsv($fp, $data);
        }
    } finally {
        fclose($fp);
    }
    return $tmpfname;
}