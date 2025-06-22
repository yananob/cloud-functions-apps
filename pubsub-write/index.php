<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use PubSubWriter\ConfigLoader;
use PubSubWriter\PubSubPublisher;

FunctionsFramework::http('main', 'publishMessageHttp');

function publishMessageHttp(ServerRequestInterface $request): ResponseInterface
{
    $basePath = __DIR__;
    $configLoader = new ConfigLoader($basePath); // ConfigLoader expects basePath to find /configs/
    $projectId = $configLoader->getProjectId();

    if (!$projectId) {
        error_log('Project ID is not configured.');
        return new Response(500, ['Content-Type' => 'text/plain'], 'Error: Project ID is not configured.');
    }

    $queryParams = $request->getQueryParams();
    $topicName = $queryParams['topic'] ?? null;

    if (empty($topicName)) {
        error_log('Topic parameter is missing.');
        return new Response(400, ['Content-Type' => 'text/plain'], 'Error: Topic parameter is missing.');
    }

    try {
        $publisher = new PubSubPublisher($projectId);
        $publishResult = $publisher->publish($topicName, $queryParams);

        $resultText = "Topic: {$topicName}, Result: " . json_encode($publishResult);
        error_log($resultText);
        return new Response(200, ['Content-Type' => 'text/plain'], $resultText);
    } catch (Exception $e) {
        error_log('Error publishing message: ' . $e->getMessage());
        return new Response(500, ['Content-Type' => 'text/plain'], 'Error publishing message: ' . $e->getMessage());
    }
}
