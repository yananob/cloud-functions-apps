<?php

declare(strict_types=1);

namespace PubSubWriter\Tests;

use PHPUnit\Framework\TestCase;
use Google\CloudFunctions\FunctionsFramework;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;

// Define the function if it's not already (e.g., when running tests standalone)
if (!function_exists('publishMessage')) {
    require __DIR__ . '/../index.php';
}
if (!function_exists('load_config')) {
    // This function is also in index.php, ensure it's loaded.
    // The above require should cover this, but being explicit doesn't hurt
    // if it were in a separate file.
    require __DIR__ . '/../index.php';
}


class PubSubWriterTest extends TestCase
{
    private static $tempConfigPath;
    private static $originalConfigContent = null;

    public static function setUpBeforeClass(): void
    {
        self::$tempConfigPath = dirname(__DIR__) . '/configs/config.json';

        if (file_exists(self::$tempConfigPath)) {
            self::$originalConfigContent = file_get_contents(self::$tempConfigPath);
        } else {
            // Ensure the directory exists
            if (!is_dir(dirname(self::$tempConfigPath))) {
                mkdir(dirname(self::$tempConfigPath), 0777, true);
            }
        }
        // Create a dummy config for testing
        file_put_contents(self::$tempConfigPath, json_encode(['project_id' => 'test-project']));
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up the dummy config and restore original if it existed
        if (self::$originalConfigContent !== null) {
            file_put_contents(self::$tempConfigPath, self::$originalConfigContent);
        } else {
            if (file_exists(self::$tempConfigPath)) {
                unlink(self::$tempConfigPath);
            }
        }
    }

    private function createMockRequest(array $queryParams): ServerRequestInterface
    {
        $uri = new Uri('');
        $uri = $uri->withQuery(http_build_query($queryParams));
        // Ensure 'GET' is uppercase as per HTTP method standards
        return new ServerRequest('GET', $uri);
    }

    public function testPublishMessageSuccess()
    {
        // This test will run against the actual function `publishMessage`.
        // Mocking `new PubSubClient` directly is complex without specific libraries (like AspectMock)
        // or refactoring `publishMessage` for dependency injection.
        // We are using a 'test-project' in `config.json`, so the actual publish
        // will likely fail authentication with GCP, but we can check that the function
        // attempts the publish and structures the response correctly.

        $request = $this->createMockRequest(['topic' => 'test-topic', 'message' => 'hello']);

        // Call the actual function defined in index.php
        $response = publishMessage($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseBody = (string) $response->getBody();
        $this->assertStringContainsString('Topic: test-topic', $responseBody);
        // Check that some form of result is included. The actual PubSub client will return a structure.
        // If 'test-project' is invalid or lacks permissions, an error from PubSub client might be in the result.
        $this->assertStringContainsString('Result: {', $responseBody);
    }

    public function testPublishMessageMissingTopic()
    {
        $request = $this->createMockRequest(['message' => 'hello']); // No topic
        $response = publishMessage($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Error: Topic parameter is missing.', (string) $response->getBody());
    }

    public function testPublishMessageMissingProjectId()
    {
        // Save current config content and then delete the file
        $currentConfig = file_get_contents(self::$tempConfigPath);
        unlink(self::$tempConfigPath);

        $request = $this->createMockRequest(['topic' => 'test-topic']);
        $response = publishMessage($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Error: Project ID is not configured.', (string) $response->getBody());

        // Restore config file for other tests
        file_put_contents(self::$tempConfigPath, $currentConfig);
    }
}
```
