/* ===== salon-lp エンジン =====
   - 縦スナップは CSS(scroll-snap) が担当
   - ここは「点ナビ」「出現アニメの発火」「計測(スタブ)」を IntersectionObserver 1つでまとめる
     ＝アニメを光らせる観測者に「データも送って」と一言足すだけ、の実装 */

const feed   = document.getElementById('feed');
const panels = [...document.querySelectorAll('.panel')];
const dotsNav = document.getElementById('dots');

/* --- 点ナビをパネル数だけ自動生成（増減してもコード不変） --- */
panels.forEach((p, i) => {
  const b = document.createElement('button');
  b.className = 'dot';
  b.setAttribute('aria-label', (i + 1) + 'ページ目へ');
  b.addEventListener('click', () => p.scrollIntoView({ behavior: 'smooth' }));
  dotsNav.appendChild(b);
});
const dots = [...dotsNav.children];

/* --- 計測スタブ：入った時刻を覚え、出る時に滞在秒数を出す --- */
const enterAt = {};
function track(event, data) {
  // ★ ここに GA4 を入れる: if (window.gtag) gtag('event', event, data);
  console.log('[track]', event, JSON.stringify(data));
}

/* --- 観測者：60%見えたら「そのビートに居る」と判定 --- */
const io = new IntersectionObserver((entries) => {
  entries.forEach((e) => {
    const i = panels.indexOf(e.target);
    const beat = i + 1;
    const vid = e.target.querySelector('video');

    if (e.isIntersecting) {
      e.target.classList.add('in');                 // 出現アニメ発火
      dots.forEach((d, j) => d.classList.toggle('on', j === i));
      if (vid) vid.play().catch(() => {});           // 見えてる動画だけ再生
      enterAt[i] = performance.now();
      track('beat_view', { beat });                  // 到達（ファネル用）
    } else {
      if (enterAt[i] != null) {                      // 実際に入って(見て)いた時だけ処理＝初期の画面外発火では何もしない
        e.target.classList.add('seen');              // 一度見て離れた印＝次に戻った時は消える文字を"出たまま"に（初回は順番に表示のまま）
        const sec = +((performance.now() - enterAt[i]) / 1000).toFixed(1);
        track('beat_dwell', { beat, seconds: sec }); // 滞在時間
        enterAt[i] = null;
      }
      if (vid) vid.pause();                          // 画面外は止めて省電力
    }
  });
}, { root: feed, threshold: 0.6 });

panels.forEach((p) => io.observe(p));

/* --- 折り返し自動フィット（ジョー案：画面幅から1行に収める） ---
   意図した行数（<br>の数+1）を超えて折り返した行だけ、収まるまで少しずつ縮める。
   まずCSS本来のサイズに戻してから測るので、画面を広げれば元サイズに戻る。
   文言を変えても自動で追従。狭い実機（最低スペック機）でも1行を保つ。 */
function fitLines() {
  const lines = document.querySelectorAll('.copy .line, .copy .lead');
  lines.forEach((el) => { el.style.fontSize = ''; });          // いったんCSS指定のサイズに戻す
  lines.forEach((el) => {
    const intended = el.querySelectorAll('br').length + 1;     // 本来の行数
    let size = parseFloat(getComputedStyle(el).fontSize);
    let lh   = parseFloat(getComputedStyle(el).lineHeight);
    let guard = 0;
    while (Math.round(el.clientHeight / lh) > intended && size > 13 && guard < 40) {
      size -= 0.5;
      el.style.fontSize = size + 'px';
      lh = parseFloat(getComputedStyle(el).lineHeight);        // line-heightは倍率指定なので取り直す
      guard++;
    }
  });
}
window.addEventListener('load', fitLines);
window.addEventListener('resize', fitLines);
if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitLines);  // 日本語フォント確定後に再計算

/* --- CTA：④の解決ビートから⑤フォームへ滑らかに送る --- */
const formPanel = document.querySelector('.form-panel');
document.getElementById('cta').addEventListener('click', () => {
  track('cta_click', { beat: 4 });
  formPanel.scrollIntoView({ behavior: 'smooth' });
});

/* --- 資料請求フォーム ---
   FORM_ENDPOINT が空のうちは「試作モード」＝送信を模擬してお礼を出すだけ。
   本番でメール配達係(FormSubmit/Formspree等)やサーバーPHPのURLを入れれば、そこへPOSTする。 */
const FORM_ENDPOINT = ''; // ★ここに送信先URLを入れると実送信になる（例：https://formspree.io/f/xxxx）

const reqForm = document.getElementById('reqForm');
const thanks  = document.getElementById('thanks');

function showThanks() {
  reqForm.hidden = true;
  thanks.hidden = false;
  thanks.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

reqForm.addEventListener('submit', async (ev) => {
  ev.preventDefault();

  // ブラウザ標準のバリデーション（必須・メール形式）
  if (!reqForm.reportValidity()) return;

  const btn = reqForm.querySelector('.form-submit');
  btn.disabled = true;
  btn.textContent = '送信しています…';

  const data = Object.fromEntries(new FormData(reqForm).entries());
  track('lead_submit', { beat: 5, salon_type: data.salon_type || '(未選択)' });

  try {
    if (FORM_ENDPOINT) {
      const res = await fetch(FORM_ENDPOINT, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new FormData(reqForm),
      });
      if (!res.ok) throw new Error('bad response');
    }
    // 試作モード（ENDPOINT空）はここへ直行＝お礼を表示
    showThanks();
  } catch (err) {
    btn.disabled = false;
    btn.textContent = '無料で資料を受け取る';
    alert('送信に失敗しました。通信環境をご確認のうえ、もう一度お試しください。');
  }
});
