<?php declare(strict_types=1);

namespace MyApp;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;
use yananob\MyGcpTools\CFUtils;
use yananob\MyTools\Logger;
use yananob\MyTools\Line;

/**
 * LINE Webhook リクエストを処理するハンドラクラス。
 */
class WebhookHandler
{
    /**
     * @param Line $line LINE送信処理用インスタンス
     * @param Logger $logger ログ出力用インスタンス
     */
    public function __construct(
        private Line $line,
        private Logger $logger
    ) {}

    /**
     * 受信した LINE Webhook リクエストを処理します。
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

        if (!is_array($body) || !isset($body['events']) || !is_array($body['events'])) {
            $this->logger->log("リクエストボディにイベントが見つかりません。");
            return new Response(200, ['Content-Type' => 'application/json'], $rawBody);
        }

        foreach ($body['events'] as $event) {
            $this->processEvent($event);
        }

        $headers = ['Content-Type' => 'application/json'];
        return new Response(200, $headers, $rawBody);
    }

    /**
     * 単一の LINE Webhook イベントを処理します。
     *
     * @param array<string, mixed> $eventData
     * @return void
     * @throws Exception
     */
    private function processEvent(array $eventData): void
    {
        $event = new LineEvent($eventData);
        $type = $event->getType();

        match ($type) {
            'message' => $this->handleMessageEvent($event),
            default => $this->logger->log("未対応のイベントタイプです: " . ($type ?? 'null')),
        };
    }

    /**
     * メッセージイベントを処理します。
     *
     * @param LineEvent $event
     * @return void
     * @throws Exception
     */
    private function handleMessageEvent(LineEvent $event): void
    {
        if (!$event->isValidTextMessageEvent()) {
            $this->logger->log("メッセージイベントをスキップします: 有効なテキストメッセージではありません。");
            return;
        }

        $sourceType = $event->getSourceType();
        $targetId = $event->getTargetId();

        if ($targetId === null) {
            $this->logger->log("送信元タイプに対する TargetId が見つかりません: " . ($sourceType ?? 'null'));
            return;
        }

        $this->line->sendReply(
            bot: "test",
            replyToken: $event->getReplyToken(),
            message: "Type: {$sourceType}\nTargetId: {$targetId}\nMessage: {$event->getMessageText()}"
        );
    }
}
