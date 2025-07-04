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
use yananob\MyTools\Raindrop;

// CloudEventを処理するメイン関数
// 設定されたタイミングで指定されたURLをRaindropに追加する
FunctionsFramework::cloudEvent('main_event', 'main_event');

// HTTPリクエストを処理するメイン関数
// 指定されたURLをRaindropに追加する
FunctionsFramework::http('main_http', 'main_http');

function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("web-fetch-event");
    $trigger = new Trigger();

    $config = Utils::getConfig(dirname(__FILE__) . "/configs/config.json");

    $raindrop = new Raindrop(__DIR__ . '/configs/raindrop.json');
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting));

        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Timing matched, adding page to Raindrop");
            $raindrop->add($setting["url"]);
        }
    };

    $logger->log("Succeeded.");
}

function main_http(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("web-fetch-http");

    if ($request->getMethod() === 'GET') {
        // Serve the HTML form
        $formPath = __DIR__ . '/templates/index.html';
        if (file_exists($formPath)) {
            return new Response(
                200,
                ['Content-Type' => 'text/html'],
                file_get_contents($formPath)
            );
        } else {
            $logger->log("Error: HTML form not found at " . $formPath);
            return new Response(
                500,
                ['Content-Type' => 'text/plain'],
                'Error: Form template not found.'
            );
        }
    } elseif ($request->getMethod() === 'POST') {
        // Process the form submission (existing logic)
        $body = $request->getParsedBody();
        $url = $body['url'] ?? null;

        if (empty($url)) {
            $logger->log("URL not provided in POST request.");
            return new Response(400, ['Content-Type' => 'text/plain'], 'URL not provided');
        }

        // Validate URL format (basic validation)
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $logger->log("Invalid URL format: " . $url);
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid URL format provided.');
        }

        $logger->log("Received URL to add: " . $url);

        try {
            $raindrop = new Raindrop(__DIR__ . '/configs/raindrop.json');
            $raindrop->add($url);
            $logger->log("URL added to Raindrop: " . $url);

            return new Response(
                200,
                ['Content-Type' => 'text/plain'],
                'URL added successfully to Raindrop: ' . $url
            );
        } catch (\Exception $e) {
            $logger->log("Error adding URL: " . $e->getMessage());
            return new Response(
                500,
                ['Content-Type' => 'text/plain'],
                'Error adding URL: ' . $e->getMessage()
            );
        }
    } else {
        // Handle other methods (optional, 405 Method Not Allowed is good practice)
        $logger->log("Unsupported HTTP method: " . $request->getMethod());
        return new Response(405, ['Content-Type' => 'text/plain'], 'Method Not Allowed');
    }
}
