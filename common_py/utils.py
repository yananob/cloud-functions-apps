import json

# 設定ファイルを読み込み、属性としてアクセス可能な辞書オブジェクトを返す関数
# config_path: 設定ファイルのパス (デフォルトは "config.json")
def load_attributed_config(config_path: str = "config.json"):
    with open(config_path, "r") as f:
        config = json.load(f, object_hook=AttributedDict)
        return config

# 辞書のキーを属性としてアクセスできるようにするクラス
class AttributedDict(object):
    def __init__(self, obj):
        self._obj = obj

    def __getattr__(self, name):
        return self._obj.get(name)

    def fields(self):
        return self._obj

    def keys(self):
        return self._obj.keys()
