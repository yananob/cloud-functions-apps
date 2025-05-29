<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

Google\CloudFunctions\FunctionsFramework::cloudEvent('main', 'main');
function main(CloudEvents\V1\CloudEventInterface $event): void
{
    $logger = new yananob\MyTools\Logger("gmail-cleanup");
    $client = yananob\MyTools\GmailWrapper::getClient(
        __DIR__ . '/configs/googleapi_clientsecret.json',
        __DIR__ . '/configs/googleapi_token.json',
    );
    $service = new Google\Service\Gmail($client);
    $query = new MyApp\Query();

    $user = 'me';

    $config = yananob\MyTools\Utils::getConfig(__DIR__ . "/configs/config.json");

    foreach ($config["targets"] as $target) {
        $logger->log("Processing target: " . json_encode($target));
        $params = [
            "maxResults" => 20,
            "q" => $query->build($target),
            "includeSpamTrash" => false,
        ];
        $logger->log("Listing messages: " . json_encode($params));
        $results = $service->users_messages->listUsersMessages($user, $params);

        if (count($results->getMessages()) == 0) {
            $logger->log("No results found.");
            continue;
        }

        $logger->log("Deleting messages:");
        $message_ids = [];
        foreach ($results->getMessages() as $message) {
            $message_b = $service->users_messages->get($user, $message->id);
            $logger->log("[{$message->id}] {$message_b->snippet}");
            $message_ids[] = $message->id;
            $message_b = $service->users_messages->trash($user, $message->id);
        }
    };

    $logger->log("Succeeded.");
}
