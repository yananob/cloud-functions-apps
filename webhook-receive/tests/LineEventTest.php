<?php declare(strict_types=1);

namespace MyApp\Tests;

use PHPUnit\Framework\TestCase;
use MyApp\LineEvent;
use Exception;

class LineEventTest extends TestCase
{
    public function testGetType(): void
    {
        $event = new LineEvent(['type' => 'message']);
        $this->assertEquals('message', $event->getType());
    }

    public function testGetReplyToken(): void
    {
        $event = new LineEvent(['replyToken' => 'token123']);
        $this->assertEquals('token123', $event->getReplyToken());
    }

    public function testGetMessageType(): void
    {
        $event = new LineEvent(['message' => ['type' => 'text']]);
        $this->assertEquals('text', $event->getMessageType());
    }

    public function testGetMessageText(): void
    {
        $event = new LineEvent(['message' => ['text' => 'hello world']]);
        $this->assertEquals('hello world', $event->getMessageText());
    }

    public function testGetSourceType(): void
    {
        $event = new LineEvent(['source' => ['type' => 'user']]);
        $this->assertEquals('user', $event->getSourceType());
    }

    public function testGetTargetIdForUser(): void
    {
        $event = new LineEvent([
            'source' => [
                'type' => 'user',
                'userId' => 'U12345'
            ]
        ]);
        $this->assertEquals('U12345', $event->getTargetId());
    }

    public function testGetTargetIdForGroup(): void
    {
        $event = new LineEvent([
            'source' => [
                'type' => 'group',
                'groupId' => 'G12345'
            ]
        ]);
        $this->assertEquals('G12345', $event->getTargetId());
    }

    public function testGetTargetIdForRoom(): void
    {
        $event = new LineEvent([
            'source' => [
                'type' => 'room',
                'roomId' => 'R12345'
            ]
        ]);
        $this->assertEquals('R12345', $event->getTargetId());
    }

    public function testGetTargetIdThrowsExceptionForUnknownType(): void
    {
        $event = new LineEvent([
            'source' => [
                'type' => 'unknown'
            ]
        ]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unknown type : unknown");
        $event->getTargetId();
    }

    public function testIsValidTextMessageEvent(): void
    {
        $validEvent = new LineEvent([
            'type' => 'message',
            'message' => [
                'type' => 'text',
                'text' => 'hello'
            ],
            'source' => ['type' => 'user']
        ]);
        $this->assertTrue($validEvent->isValidTextMessageEvent());

        $invalidMessageTypeEvent = new LineEvent([
            'type' => 'message',
            'message' => [
                'type' => 'image',
                'text' => 'hello'
            ],
            'source' => ['type' => 'user']
        ]);
        $this->assertFalse($invalidMessageTypeEvent->isValidTextMessageEvent());

        $invalidEventTypeEvent = new LineEvent([
            'type' => 'follow',
            'message' => [
                'type' => 'text',
                'text' => 'hello'
            ],
            'source' => ['type' => 'user']
        ]);
        $this->assertFalse($invalidEventTypeEvent->isValidTextMessageEvent());
    }
}
