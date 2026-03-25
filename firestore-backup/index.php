<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Storage\StorageClient;
use yananob\MyTools\Logger;
use yananob\MyTools\Utils;
use MyApp\FirestoreBackupHandler;

FunctionsFramework::cloudEvent('main_event', 'main_event');

/**
 * CloudEventを処理するメイン関数
 * 実際のバックアップ処理は FirestoreBackupHandler クラスに委譲します。
 *
 * @param CloudEventInterface $event
 * @return void
 */
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("firestore-backup");

    $db = new FirestoreClient([
        "keyFilePath" => __DIR__ . '/configs/firebase.json'
    ]);

    $storage = new StorageClient([
        'keyFile' => json_decode(file_get_contents(__DIR__ . '/configs/gcp_serviceaccount.json'), true)
    ]);

    $config = Utils::getConfig(__DIR__ . '/configs/config.json');

    $handler = new FirestoreBackupHandler($db, $storage, $logger, $config);
    $handler->handle($event);
}
