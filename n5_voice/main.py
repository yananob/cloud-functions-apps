import logging
import urllib.request, urllib.parse
from flask import Flask, request, render_template
import functions_framework
from common.utils import load_conf # common.utilsから設定読み込み関数をインポート (具体的な実装は不明)

app = Flask(__name__)

# AutoRemoteサービスのAPIエンドポイントURL
AUTOREMOTE_ENDPOINT = 'https://autoremotejoaomgcd.appspot.com/sendmessage'

LOG_LEVEL = logging.INFO # ログレベルをINFOに設定

# AutoRemote APIへのリクエスト例 (コメントアウト)
# ?key=[APIキー]
# &message=[スピーカー名]%20[メッセージ本文]=:=voice  (例: jp_women%20テスト=:=voice)

# AutoRemoteにリクエストを送信する関数
# conf: 設定情報 (APIトークンを含む)
# speaker: 発話させるスピーカーの名前 (例: "jp_women")
# message: 発話させるメッセージ本文
def send_request(conf, speaker, message):

    # メッセージ内のスペースをカンマに置換 (AutoRemoteの仕様に合わせるためか？)
    message = message.replace(" ", ",")

    # AutoRemote APIに送信するデータを作成
    data = {
        "key": conf["token"], # 設定から取得したAPIトークン
        "message": "{} {}=:=voice".format(speaker, message), # "スピーカー名 メッセージ本文=:=voice" の形式
    }
    
    logging.info("speaker: {}, message: {}".format(speaker, message)) # 送信するスピーカーとメッセージをログに出力
    # URLエンコードされたデータを含むリクエストオブジェクトを作成
    req = urllib.request.Request("{}?{}".format(AUTOREMOTE_ENDPOINT, urllib.parse.urlencode(data)))
    
    # AutoRemote APIにリクエストを送信し、レスポンスをログに出力
    # res = urllib.request.urlopen(req) # 古い書き方 (コメントアウト)
    # logging.info("response: {}".format(res.read())) # 古い書き方 (コメントアウト)
    with urllib.request.urlopen(req) as res: # リクエストを送信し、レスポンスを取得
        logging.info("response: {}".format(res.read())) # レスポンス内容をログに出力


@functions_framework.http # HTTPトリガーで起動するCloud Functionとして登録
def main(req):
    # 設定情報をロード (具体的な実装は load_conf 次第)
    conf = load_conf() 

    # ロギング設定を初期化
    logging.basicConfig(format="[%(asctime)s] [%(levelname)s] %(message)s", # ログのフォーマット
                        level=LOG_LEVEL, datefmt="%Y/%m/%d %H:%M:%S") # ログレベルと日付フォーマット
    logging.info("args: {}".format(req.args)) # HTTPリクエストの引数をログに出力

    feedback = "" # ユーザーへのフィードバックメッセージ
    # リクエストのクエリパラメータから "speaker" と "message" を取得
    speaker = req.args.get("speaker", "") # speakerパラメータがない場合は空文字
    message = req.args.get("message", "") # messageパラメータがない場合は空文字

    # speakerとmessageの両方が指定されている場合
    if speaker and message:
        send_request(conf, speaker, message) # AutoRemoteにリクエストを送信
        feedback = "Message successfully sent." # フィードバックメッセージを設定

    # HTMLテンプレート (form.html) をレンダリングして返す
    # フィードバックメッセージをテンプレートに渡す
    return render_template("form.html", feedback=feedback)
