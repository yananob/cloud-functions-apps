<?php declare(strict_types=1);

namespace MyApp\Tests;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use yananob\MyTools\Line;
use yananob\MyTools\Logger;
use MyApp\WebhookHandler;

// We need to bypass finals before classes are loaded
BypassFinals::enable();

class WebhookHandlerTest extends TestCase
{
    public function testHandleProcessesEvents(): void
    {
        $mockLine = $this->createMock(Line::class);
        $mockLogger = $this->createMock(Logger::class);
        $mockRequest = $this->getMockBuilder(ServerRequestInterface::class)->getMock();
        $mockBody = $this->getMockBuilder(StreamInterface::class)->getMock();

        $payload = json_encode([
            'events' => [
                [
                    'type' => 'message',
                    'replyToken' => 'test-reply-token',
                    'source' => [
                        'type' => 'user',
                        'userId' => 'test-user-id'
                    ],
                    'message' => [
                        'type' => 'text',
                        'text' => 'hello'
                    ]
                ]
            ]
        ]);

        $mockBody->method('getContents')->willReturn($payload);
        $mockRequest->method('getBody')->willReturn($mockBody);
        $mockRequest->method('getHeaders')->willReturn([]);
        $mockRequest->method('getQueryParams')->willReturn([]);
        $mockRequest->method('getParsedBody')->willReturn([]);

        $mockLine->expects($this->once())
            ->method('sendReply')
            ->with(
                'test',
                'test-reply-token',
                "Type: user\nTargetId: test-user-id\nMessage: hello"
            );

        $handler = new WebhookHandler($mockLine, $mockLogger);
        $response = $handler->handle($mockRequest);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($payload, (string)$response->getBody());
    }
}
