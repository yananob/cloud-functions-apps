<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Google\CloudFunctions\FunctionsFramework;
use GuzzleHttp\Psr7\Response;
use yananob\MyGcpTools\CFUtils;
use yananob\MyTools\Logger;
use yananob\MyTools\Line;

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger(CFUtils::getFunctionName());
    $logger->log(str_repeat("-", 120));
    $logger->log("headers: " . json_encode($request->getHeaders()));
    $logger->log("params: " . json_encode($request->getQueryParams()));
    $logger->log("parsedBody: " . json_encode($request->getParsedBody()));
    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);
    $body = json_decode($body, false);

    $logger->log($_ENV);
    $logger->log(CFUtils::isTestingEnv());

    $event = $body->events[0];
    $message = $event->message->text;

    $type = $event->source->type;
    $targetId = null;
    // typeを判定して、idを取得
    if ($type === 'user') {
        $targetId = $event->source->userId;
    } else if ($type === 'group') {
        $targetId = $event->source->groupId;
    } else if ($type === 'room') {
        $targetId = $event->source->roomId;
    } else {
        throw new Exception("Unknown type :" + $type);
    }

    $line = new Line(__DIR__ . "/configs/line.json");
    $line->sendMessage(
        bot: "test",
        // target: "dailylog",
        targetId: $targetId,
        message: "Type: {$type}\nTargetId: {$targetId}\nMessage: {$message}",
        replyToken: $event->replyToken
    );
  
    $headers = ['Content-Type' => 'application/json'];
    return new Response(200, $headers, json_encode($body));
}
