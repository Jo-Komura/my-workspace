/* ランドデザイン GA4 + イベント計測（電話タップ・問い合わせフォーム送信）
 * ───────────────────────────────────────────────
 * 【本番移行時にやること】下の GA4_ID を、landdesign11.co.jp の
 * GA4データストリームで発行される計測ID（G-XXXXXXXXXX）に差し替えるだけ。
 * それだけでページビュー計測＋電話/フォームのイベント計測が動き出す。
 *
 * ・ID未設定（プレースホルダ）の間は Google へ一切通信しない安全な状態。
 *   gtag() は常に定義され、イベントは dataLayer に積まれるだけの no-op。
 * ・全ページ共通で読み込む1本のファイル。IDの差し替えはこの1箇所でOK。
 */
(function () {
  var GA4_ID = 'G-XXXXXXXXXX'; // ← 本番の計測IDに差し替え

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () { dataLayer.push(arguments); };

  // 本物のIDが入ったときだけ gtag.js を読み込んで計測開始
  if (GA4_ID.indexOf('XXXX') === -1) {
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA4_ID;
    document.head.appendChild(s);
    gtag('js', new Date());
    gtag('config', GA4_ID);
  }

  // イベント計測（GA4未接続でも安全＝dataLayerに積むだけ）
  function bind() {
    // 電話タップ
    document.querySelectorAll('a[href^="tel:"]').forEach(function (a) {
      a.addEventListener('click', function () {
        gtag('event', 'tel_click', {
          phone_number: a.getAttribute('href').replace('tel:', ''),
          page: location.pathname
        });
      });
    });
    // 問い合わせフォーム送信
    document.querySelectorAll('form').forEach(function (f) {
      f.addEventListener('submit', function () {
        gtag('event', 'form_submit', {
          form_id: f.id || 'contact',
          page: location.pathname
        });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
