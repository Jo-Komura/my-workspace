import re
import os

files = [
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\style.css",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\buy-sell.html",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\investment.html",
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\management.html",
]

pattern = r'(\.[a-z][a-z0-9\-]*)\{[^}]*font-weight:300[^}]*\}'

for filepath in files:
    filename = os.path.basename(filepath)
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    matches = re.findall(pattern, content)
    if matches:
        print(f"\n【{filename}】")
        for m in matches:
            print(f"  {m}")
    else:
        print(f"\n【{filename}】 → 該当なし")
