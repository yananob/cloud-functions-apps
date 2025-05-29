import json
import logging
from google.cloud import pubsub_v1 # Google Cloud Pub/Sub クライアントライブラリ
import functions_framework
from common.utils import load_conf # common.utils から設定読み込み関数をインポート

LOG_LEVEL = logging.INFO

@functions_framework.http # HTTPトリガーで起動するCloud Functionとして登録
def main(request):
    # 設定情報をロード (プロジェクトIDなどを取得するため)
    conf = load_conf()

    logging.basicConfig(format="[%(asctime)s] [%(levelname)s] %(message)s",
                        level=LOG_LEVEL, datefmt="%Y/%m/%d %H:%M:%S")
    logging.info("args: {}".format(request.args))
    
    topic = request.args.get('topic')
    
    publisher = pubsub_v1.PublisherClient()
    # パブリッシュ先のトピックパスを作成 (例: "projects/your-project-id/topics/your-topic-name")
    topic_path = publisher.topic_path(conf["project_id"], topic)

    message = json.dumps(request.args)
    # Pub/Sub にパブリッシュするデータはbytes型である必要があるため、UTF-8でエンコード
    data = message.encode("utf-8")
    
    # publish() メソッドは Future オブジェクトを返す
    future = publisher.publish(topic_path, data)
    
    result = f"Topic: {topic}, Result: {future.result()}"
    logging.info(result)

    return result
