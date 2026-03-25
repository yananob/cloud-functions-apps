<?php declare(strict_types=1);

namespace App;

use CloudEvents\V1\CloudEventInterface;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\QuerySnapshot;
use Google\Cloud\Storage\StorageClient;
use Psr\Log\LoggerInterface;

/**
 * Firestoreのデータをバックアップする処理を規定するハンドラクラス。
 */
class FirestoreBackupHandler
{
    /**
     * @param FirestoreClient $db
     * @param StorageClient $storage
     * @param LoggerInterface $logger
     * @param array<string, mixed> $config
     */
    public function __construct(
        private FirestoreClient $db,
        private StorageClient $storage,
        private LoggerInterface $logger,
        private array $config
    ) {}

    /**
     * CloudEventを処理し、設定されたターゲットに基づいてFirestoreのデータをCSVとしてStorageにバックアップします。
     *
     * @param CloudEventInterface $event
     * @return void
     */
    public function handle(CloudEventInterface $event): void
    {
        foreach ($this->config["firestore"] as $target) {
            $this->logger->info("Processing [" . $target["path"] . "]");

            $tmpFilepath = null;
            if ($target["type"] === "collection") {
                $tmpFilepath = $this->saveToCsv($this->db->collection($target["path"])->documents());
            }

            if ($tmpFilepath === null) {
                $this->logger->warning("No data to backup for path: " . $target["path"]);
                continue;
            }

            try {
                $bucket = $this->storage->bucket($this->config["storage"]["bucket"]);
                $objectName = date('Y-m-d') . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, "_", $target["path"]) . ".csv";

                $bucket->upload(
                    fopen($tmpFilepath, 'r'),
                    [
                        "name" => $objectName,
                    ]
                );
            } finally {
                if (file_exists($tmpFilepath)) {
                    unlink($tmpFilepath);
                }
            }
        }

        $this->logger->info("Succeeded.");
    }

    /**
     * ドキュメントのリストをCSVとして一時ファイルに保存します。
     *
     * @param QuerySnapshot $documents
     * @return string
     */
    private function saveToCsv(QuerySnapshot $documents): string
    {
        $tmpDir = sys_get_temp_dir();
        $tmpfname = (string)tempnam($tmpDir, "temp.csv");
        $fp = fopen($tmpfname, "w");
        if ($fp === false) {
            throw new \RuntimeException("Failed to open temporary file for writing.");
        }

        try {
            $keys = [];
            // 最初の100ドキュメントを調べてCSVのヘッダー行を決定する
            foreach ($documents as $idx => $doc) {
                $docData = $doc->data();
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
                    $data[$key] = $docData[$key] ?? "";
                }
                fputcsv($fp, $data);
            }
        } finally {
            fclose($fp);
        }
        return $tmpfname;
    }
}
