import json
import logging
from google.cloud import pubsub_v1 # Google Cloud Pub/Sub クライアントライブラリ
import functions_framework
from common.utils import load_conf # common.utils から設定読み込み関数をインポート

LOG_LEVEL = logging.INFO # ログレベルをINFOに設定

@functions_framework.http # HTTPトリガーで起動するCloud Functionとして登録
def main(request):
    # 設定情報をロード (プロジェクトIDなどを取得するため)
    conf = load_conf()

    # ロギング設定を初期化
    logging.basicConfig(format="[%(asctime)s] [%(levelname)s] %(message)s", # ログのフォーマット
                        level=LOG_LEVEL, datefmt="%Y/%m/%d %H:%M:%S") # ログレベルと日付フォーマット
    logging.info("args: {}".format(request.args)) # HTTPリクエストの引数をログに出力
    
    # リクエストのクエリパラメータから 'topic' を取得
    topic = request.args.get('topic')
    
    # Pub/Sub パブリッシャークライアントを初期化
    publisher = pubsub_v1.PublisherClient()
    # パブリッシュ先のトピックパスを作成 (例: "projects/your-project-id/topics/your-topic-name")
    topic_path = publisher.topic_path(conf["project_id"], topic)

    # HTTPリクエストの全引数をJSON文字列に変換
    message = json.dumps(request.args)
    # Pub/Sub にパブリッシュするデータはbytes型である必要があるため、UTF-8でエンコード
    data = message.encode("utf-8")
    
    # 指定されたトピックにデータをパブリッシュ
    # publish() メソッドは Future オブジェクトを返す
    future = publisher.publish(topic_path, data)
    
    # パブリッシュ結果 (メッセージIDなど) を含む文字列を作成
    result = f"Topic: {topic}, Result: {future.result()}"
    logging.info(result) # 結果をログに出力

    return result # 結果文字列をHTTPレスポンスとして返す
