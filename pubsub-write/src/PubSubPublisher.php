<?php

declare(strict_types=1);

namespace PubSubWriter;

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Exception;

class PubSubPublisher
{
    private PubSubClient $pubSubClient;

    public function __construct(?string $projectId)
    {
        if (!$projectId) {
            throw new Exception("Project ID is required to initialize PubSubClient.");
        }
        $this->pubSubClient = new PubSubClient([
            'projectId' => $projectId,
        ]);
    }

    // Allows injecting a mock client for testing
    public static function createWithClient(PubSubClient $client): self
    {
        $instance = new self(null); // Project ID not needed if client is injected
        $instance->pubSubClient = $client;
        return $instance;
    }

    public function publish(string $topicName, array $messageData): array
    {
        $topic = $this->pubSubClient->topic($topicName);
        $message = [
            'data' => json_encode($messageData),
            // attributes can be added here if needed
        ];
        return $topic->publish($message);
    }
}
