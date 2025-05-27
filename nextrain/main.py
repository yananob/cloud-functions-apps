# -*- coding: utf-8 -*-

import sys
import json
import logging
from enum import Enum
from html.parser import HTMLParser
import urllib.request
import urllib.parse
from flask import Flask, request, Response
import arrow # 日時操作ライブラリ
import functions_framework

# Yahoo!乗換案内 (印刷用ページ) のエンドポイント
YAHOO_ENDPOINT = 'https://transit.yahoo.co.jp/search/print'

LOG_LEVEL = logging.INFO # ログレベル

# レスポンスのタイプを定義する列挙型
class RESPONSE_TYPE(Enum):
    NORMAL = "normal"   # 通常: 次の電車までの時間 (分:秒)
    VERBOSE = "verbose" # 詳細: JSON形式で詳細情報

# Yahoo!乗換案内のHTMLをパースするためのクラス
class YahooTransitParser(HTMLParser):
    def __init__(self):
        HTMLParser.__init__(self)
        self.found = False # 次の電車の時間を見つけたかどうかのフラグ
        self.count = 0     # 特定のHTMLタグ構造を追跡するためのカウンター
        self.next_time = "" #抽出した次の電車の時間文字列 (例: "19:34発→")

    # 開始タグを処理するメソッド
    def handle_starttag(self, tag, attrs):
        # 特定のidとclassを持つタグのネスト構造を辿って、次の電車の時刻が含まれるspanタグを探す
        if self.count == 0: # 初期状態
            if tag == "div":
                for attr in attrs:
                    if attr == ("id", "srline"): # <div id="srline"> を探す
                        self.count += 1
                        break
        elif self.count == 1: # div#srline の中にいる状態
            if tag == "li":
                for attr in attrs:
                    if attr == ("class", "time"): # <li class="time"> を探す
                        self.count += 1
                        break
        elif self.count == 2: # li.time の中にいる状態
            if tag == "span": # 最初の<span>タグ (これが時刻を含む)
                self.count = 99 # 処理完了を示すためにカウンターを大きな値に
                self.found = True # データ取得フラグをTrueに

    # タグ内のデータを処理するメソッド
    def handle_data(self, data):
        if self.found: # foundフラグがTrueの場合 (spanタグのデータ)
            self.next_time = data # データをnext_timeに格納
            self.found = False # データ取得完了したのでフラグをFalseに

    # 抽出した次の電車の時間を返すメソッド
    def get_next_time(self):
        return self.next_time

# Yahoo!乗換案内にリクエストを送信し、HTMLレスポンスを取得する関数
# sta_from: 出発駅
# sta_to: 到着駅
def send_request(sta_from, sta_to):

    # Yahoo!乗換案内の検索パラメータ (多くは固定値)
    data = {
        "from": sta_from, # 出発駅
        "to": sta_to,     # 到着駅
        "type": "1",      # 不明 (おそらく検索タイプ)
        
        "flatlon": "",    # 不明
        "tlatlon": "",    # 不明
        "viacode": "",    # 経由駅コード
        "shin": "1",      # 新幹線を利用するか (1:する)
        "ex": "1",        # 特急を利用するか (1:する)
        "hb": "1",        # 不明 (おそらく有料列車関連)
        "al": "1",        # 不明 (おそらく航空機関連)
        "lb": "1",        # 不明 (おそらく路線バス関連)
        "sr": "1",        # 不明
        "ws": "3",        # 不明 (おそらく速度関連)
        "s": "0",         # 不明 (おそらくソート順)
        "ei": "",         # 不明
        "fl": "1",        # 不明
        "tl": "3",        # 不明
        "expkind": "1",   # 不明 (おそらく急行の種類)
        "mtf": "",        # 不明
        "out_y": "",      # 不明
        "mode": "",       # 不明
        "c": "",          # 不明
        "searchOpt": "",  # 不明
        "stype": "",      # 不明
        "ticket": "ic",   # ICカード料金優先
        "userpass": "1",  # 不明
        "passtype": "",   # 不明
        "detour_id": "",  # 不明
        "no": "1",        # 検索結果の表示件数か？ (1件目)
    }

    logging.info("data: {}".format(urllib.parse.urlencode(data).encode())) # 送信するデータをログに出力
    # リクエストURLを構築
    req = urllib.request.Request("{}?{}".format(YAHOO_ENDPOINT, urllib.parse.urlencode(data)))

    # リクエストを送信し、レスポンスを取得
    response = urllib.request.urlopen(req)

    # レスポンスボディをUTF-8でデコード
    body = response.read().decode('utf-8')
    # デバッグ用にHTMLをファイルに保存するコード (コメントアウト)
    # with open("test.html", "w") as f:
    #     f.write(body)

    return body # HTML文字列を返す

# HTMLレスポンスをパースして次の電車の時刻と現在時刻からの差分を計算する関数
# sta_from: 出発駅
# sta_to: 到着駅
def parse_response(sta_from, sta_to):
    parser = YahooTransitParser() # パーサーをインスタンス化
    parser.feed(send_request(sta_from, sta_to)) # HTMLをパース
    next_time_str = parser.get_next_time()  # パース結果から次の電車の時刻文字列を取得 (例: "19:34発→")
    if not next_time_str: # 時刻が取得できなかった場合
        raise Exception("Cannot get next_time. Please check the parameters.") # 例外を発生
    logging.debug("response: {}".format(next_time_str))

    # 時刻文字列から "発" と "→" を除去 (例: "19:34")
    next_time_str = next_time_str.replace("発", "").replace("→", "")
    logging.debug("next_time_str: {}".format(next_time_str))
    
    # 現在時刻を東京タイムゾーンで取得
    now = arrow.now().to('Asia/Tokyo')
    # 次の電車の時刻をarrowオブジェクトに変換 (日付は今日、秒は0)
    next_time = now.replace(hour=int(next_time_str[0:2]),
                            minute=int(next_time_str[3:5]),
                            second=0)
    logging.debug("next_time: {}".format(next_time))

    # 次の電車までの時間差を計算
    diff_time = next_time - now
    logging.debug("diff_time: {}".format(diff_time))

    return next_time, diff_time # 次の電車の時刻 (arrow object), 時間差 (timedelta object)

# HTTPトリガーで起動するメイン関数
@functions_framework.http
def main(req):
    # ロギング設定
    logging.basicConfig(format="[%(asctime)s] [%(levelname)s] %(message)s",
                        level=LOG_LEVEL, datefmt="%Y/%m/%d %H:%M:%S")
    logging.info("args: {}".format(req.args)) # リクエスト引数をログに出力
    message = "" # レスポンスメッセージ
    content_type = "text/plain" # デフォルトのコンテントタイプ
    try:
        # リクエストパラメータからレスポンスタイプ、出発駅、到着駅を取得
        res_type_str = req.args.get("res_type", RESPONSE_TYPE.NORMAL.value) # デフォルトは "normal"
        try:
            res_type = RESPONSE_TYPE(res_type_str)
        except ValueError:
            res_type = RESPONSE_TYPE.NORMAL # 不正な値の場合はNORMALにする
            logging.warning(f"Invalid res_type: {res_type_str}. Defaulting to normal.")

        sta_from = req.args.get("from", "")
        sta_to = req.args.get("to", "")
        if (not sta_from) or (not sta_to): # 出発駅または到着駅が未指定の場合
            raise Exception("Please set from and to parameters.") # 例外を発生

        # 電車情報を解析
        next_time, diff_time = parse_response(sta_from, sta_to)
        # 時間差を「分:秒」の文字列にフォーマット
        dm = divmod(diff_time.total_seconds(), 60) # (商:分, 余り:秒)
        logging.debug("divmod: {}".format(dm))
        diff_time_str = "{}:{:02d}".format(int(dm[0]), int(dm[1])) # 例: "12:34"

        if res_type == RESPONSE_TYPE.VERBOSE: # 詳細レスポンスの場合
            message_json = {
                "station_from": sta_from,
                "station_to": sta_to,
                "next_time": next_time.format("HH:mm"), # 次の電車の時刻 (HH:mm)
                "diff_seconds": int(diff_time.total_seconds()), # あと何秒か
                "diff_time": diff_time_str, # あと何分何秒か (MM:SS)
            }
            message = json.dumps(message_json, ensure_ascii=False) # JSON文字列に変換 (日本語をエスケープしない)
            content_type = "application/json"
        else: # 通常レスポンスの場合
            message = diff_time_str

    except Exception: # エラーハンドリング
        ex, ms, tb = sys.exc_info()
        message = f"Error occured. <{ms}>" # エラーメッセージを設定
        logging.error(ms) # エラーをログに出力

    finally: # 最後に必ず実行
        logging.info("message: {}".format(message)) # レスポンスメッセージをログに出力
        resp = Response(message) # FlaskのResponseオブジェクトを作成
        resp.headers["content-type"] = content_type # Content-Typeを設定
        return resp
