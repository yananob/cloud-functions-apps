<?php declare(strict_types=1);

namespace MyApp\Tests;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;

BypassFinals::enable();
use CloudEvents\V1\CloudEventInterface;
use Google\Service\Gmail;
use Google\Service\Gmail\Resource\UsersMessages;
use Google\Service\Gmail\ListMessagesResponse;
use Google\Service\Gmail\Message;
use yananob\MyTools\Logger;
use MyApp\GmailCleanupHandler;
use MyApp\Query;

class GmailCleanupHandlerTest extends TestCase
{
    public function testHandleProcessesTargets(): void
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockService = $this->createMock(Gmail::class);
        $mockUsersMessages = $this->createMock(UsersMessages::class);
        $mockQuery = $this->createMock(Query::class);
        $mockEvent = $this->createMock(CloudEventInterface::class);

        $mockService->users_messages = $mockUsersMessages;

        $config = [
            "targets" => [
                ["keyword" => "test1"],
                ["keyword" => "test2"]
            ]
        ];

        $mockQuery->expects($this->exactly(2))
            ->method('build')
            ->willReturnMap([
                [["keyword" => "test1"], "q:test1"],
                [["keyword" => "test2"], "q:test2"],
            ]);

        // Setup for first target: 1 message found
        $message1 = new Message();
        $message1->id = "msg1";
        $listResponse1 = new ListMessagesResponse();
        $listResponse1->setMessages([$message1]);

        // Setup for second target: no messages found
        $listResponse2 = new ListMessagesResponse();
        $listResponse2->setMessages([]);

        $mockUsersMessages->expects($this->exactly(2))
            ->method('listUsersMessages')
            ->willReturnMap([
                ['me', ["maxResults" => 20, "q" => "q:test1", "includeSpamTrash" => false], $listResponse1],
                ['me', ["maxResults" => 20, "q" => "q:test2", "includeSpamTrash" => false], $listResponse2],
            ]);

        $messageDetail1 = new Message();
        $messageDetail1->id = "msg1";
        $messageDetail1->snippet = "snippet1";

        $mockUsersMessages->expects($this->once())
            ->method('get')
            ->with('me', 'msg1')
            ->willReturn($messageDetail1);

        $mockUsersMessages->expects($this->once())
            ->method('trash')
            ->with('me', 'msg1');

        $handler = new GmailCleanupHandler($mockLogger, $mockService, $mockQuery, $config);
        $handler->handle($mockEvent);

        // Verification is mostly done via expects() calls on mocks.
    }
}
