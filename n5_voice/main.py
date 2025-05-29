import logging
import urllib.request, urllib.parse
from flask import Flask, request, render_template
import functions_framework
from common.utils import load_conf # common.utilsから設定読み込み関数をインポート (具体的な実装は不明)

app = Flask(__name__)

AUTOREMOTE_ENDPOINT = 'https://autoremotejoaomgcd.appspot.com/sendmessage'

LOG_LEVEL = logging.INFO

# AutoRemote APIへのリクエスト例
# ?key=[APIキー]
# &message=[スピーカー名]%20[メッセージ本文]=:=voice  (例: jp_women%20テスト=:=voice)

# AutoRemoteにリクエストを送信する関数
# conf: 設定情報 (APIトークンを含む)
# speaker: 発話させるスピーカーの名前 (例: "jp_women")
# message: 発話させるメッセージ本文
def send_request(conf, speaker, message):

    # メッセージ内のスペースをカンマに置換 (AutoRemoteの仕様に合わせるためか？)
    message = message.replace(" ", ",")

    data = {
        "key": conf["token"],
        "message": "{} {}=:=voice".format(speaker, message), # "スピーカー名 メッセージ本文=:=voice" の形式
    }
    
    logging.info("speaker: {}, message: {}".format(speaker, message))
    req = urllib.request.Request("{}?{}".format(AUTOREMOTE_ENDPOINT, urllib.parse.urlencode(data)))
    
    # res = urllib.request.urlopen(req) # 古い書き方 (コメントアウト)
    # logging.info("response: {}".format(res.read())) # 古い書き方 (コメントアウト)
    with urllib.request.urlopen(req) as res:
        logging.info("response: {}".format(res.read()))


@functions_framework.http # HTTPトリガーで起動するCloud Functionとして登録
def main(req):
    # 設定情報をロード (具体的な実装は load_conf 次第)
    conf = load_conf() 

    logging.basicConfig(format="[%(asctime)s] [%(levelname)s] %(message)s",
                        level=LOG_LEVEL, datefmt="%Y/%m/%d %H:%M:%S")
    logging.info("args: {}".format(req.args))

    feedback = ""
    speaker = req.args.get("speaker", "")
    message = req.args.get("message", "")

    if speaker and message:
        send_request(conf, speaker, message)
        feedback = "Message successfully sent."

    # HTMLテンプレート (form.html) をレンダリングして返す
    # フィードバックメッセージをテンプレートに渡す
    return render_template("form.html", feedback=feedback)
