<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;
use yananob\MyTools\Logger;
use yananob\MyTools\Trigger;
use yananob\MyTools\Utils;
use yananob\MyTools\Pocket;
use yananob\MyTools\Raindrop;

// CloudEventを処理するメイン関数
// 設定されたタイミングで指定されたURLをPocketおよびRaindropに追加する
FunctionsFramework::cloudEvent('main_event', 'main_event');

// HTTPリクエストを処理するメイン関数
// 指定されたURLをPocketおよびRaindropに追加する
FunctionsFramework::http('main_http', 'main_http');

function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("web-fetch-event");
    $trigger = new Trigger();

    $config = Utils::getConfig(dirname(__FILE__) . "/configs/config.json");

    $pocket = new Pocket(__DIR__ . '/configs/pocket.json');
    $raindrop = new Raindrop(__DIR__ . '/configs/raindrop.json');
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting));

        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Timing matched, adding page to Pocket and Raindrop");
            $pocket->add($setting["url"]);
            $raindrop->add($setting["url"]);
        }
    };

    $logger->log("Succeeded.");
}

function main_http(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("web-fetch-http");

    $body = $request->getParsedBody();
    $url = $body['url'] ?? null;

    if (empty($url)) {
        $logger->log("URL not provided.");
        return new Response(400, ['Content-Type' => 'text/plain'], 'URL not provided');
    }

    $logger->log("Received URL: " . $url);

    try {
        $pocket = new Pocket(__DIR__ . '/configs/pocket.json');
        $raindrop = new Raindrop(__DIR__ . '/configs/raindrop.json');

        $logger->log("Adding to Pocket...");
        $pocket->add($url);
        $logger->log("URL added to Pocket successfully.");

        $logger->log("Adding to Raindrop...");
        $raindrop->add($url);
        $logger->log("URL added to Raindrop successfully.");

        return new Response(200, ['Content-Type' => 'text/plain'], 'URL added successfully to Pocket and Raindrop');
    } catch (\Exception $e) {
        $logger->log("Error adding URL: " . $e->getMessage());
        return new Response(500, ['Content-Type' => 'text/plain'], 'Error adding URL: ' . $e->getMessage());
    }
}
