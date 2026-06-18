import re
import os

files = [
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\buy-sell.html",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\investment.html",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\management.html",
]

pattern = r'<img src="data:image/png;base64,[^"]*" alt="LAND DESIGN">'
replacement = '<img src="images/logo.png" alt="LAND DESIGN">'

for filepath in files:
    filename = os.path.basename(filepath)
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()
    
    if re.search(pattern, content):
        new_content = re.sub(pattern, replacement, content)
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        print(f"✅ {filename} - base64ロゴを images/logo.png に置換しました")
    else:
        print(f"⚠️  {filename} - base64ロゴが見つかりませんでした（すでに修正済み？）")

print("\n完了！")
