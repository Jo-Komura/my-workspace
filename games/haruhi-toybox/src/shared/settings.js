/* =======================================================================
   はるひのおもちゃ箱 ・ 全ゲーム共通の「音」の設定
   - 箱（表紙）の設定画面で BGM と 効果音(SE) の音量を変える。
   - 音量レベルは 1〜5（既定3）＋ ミュート。localStorage に保存し、
     各ゲームのページが読み込んで自分の音量に反映する。
   - レベル→実音量の対応表は下の *_LEVELS（添字＝レベル。0番はダミー）。
     ここの数字を直すだけで全ゲームの音量バランスを再調整できる。
   ======================================================================= */
(function (g) {
  // 添字 = レベル(1〜5)。0番は使わないダミー。
  var BGM_LEVELS = [0, 0.10, 0.16, 0.23, 0.30, 0.38]; // 既定3=0.23（実機で合わせた控えめ背景）
  var SE_LEVELS  = [0, 0.40, 0.60, 0.80, 0.90, 1.00]; // 既定3=0.80／5でフル(1.0)
  var DEF = 3;

  // localStorage が使えない端末でも落ちないように、全部 try で包む。
  function readInt(key) {
    try { var v = parseInt(localStorage.getItem(key), 10);
          return (v >= 1 && v <= 5) ? v : DEF; } catch (e) { return DEF; }
  }
  function readBool(key) {
    try { return localStorage.getItem(key) === '1'; } catch (e) { return false; }
  }
  function write(key, val) { try { localStorage.setItem(key, val); } catch (e) {} }
  function clampLv(v) { v = parseInt(v, 10); return (v >= 1 && v <= 5) ? v : DEF; }

  var S = {
    BGM_LEVELS: BGM_LEVELS, SE_LEVELS: SE_LEVELS, DEF: DEF,

    bgmLevel: function () { return readInt('htb_bgm_lv'); },
    seLevel:  function () { return readInt('htb_se_lv'); },
    bgmMuted: function () { return readBool('htb_bgm_mute'); },
    seMuted:  function () { return readBool('htb_se_mute'); },

    setBgmLevel: function (v) { write('htb_bgm_lv', clampLv(v)); },
    setSeLevel:  function (v) { write('htb_se_lv', clampLv(v)); },
    setBgmMute:  function (on) { write('htb_bgm_mute', on ? '1' : '0'); },
    setSeMute:   function (on) { write('htb_se_mute', on ? '1' : '0'); },

    // 実際にAudioへ渡す音量（0〜1）。ミュート中は0。
    bgmVolume: function () { return this.bgmMuted() ? 0 : BGM_LEVELS[this.bgmLevel()]; },
    seVolume:  function () { return this.seMuted()  ? 0 : SE_LEVELS[this.seLevel()]; }
  };

  g.HTBSettings = S;
})(window);
