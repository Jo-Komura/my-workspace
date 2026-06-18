import re
import os

files = [
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\style.css",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\buy-sell.html",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\investment.html",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\management.html",
]

pattern = r'font-size:0\.75(rem|em)'
replacement = r'font-size:0.85\1'

for filepath in files:
    filename = os.path.basename(filepath)
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    matches = re.findall(pattern, content)
    if matches:
        new_content = re.sub(pattern, replacement, content)
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        print(f"✅ {filename} - {len(matches)}箇所を 0.75 → 0.85 に変更しました")
    else:
        print(f"⚠️  {filename} - 該当箇所なし")

print("\n完了！")
