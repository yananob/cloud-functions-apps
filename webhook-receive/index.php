<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Google\CloudFunctions\FunctionsFramework;
use GuzzleHttp\Psr7\Response;
use yananob\mytools\Logger;
use yananob\mytools\Line;

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("webhook-receive");
    $logger->log(str_repeat("-", 120));

    $logger->log("headers: " . json_encode($request->getHeaders()));

    $logger->log("params: " . json_encode($request->getQueryParams()));

    $logger->log("parsedBody: " . json_encode($request->getParsedBody()));

    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);
    $logger->log("body_json: " . json_encode(json_decode($body)));
    $body = json_decode($body, false);

    // sample:
    /* body: 
    {
        "destination": "XXXX",
        "events" : [
            {
                "type": "message",
                "message": {
                    "type":"text",
                    "id":"XXXX",
                    "quoteToken":"XXXX",
                    "text":"わわわわわ"
                },
                "webhookEventId": "XXXX",
                "deliveryContext": {
                    "isRedelivery": false
                },
                "timestamp":1731926933496,
                "source":{
                    "type":"user",
                    "userId":"XXXX"
                },
                "replyToken":"XXXX",
                "mode":"active"
            }
        ]
    }
    */

    $event = $body->events[0];

    // typeを取得 
    $type = $event->source->type;

    $message = $event->message->text;

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
        bot: "aisan",
        // target: "dailylog", // TODO: 書き換え
        targetId: $targetId,
        message: "Got Message: \n" . $message,
        replyToken: $event->replyToken
    );
  
    $headers = ['Content-Type' => 'application/json'];
    return new Response(200, $headers, json_encode($body));
}
