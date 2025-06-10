<?php

require __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Google\Cloud\PubSub\PubSubClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

// Register the function with Functions Framework.
FunctionsFramework::http('main', 'publishMessage');

function publishMessage(ServerRequestInterface $request): ResponseInterface
{
    $config = load_config();
    $projectId = $config['project_id'] ?? null;

    if (!$projectId) {
        error_log('Project ID is not configured.');
        return new Response(500, [], 'Error: Project ID is not configured.');
    }

    $queryParams = $request->getQueryParams();
    $topicName = $queryParams['topic'] ?? null;

    if (empty($topicName)) {
        error_log('Topic parameter is missing.');
        return new Response(400, [], 'Error: Topic parameter is missing.');
    }

    $messageData = json_encode($queryParams);

    try {
        $pubSub = new PubSubClient([
            'projectId' => $projectId,
        ]);
        $topic = $pubSub->topic($topicName);
        $publishResult = $topic->publish(['data' => $messageData]);

        $resultText = "Topic: {$topicName}, Result: " . json_encode($publishResult);
        error_log($resultText); // Log to Cloud Functions logs
        return new Response(200, ['Content-Type' => 'text/plain'], $resultText);
    } catch (Exception $e) {
        error_log('Error publishing message: ' . $e->getMessage());
        return new Response(500, [], 'Error publishing message: ' . $e->getMessage());
    }
}

function load_config(): array
{
    $configPath = __DIR__ . '/configs/config.json';
    if (file_exists($configPath)) {
        $configJson = file_get_contents($configPath);
        return json_decode($configJson, true) ?: [];
    }
    return [];
}

```
