<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;         // PSR-7 HTTPレスポンスインターフェース
use Psr\Http\Message\ServerRequestInterface;    // PSR-7 HTTPサーバーリクエストインターフェース
use Google\CloudFunctions\FunctionsFramework;   // Google Cloud Functions Framework
use GuzzleHttp\Psr7\Response;                   // Guzzle HTTPレスポンス実装 (PSR-7準拠)
use yananob\MyGcpTools\CFUtils;                 // 独自GCPユーティリティ (関数名取得など)
use yananob\MyTools\Logger;                     // 独自ロガークラス
use yananob\MyTools\Line;                       // 独自LINE送信用クラス

FunctionsFramework::http('main', 'main');

// HTTPリクエストを処理するメイン関数 (主にLINE Webhookからのリクエストを想定)
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger(CFUtils::getFunctionName());
    $logger->log(str_repeat("-", 120));
    // 受信したリクエストの詳細をログに出力
    $logger->log("headers: " . json_encode($request->getHeaders()));
    $logger->log("params: " . json_encode($request->getQueryParams()));
    $logger->log("parsedBody: " . json_encode($request->getParsedBody()));
    $rawBody = $request->getBody()->getContents();
    $logger->log("body: " . $rawBody);
    $body = json_decode($rawBody, false);

    $logger->log($_ENV);
    $logger->log(CFUtils::isTestingEnv());

    // LINE Webhookイベントの処理 (最初のイベントのみを対象)
    $event = $body->events[0];
    $message = $event->message->text;

    $type = $event->source->type; // イベントソースのタイプ ('user', 'group', 'room')
    $targetId = null;

    // イベントソースのタイプに応じて返信先のIDを特定
    if ($type === 'user') {
        $targetId = $event->source->userId;
    } else if ($type === 'group') {
        $targetId = $event->source->groupId;
    } else if ($type === 'room') {
        $targetId = $event->source->roomId;
    } else {
        // 未知のタイプの場合は例外をスロー
        throw new Exception("Unknown type :" + $type);
    }

    $line = new Line(__DIR__ . "/configs/line.json");
    $line->sendMessage(
        bot: "test", // 使用するLINE Botの設定キー (例: "test" ボット)
        // target: "dailylog", // 固定のターゲットに送る場合はこちらを使用 (現在コメントアウト)
        targetId: $targetId, // 動的に取得した返信先ID
        message: "Type: {$type}\nTargetId: {$targetId}\nMessage: {$message}", // 返信するメッセージ内容 (受信内容をエコー)
        replyToken: $event->replyToken // LINE Webhookから受け取ったリプライトークン
    );
  
    $headers = ['Content-Type' => 'application/json'];
    // ステータスコード200で、受信したJSONボディをそのまま返す (LINE Webhookの一般的な応答)
    return new Response(200, $headers, json_encode($body));
}
