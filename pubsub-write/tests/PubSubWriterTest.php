<?php

declare(strict_types=1);

namespace PubSubWriter\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
// ConfigLoader and PubSubPublisher are not directly mocked here anymore,
// as index.php news them up. Tests are more integration-style for index.php.

// The function to test is publishMessageHttp
require_once __DIR__ . '/../index.php'; // Make sure this path is correct

class PubSubWriterTest extends TestCase
{
    private $tempConfigPath;
    private $originalConfigContent = null;
    private $configDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = __DIR__ . '/../configs';
        $this->tempConfigPath = $this->configDir . '/config.json';

        // Ensure config directory exists
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0777, true);
        }

        // Backup existing config if it exists
        if (file_exists($this->tempConfigPath)) {
            $this->originalConfigContent = file_get_contents($this->tempConfigPath);
        }
    }

    protected function tearDown(): void
    {
        // Restore original config or delete test config
        if ($this->originalConfigContent !== null) {
            file_put_contents($this->tempConfigPath, $this->originalConfigContent);
            $this->originalConfigContent = null; // Reset for next test
        } else {
            if (file_exists($this->tempConfigPath)) {
                unlink($this->tempConfigPath);
            }
        }
        parent::tearDown();
    }

    private function createServerRequest(array $queryParams): ServerRequestInterface
    {
        $uri = new Uri('');
        $uri = $uri->withQuery(http_build_query($queryParams));
        $request = new ServerRequest('GET', $uri);
        return $request->withQueryParams($queryParams); // Explicitly set the parsed query params
    }

    private function setTestConfig(array $config): void
    {
        file_put_contents($this->tempConfigPath, json_encode($config));
    }

    public function testPublishMessageHttpSuccess()
    {
        $this->setTestConfig(['project_id' => 'test-project-for-php-unit']);

        $request = $this->createServerRequest(['topic' => 'test-topic', 'message' => 'hello']);

        // PubSubPublisher will be newed up by publishMessageHttp.
        // This will attempt a real publish, which will likely fail if 'test-project-for-php-unit'
        // isn't a real, configured project with credentials. The test checks structure.
        $response = publishMessageHttp($request);

        // In a sandboxed environment without real credentials or an emulator,
        // the publish attempt will fail. We expect a 500 error from our handler.
        $this->assertEquals(500, $response->getStatusCode());
        $responseBody = (string) $response->getBody();
        $this->assertStringContainsString('Error publishing message:', $responseBody);
        // Check for a part of the typical Google Cloud permission denied error.
        // This makes the test more robust to slight changes in the exact error message from the client library.
        $this->assertStringContainsString('PERMISSION_DENIED', $responseBody);
    }

    public function testPublishMessageHttpMissingTopic()
    {
        $this->setTestConfig(['project_id' => 'test-project-for-php-unit']);
        $request = $this->createServerRequest(['message' => 'hello']); // No 'topic'

        $response = publishMessageHttp($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Error: Topic parameter is missing.', (string) $response->getBody());
    }

    public function testPublishMessageHttpMissingProjectId()
    {
        // Ensure no config or config without project_id by writing an empty array
        $this->setTestConfig([]);

        $request = $this->createServerRequest(['topic' => 'test-topic']);

        $response = publishMessageHttp($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Error: Project ID is not configured.', (string) $response->getBody());
    }
}
