import re
import os

changes = {
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\style.css": [
        ".area-item-desc",
    ],
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\buy-sell.html": [
        ".choice-card-desc",
        ".flow-step-desc",
        ".faq-a-text",
    ],
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\investment.html": [
        ".what-text",
        ".merit-desc",
        ".type-card-desc",
    ],
    r"H:\マイドライブ\ClaudeWorkspace\land-design\src\management.html": [
        ".service-card-desc",
        ".flow-step-desc",
        ".faq-a-text",
    ],
}

for filepath, classes in changes.items():
    filename = os.path.basename(filepath)
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    total = 0
    for cls in classes:
        pattern = r'(' + re.escape(cls) + r'\{[^}]*)font-weight:300([^}]*\})'
        new_content, count = re.subn(pattern, r'\1font-weight:400\2', content)
        if count > 0:
            content = new_content
            total += count

    if total > 0:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"✅ {filename} - {total}箇所を変更しました")
    else:
        print(f"⚠️  {filename} - 変更なし（クラス名が見つからなかった可能性あり）")

print("\n完了！")
