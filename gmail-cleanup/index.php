<?php declare(strict_types=1);

use CloudEvents\V1\CloudEventInterface;
use Google\CloudFunctions\FunctionsFramework;
use yananob\MyTools\GmailWrapper;
use yananob\MyTools\Logger;
use yananob\MyTools\Utils;

require_once __DIR__ . '/vendor/autoload.php';

// Define words to filter from logs.
const FILTERED_WORDS = ['exception'];

/**
 * Filters out specified words from a log message.
 *
 * @param string $message The original log message.
 * @return string The filtered log message.
 */
function filter_log_message(string $message): string
{
    foreach (FILTERED_WORDS as $word) {
        $message = str_ireplace($word, '[MASKED]', $message);
    }
    return $message;
}

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("gmail-cleanup");
    $client = GmailWrapper::getClient(
        Utils::getConfig(__DIR__ . '/configs/googleapi_clientsecret.json'),
        Utils::getConfig(__DIR__ . '/configs/googleapi_token.json'),
    );
    $service = new Google\Service\Gmail($client);
    $query = new MyApp\Query();

    $user = 'me';

    $config = Utils::getConfig(__DIR__ . "/configs/config.json");

    foreach ($config["targets"] as $target) {
        $logger->log(filter_log_message("Processing target: " . json_encode($target)));
        $params = [
            "maxResults" => 20,
            "q" => $query->build($target),
            "includeSpamTrash" => false,
        ];
        $logger->log(filter_log_message("Listing messages: " . json_encode($params)));
        $results = $service->users_messages->listUsersMessages($user, $params);

        if (count($results->getMessages()) == 0) {
            $logger->log("No results found.");
            continue;
        }

        $logger->log("Deleting messages:");
        $message_ids = [];
        foreach ($results->getMessages() as $message) {
            $message_b = $service->users_messages->get($user, $message->id);
            $logger->log(filter_log_message("[{$message->id}] {$message_b->snippet}"));
            $message_ids[] = $message->id;
            $message_b = $service->users_messages->trash($user, $message->id);
        }
    };

    $logger->log("Succeeded.");
}
