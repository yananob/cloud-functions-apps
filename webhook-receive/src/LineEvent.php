<?php declare(strict_types=1);

namespace MyApp;

use Exception;

/**
 * LINE Webhook イベントのラッパークラス。
 */
class LineEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data) {}

    /**
     * イベントタイプを取得します。
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->data['type'] ?? null;
    }

    /**
     * 応答トークンを取得します。
     *
     * @return string
     */
    public function getReplyToken(): string
    {
        return (string)($this->data['replyToken'] ?? '');
    }

    /**
     * メッセージタイプを取得します。
     *
     * @return string|null
     */
    public function getMessageType(): ?string
    {
        return $this->data['message']['type'] ?? null;
    }

    /**
     * メッセージ本文を取得します。
     *
     * @return string|null
     */
    public function getMessageText(): ?string
    {
        return $this->data['message']['text'] ?? null;
    }

    /**
     * 送信元タイプ（user, group, room）を取得します。
     *
     * @return string|null
     */
    public function getSourceType(): ?string
    {
        return $this->data['source']['type'] ?? null;
    }

    /**
     * 送信元タイプに応じたターゲットIDを取得します。
     *
     * @return string|null
     * @throws Exception 未知の送信元タイプの場合に発生
     */
    public function getTargetId(): ?string
    {
        $type = $this->getSourceType();
        $source = $this->data['source'] ?? [];

        return match ($type) {
            'user' => $source['userId'] ?? null,
            'group' => $source['groupId'] ?? null,
            'room' => $source['roomId'] ?? null,
            null => null,
            default => throw new Exception("未知のタイプです: " . $type),
        };
    }

    /**
     * 有効なテキストメッセージイベントであるか判定します。
     *
     * @return bool
     */
    public function isValidTextMessageEvent(): bool
    {
        return $this->getType() === 'message'
            && $this->getMessageType() === 'text'
            && $this->getMessageText() !== null
            && $this->getSourceType() !== null;
    }
}
