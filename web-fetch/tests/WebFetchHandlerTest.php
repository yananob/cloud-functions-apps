<?php declare(strict_types=1);

namespace MyApp\Tests;

use PHPUnit\Framework\TestCase;

if (class_exists(\DG\BypassFinals::class)) {
    \DG\BypassFinals::enable();
}

use CloudEvents\V1\CloudEventInterface;
use Psr\Http\Message\ServerRequestInterface;
use yananob\MyTools\Logger;
use yananob\MyTools\Raindrop;
use yananob\MyTools\Trigger;
use MyApp\WebFetchHandler;

class WebFetchHandlerTest extends TestCase
{
    private string $tempTemplatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempTemplatePath = sys_get_temp_dir() . '/test_index.html';
        file_put_contents($this->tempTemplatePath, '<html><body>Test Form</body></html>');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempTemplatePath)) {
            unlink($this->tempTemplatePath);
        }
        parent::tearDown();
    }

    public function testHandleEventAddsToRaindropWhenTriggered(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockRaindrop = $this->createMock(Raindrop::class);
        $mockEvent = $this->createMock(CloudEventInterface::class);

        $timing = ["hour" => 9];
        $config = [
            "settings" => [
                [
                    "timing" => $timing,
                    "url" => "https://example.com"
                ]
            ]
        ];

        $mockTrigger->method('isLaunch')->with($timing)->willReturn(true);
        $mockRaindrop->expects($this->once())
            ->method('add')
            ->with("https://example.com");

        $handler = new WebFetchHandler($mockLogger, $mockTrigger, $mockRaindrop, $config, $this->tempTemplatePath);
        $handler->handleEvent($mockEvent);
    }

    public function testHandleEventDoesNotAddWhenNotTriggered(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockRaindrop = $this->createMock(Raindrop::class);
        $mockEvent = $this->createMock(CloudEventInterface::class);

        $timing = ["hour" => 9];
        $config = [
            "settings" => [
                [
                    "timing" => $timing,
                    "url" => "https://example.com"
                ]
            ]
        ];

        $mockTrigger->method('isLaunch')->with($timing)->willReturn(false);
        $mockRaindrop->expects($this->never())->method('add');

        $handler = new WebFetchHandler($mockLogger, $mockTrigger, $mockRaindrop, $config, $this->tempTemplatePath);
        $handler->handleEvent($mockEvent);
    }

    public function testHandleHttpGetReturnsHtmlForm(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockRaindrop = $this->createMock(Raindrop::class);
        $mockRequest = $this->createMock(ServerRequestInterface::class);

        $mockRequest->method('getMethod')->willReturn('GET');

        $handler = new WebFetchHandler($mockLogger, $mockTrigger, $mockRaindrop, [], $this->tempTemplatePath);
        $response = $handler->handleHttp($mockRequest);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('<html><body>Test Form</body></html>', (string)$response->getBody());
    }

    public function testHandleHttpPostValidUrlAddsToRaindropAndRedirects(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockRaindrop = $this->createMock(Raindrop::class);
        $mockRequest = $this->createMock(ServerRequestInterface::class);

        $mockRequest->method('getMethod')->willReturn('POST');
        $mockRequest->method('getParsedBody')->willReturn(['url' => 'https://example.com/item']);

        $mockRaindrop->expects($this->once())
            ->method('add')
            ->with('https://example.com/item');

        $handler = new WebFetchHandler($mockLogger, $mockTrigger, $mockRaindrop, [], $this->tempTemplatePath);
        $response = $handler->handleHttp($mockRequest);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
    }

    public function testHandleHttpPostInvalidUrlReturns400(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockRaindrop = $this->createMock(Raindrop::class);
        $mockRequest = $this->createMock(ServerRequestInterface::class);

        $mockRequest->method('getMethod')->willReturn('POST');
        $mockRequest->method('getParsedBody')->willReturn(['url' => 'not-a-valid-url']);

        $mockRaindrop->expects($this->never())->method('add');

        $handler = new WebFetchHandler($mockLogger, $mockTrigger, $mockRaindrop, [], $this->tempTemplatePath);
        $response = $handler->handleHttp($mockRequest);

        $this->assertEquals(400, $response->getStatusCode());
    }
}
