import os
import sys
import logging
import time
import json
import traceback
import flask
import functions_framework
import requests

from common.utils import load_attributed_config

LOG_LEVEL = logging.DEBUG

# AIモデルにメッセージを送信し、応答を取得する関数
# api_key: APIキー
# model: 使用するAIモデルの名前
# message: 送信するメッセージ
def send_message(api_key: str, model: str, message: str):
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
    }
    payload = {
        "model": model,
        "messages": [
            {
                "role": "system",
                "content": "You are a helpful assistant.", # システムメッセージ (AIの役割を指定)
            },
            {
                "role": "user",
                "content": message,
            },
        ],
    }
    logging.debug(f"payload: {json.dumps(payload)}")
    # OpenAIのChat Completions APIにPOSTリクエストを送信
    r = requests.post(
        "https://api.openai.com/v1/chat/completions",
        headers=headers,
        data=json.dumps(payload))
    return r


@functions_framework.http # HTTPトリガーで起動するCloud Functionとして登録
def main(request):
    logging.basicConfig(
        format="[%(asctime)s] [%(levelname)s] %(message)s", # ログのフォーマットを指定
        level=LOG_LEVEL, datefmt="%Y/%m/%d %H:%M:%S")

    logging.info(f"data: {request.form.to_dict()}")

    # 設定ファイルを読み込み (config.jsonからAPIキーやモデル名を取得)
    config = load_attributed_config(os.path.join("configs", "config.json"))

    data = request.form.to_dict()
    question = ""
    answer = ""

    if data:
        # time.sleep(1) # 必要に応じて遅延を挿入 (現在コメントアウト)
        question = data["question"]
        api_response = send_message(config.api_key, config.model, question)
        logging.info(f"answer: {api_response.json()}")
        answer = api_response.json()["choices"][0]["message"]["content"]

    # HTMLテンプレート (form.html) をレンダリングして返す
    # 質問と回答をテンプレートに渡す
    return flask.render_template("form.html", question=question, answer=answer)
