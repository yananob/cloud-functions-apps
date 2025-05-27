<?php declare(strict_types=1);

// Composerのオートローダーを読み込む
require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;         // PSR-7 HTTPレスポンスインターフェース
use Psr\Http\Message\ServerRequestInterface;    // PSR-7 HTTPサーバーリクエストインターフェース
use Google\CloudFunctions\FunctionsFramework;   // Google Cloud Functions Framework
use GuzzleHttp\Psr7\Response;                   // Guzzle HTTPレスポンス実装 (PSR-7準拠)
use yananob\MyGcpTools\CFUtils;                 // 独自GCPユーティリティ (関数名取得など)
use yananob\MyTools\Logger;                     // 独自ロガークラス
use yananob\MyTools\Line;                       // 独自LINE送信用クラス

// HTTPトリガー関数として 'main' 関数を登録
FunctionsFramework::http('main', 'main');

// HTTPリクエストを処理するメイン関数 (主にLINE Webhookからのリクエストを想定)
function main(ServerRequestInterface $request): ResponseInterface
{
    // ロガーを初期化 (関数名をログ識別子として使用)
    $logger = new Logger(CFUtils::getFunctionName());
    $logger->log(str_repeat("-", 120)); // ログの区切り線
    // 受信したリクエストの詳細をログに出力
    $logger->log("headers: " . json_encode($request->getHeaders()));       // HTTPヘッダー
    $logger->log("params: " . json_encode($request->getQueryParams()));    // URLクエリパラメータ
    $logger->log("parsedBody: " . json_encode($request->getParsedBody())); // パースされたリクエストボディ (例: application/x-www-form-urlencoded の場合)
    $rawBody = $request->getBody()->getContents(); // 生のリクエストボディを取得
    $logger->log("body: " . $rawBody);             // 生のリクエストボディをログに出力
    $body = json_decode($rawBody, false);          // 生のボディをJSONとしてデコード (オブジェクトとして)

    $logger->log($_ENV); // 環境変数をログに出力
    $logger->log(CFUtils::isTestingEnv()); // テスト環境かどうかをログに出力

    // LINE Webhookイベントの処理 (最初のイベントのみを対象)
    $event = $body->events[0];
    $message = $event->message->text; // ユーザーが送信したメッセージテキスト

    $type = $event->source->type; // イベントソースのタイプ ('user', 'group', 'room')
    $targetId = null; // 返信先のIDを格納する変数

    // イベントソースのタイプに応じて返信先のIDを特定
    if ($type === 'user') {
        $targetId = $event->source->userId;  // ユーザーID
    } else if ($type === 'group') {
        $targetId = $event->source->groupId; // グループID
    } else if ($type === 'room') {
        $targetId = $event->source->roomId;  // ルームID
    } else {
        // 未知のタイプの場合は例外をスロー
        throw new Exception("Unknown type :" + $type);
    }

    // LINE送信用オブジェクトを初期化 (LINEの設定ファイルパスを指定)
    $line = new Line(__DIR__ . "/configs/line.json");
    // LINEに返信メッセージを送信
    $line->sendMessage(
        bot: "test", // 使用するLINE Botの設定キー (例: "test" ボット)
        // target: "dailylog", // 固定のターゲットに送る場合はこちらを使用 (現在コメントアウト)
        targetId: $targetId, // 動的に取得した返信先ID
        message: "Type: {$type}\nTargetId: {$targetId}\nMessage: {$message}", // 返信するメッセージ内容 (受信内容をエコー)
        replyToken: $event->replyToken // LINE Webhookから受け取ったリプライトークン
    );
  
    // Webhook送信元へのHTTPレスポンス
    $headers = ['Content-Type' => 'application/json']; // レスポンスヘッダー
    // ステータスコード200で、受信したJSONボディをそのまま返す (LINE Webhookの一般的な応答)
    return new Response(200, $headers, json_encode($body));
}
