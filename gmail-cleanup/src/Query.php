<?php declare(strict_types=1);

namespace MyApp;

// Gmail検索クエリを構築するためのクラス
final class Query
{
    public function __construct() {
        // コンストラクタ (現在は処理なし)
    }

    // 指定された条件に基づいてGmail検索クエリ文字列を構築するメソッド
    // $target: 検索条件を格納した連想配列
    //   - keyword: キーワード
    //   - from: 送信元メールアドレス
    //   - to: 送信先メールアドレス
    //   - subject: 件名
    //   - label: ラベル
    //   - date_before: 指定した期間より前の日付 (例: "P30D" は30日前)
    // 戻り値: 生成された検索クエリ文字列
    public function build(array $target): string
    {
        $q = ""; // 初期クエリ文字列
        // "keyword" 条件が存在する場合、クエリに追加
        if (array_key_exists("keyword", $target)) {
            $q .= $target["keyword"];
        };
    
        // "from" 条件が存在する場合、クエリに追加
        if (array_key_exists("from", $target)) {
            $q .= " from:" . $target["from"];
        };
    
        // "to" 条件が存在する場合、クエリに追加
        if (array_key_exists("to", $target)) {
            $q .= " to:" . $target["to"];
        };
    
        // "subject" 条件が存在する場合、クエリに追加
        if (array_key_exists("subject", $target)) {
            $q .= " subject:" . $target["subject"];
        };
    
        // "label" 条件が存在する場合、クエリに追加
        if (array_key_exists("label", $target)) {
            $q .= " label:" . $target["label"];
        };
    
        // "date_before" 条件が存在する場合、相対的な日付を計算してクエリに追加
        if (array_key_exists("date_before", $target)) {
            // 現在の日時から指定された期間を引いた日付を計算 (例: P30D -> 30日前)
            $targetDate = (new \DateTime())->sub(new \DateInterval($target["date_before"]))->format('Y/m/d');
            $q .= " before:${targetDate}"; // "before:YYYY/MM/DD" の形式で追加
        };
    
        return $q; // 構築されたクエリ文字列を返す
    }
}
