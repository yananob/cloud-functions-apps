<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;         // PSR-7 HTTPレスポンスインターフェース
use Psr\Http\Message\ServerRequestInterface;    // PSR-7 HTTPサーバーリクエストインターフェース
use Google\CloudFunctions\FunctionsFramework;   // Google Cloud Functions Framework
use yananob\MyGcpTools\CFUtils;
use yananob\MyTools\Logger;
use yananob\MyTools\Line;
use MyApp\WebhookHandler;

FunctionsFramework::http('main', 'main');

/**
 * HTTPリクエストを処理するメイン関数 (主にLINE Webhookからのリクエストを想定)
 * 実際の処理は WebhookHandler クラスに委譲します。
 *
 * @param ServerRequestInterface $request
 * @return ResponseInterface
 */
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger(CFUtils::getFunctionName());
    $line = new Line(__DIR__ . "/configs/line.json");
    $handler = new WebhookHandler($line, $logger);
    return $handler->handle($request);
}
