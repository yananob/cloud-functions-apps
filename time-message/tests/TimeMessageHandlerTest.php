<?php declare(strict_types=1);

namespace MyApp\Tests;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;

// We need to bypass finals before classes are loaded
BypassFinals::enable();
use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Logger;
use yananob\MyTools\Trigger;
use yananob\MyTools\Line;
use MyApp\TimeMessageHandler;

class TimeMessageHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testHandleSendsMessageWhenTimingMatches(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockLine = $this->createMock(Line::class);
        $mockEvent = $this->createMock(CloudEventInterface::class);

        $timing = ["hour" => "09"];
        $config = [
            "settings" => [
                [
                    "timing" => $timing,
                    "bot" => "test-bot",
                    "target" => "test-target",
                    "message" => "test-message"
                ]
            ]
        ];

        $mockTrigger->method('isLaunch')->with($timing)->willReturn(true);

        $mockLine->expects($this->once())
            ->method('sendPush')
            ->with("test-bot", "test-target", null, "test-message");

        $handler = new TimeMessageHandler($mockLogger, $mockTrigger, $mockLine, $config);
        $handler->handle($mockEvent);
    }

    public function testHandleDoesNotSendMessageWhenTimingDoesNotMatch(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockLine = $this->createMock(Line::class);
        $mockEvent = $this->createMock(CloudEventInterface::class);

        $timing = ["hour" => "09"];
        $config = [
            "settings" => [
                [
                    "timing" => $timing,
                    "bot" => "test-bot",
                    "target" => "test-target",
                    "message" => "test-message"
                ]
            ]
        ];

        $mockTrigger->method('isLaunch')->with($timing)->willReturn(false);

        $mockLine->expects($this->never())->method('sendPush');

        $handler = new TimeMessageHandler($mockLogger, $mockTrigger, $mockLine, $config);
        $handler->handle($mockEvent);
    }

    public function testHandleWithMultipleSettings(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockTrigger = $this->createMock(Trigger::class);
        $mockLine = $this->createMock(Line::class);
        $mockEvent = $this->createMock(CloudEventInterface::class);

        $timing1 = ["hour" => "09"];
        $timing2 = ["hour" => "10"];
        $config = [
            "settings" => [
                [
                    "timing" => $timing1,
                    "bot" => "bot1",
                    "target" => "target1",
                    "message" => "msg1"
                ],
                [
                    "timing" => $timing2,
                    "bot" => "bot2",
                    "target" => "target2",
                    "message" => "msg2"
                ]
            ]
        ];

        $mockTrigger->method('isLaunch')->willReturnMap([
            [$timing1, true],
            [$timing2, false],
        ]);

        $mockLine->expects($this->once())
            ->method('sendPush')
            ->with("bot1", "target1", null, "msg1");

        $handler = new TimeMessageHandler($mockLogger, $mockTrigger, $mockLine, $config);
        $handler->handle($mockEvent);
    }
}
