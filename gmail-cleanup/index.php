<?php declare(strict_types=1);

use CloudEvents\V1\CloudEventInterface;
use Google\CloudFunctions\FunctionsFramework;
use yananob\MyTools\GmailWrapper;
use yananob\MyTools\Logger;
use yananob\MyTools\Utils;
use MyApp\GmailCleanupHandler;
use MyApp\Query;

require_once __DIR__ . '/vendor/autoload.php';

FunctionsFramework::cloudEvent('main_event', 'main_event');

/**
 * CloudEventを処理するメイン関数。
 * 実際の処理は GmailCleanupHandler クラスに委譲します。
 *
 * @param CloudEventInterface $event
 * @return void
 */
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("gmail-cleanup");
    $client = GmailWrapper::getClient(
        Utils::getConfig(__DIR__ . '/configs/googleapi_clientsecret.json'),
        Utils::getConfig(__DIR__ . '/configs/googleapi_token.json'),
    );
    $service = new Google\Service\Gmail($client);
    $query = new Query();
    $config = Utils::getConfig(__DIR__ . "/configs/config.json");

    $handler = new GmailCleanupHandler($logger, $service, $query, $config);
    $handler->handle($event);
}
