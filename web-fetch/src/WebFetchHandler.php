<?php declare(strict_types=1);

namespace MyApp;

use CloudEvents\V1\CloudEventInterface;
use Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use yananob\MyGcpTools\CFUtils;
use yananob\MyTools\Logger;
use yananob\MyTools\Raindrop;
use yananob\MyTools\Trigger;

/**
 * web-fetch のリクエストおよびイベント処理を行うハンドラクラス。
 */
class WebFetchHandler
{
    /**
     * @param Logger $logger
     * @param Trigger $trigger
     * @param Raindrop $raindrop
     * @param array<string, mixed> $config
     * @param string $templatePath
     */
    public function __construct(
        private Logger $logger,
        private Trigger $trigger,
        private Raindrop $raindrop,
        private array $config,
        private string $templatePath = __DIR__ . '/../templates/index.html'
    ) {}

    /**
     * CloudEventを処理し、設定時刻に一致すればURLをRaindropに追加します。
     *
     * @param CloudEventInterface $event
     * @return void
     */
    public function handleEvent(CloudEventInterface $event): void
    {
        if (!isset($this->config["settings"]) || !is_array($this->config["settings"])) {
            $this->logger->log("設定ファイルに settings が見つかりません。");
            return;
        }

        foreach ($this->config["settings"] as $setting) {
            $this->logger->log("処理対象: " . json_encode($setting));

            if ($this->trigger->isLaunch($setting["timing"])) {
                $this->logger->log("タイミングが一致したため、ページをRaindropに追加します。");
                $this->raindrop->add($setting["url"]);
            }
        }

        $this->logger->log("処理が正常に完了しました。");
    }

    /**
     * HTTPリクエストを処理し、フォームの表示またはURLのRaindrop追加を行います。
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handleHttp(ServerRequestInterface $request): ResponseInterface
    {
        $isLocal = CFUtils::isLocalHttp($request);

        if ($request->getMethod() === 'GET') {
            if (file_exists($this->templatePath)) {
                return new Response(
                    200,
                    ['Content-Type' => 'text/html'],
                    (string)file_get_contents($this->templatePath)
                );
            }

            $this->logger->log("エラー: HTMLフォームが次のパスに見つかりません: " . $this->templatePath);
            return new Response(
                500,
                ['Content-Type' => 'text/plain'],
                'Error: Form template not found.'
            );
        }

        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $url = is_array($body) && isset($body['url']) ? (string)$body['url'] : null;

            if (empty($url)) {
                $this->logger->log("POSTリクエストにURLが含まれていません。");
                return new Response(400, ['Content-Type' => 'text/plain'], 'URL not provided');
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->logger->log("無効なURL形式です: " . $url);
                return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid URL format provided.');
            }

            $this->logger->log("追加するURLを受信しました: " . $url);

            try {
                $this->raindrop->add($url);
                $this->logger->log("RaindropにURLを追加しました: " . $url);

                $location = CFUtils::getBaseUrl($isLocal, $request);
                $location .= "?" . http_build_query(['success' => 'URL added successfully!']);
                return new Response(302, ['Location' => $location]);
            } catch (Exception $e) {
                $this->logger->log("URL追加エラー: " . $e->getMessage());
                return new Response(
                    500,
                    ['Content-Type' => 'text/plain'],
                    'Error adding URL: ' . $e->getMessage()
                );
            }
        }

        $this->logger->log("未対応のHTTPメソッドです: " . $request->getMethod());
        return new Response(405, ['Content-Type' => 'text/plain'], 'Method Not Allowed');
    }
}
