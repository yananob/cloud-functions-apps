<?php declare(strict_types=1);

namespace MyApp;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;
use yananob\MyGcpTools\CFUtils;
use yananob\MyTools\Logger;
use yananob\MyTools\Line;

class WebhookHandler
{
    public function __construct(
        private Line $line,
        private Logger $logger
    ) {}

    /**
     * Handles the incoming LINE webhook request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->log(str_repeat("-", 120));
        $this->logger->log("headers: " . json_encode($request->getHeaders()));
        $this->logger->log("params: " . json_encode($request->getQueryParams()));
        $this->logger->log("parsedBody: " . json_encode($request->getParsedBody()));

        $rawBody = $request->getBody()->getContents();
        $this->logger->log("body: " . $rawBody);

        $body = json_decode($rawBody, true);

        $this->logger->log($_ENV);
        $this->logger->log(CFUtils::isTestingEnv());

        if (!is_array($body) || !isset($body['events']) || !is_array($body['events'])) {
            $this->logger->log("No events found in the request body.");
            return new Response(200, ['Content-Type' => 'application/json'], $rawBody);
        }

        foreach ($body['events'] as $event) {
            $this->processEvent($event);
        }

        $headers = ['Content-Type' => 'application/json'];
        return new Response(200, $headers, $rawBody);
    }

    /**
     * Processes a single LINE webhook event.
     *
     * @param array<string, mixed> $event
     * @return void
     * @throws Exception
     */
    private function processEvent(array $event): void
    {
        if (!isset($event['message']['text']) || !isset($event['source']['type'])) {
            $this->logger->log("Skipping event: message text or source type not found.");
            return;
        }

        $message = $event['message']['text'];
        $source = $event['source'];
        $type = $source['type'];

        $targetId = match ($type) {
            'user' => $source['userId'] ?? null,
            'group' => $source['groupId'] ?? null,
            'room' => $source['roomId'] ?? null,
            default => throw new Exception("Unknown type : " . $type),
        };

        if ($targetId === null) {
            $this->logger->log("TargetId not found for type: " . $type);
            return;
        }

        $this->line->sendReply(
            bot: "test",
            replyToken: (string)($event['replyToken'] ?? ''),
            message: "Type: {$type}\nTargetId: {$targetId}\nMessage: {$message}"
        );
    }
}
