<?php
/**
 * Public guide: install the waiter app on iPhone (Home Screen web app, push from iOS 16.4)
 * and on Android (APK). Shows a QR of the app address for phones to scan from a computer.
 */
require __DIR__ . '/../includes/app.php';

$appUrl = abs_url('cameriere/');
$apk = waiter_apk_url();
$qr = is_executable('/usr/bin/qrencode')
    ? shell_exec('/usr/bin/qrencode -t SVG -s 8 -m 1 -l M -o - ' . escapeshellarg(abs_url('cameriere/installa.php')))
    : null;

// Little pictures of the iPhone buttons the waiter has to find.
$shareIcon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 10H6.5A1.5 1.5 0 0 0 5 11.5v8A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5v-8a1.5 1.5 0 0 0-1.5-1.5H16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
$addIcon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0786c4">
<title>Installa l'app Chiamate</title>
<link rel="icon" href="<?= h(app_path('icon.php?s=192')) ?>">
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
</head>
<body class="install">
<main class="install-main">
  <header class="install-head">
    <img src="<?= h(app_path('icon.php?s=192')) ?>" alt="" width="72" height="72">
    <h1>Installa l'app <span>Chiamate</span></h1>
    <p class="muted">Per i camerieri: ricevi le chiamate dei tavoli con suono e notifiche, anche a telefono bloccato.</p>
  </header>

  <div class="install-tabs" role="tablist">
    <button class="on" data-os="ios" role="tab">🍎 iPhone</button>
    <button data-os="android" role="tab">🤖 Android</button>
  </div>

  <section class="card install-os" id="os-ios">
    <ol class="install-steps">
      <li><span class="n">1</span><div><strong>Apri questa pagina con Safari</strong>
        <small>L'indirizzo è <b><?= h(preg_replace('#^https?://#', '', $appUrl)) ?></b>. Serve iOS 16.4 o successivo (Impostazioni › Generali › Info).</small></div></li>
      <li><span class="n">2</span><div><strong>Tocca il pulsante Condividi <span class="ios-btn"><?= $shareIcon ?></span></strong>
        <small>È nella barra in basso di Safari (su iPad in alto).</small></div></li>
      <li><span class="n">3</span><div><strong>Scegli «Aggiungi alla schermata Home» <span class="ios-btn"><?= $addIcon ?></span></strong>
        <small>Se non lo vedi, scorri l'elenco verso il basso.</small></div></li>
      <li><span class="n">4</span><div><strong>Tocca «Aggiungi»</strong>
        <small>In alto a destra. Sulla Home compare l'icona <b>Chiamate</b>.</small></div></li>
      <li><span class="n">5</span><div><strong>Apri Chiamate dalla Home e accedi</strong>
        <small>Poi tocca <b>«Attiva»</b> e, quando iPhone lo chiede, <b>«Consenti»</b> le notifiche.</small></div></li>
    </ol>
    <p class="notice small">Le notifiche su iPhone arrivano solo se l'app è aperta dall'icona sulla Home, non da Safari.</p>
    <a class="btn primary big block" href="<?= h(app_path('cameriere/')) ?>">Apri l'app del cameriere</a>
  </section>

  <section class="card install-os" id="os-android" hidden>
    <?php if ($apk): ?>
    <ol class="install-steps">
      <li><span class="n">1</span><div><strong>Scarica l'app</strong>
        <a class="btn primary block" href="<?= h($apk) ?>" download="Chiamate-cameriere.apk">📱 Scarica Chiamate per Android</a></div></li>
      <li><span class="n">2</span><div><strong>Apri il file scaricato e tocca «Installa»</strong>
        <small>La prima volta Android chiede di consentire l'installazione da questa fonte: tocca «Impostazioni», attiva «Consenti» e torna indietro.</small></div></li>
      <li><span class="n">3</span><div><strong>Apri Chiamate e accedi</strong>
        <small>Poi tocca <b>«Attiva»</b> e consenti le notifiche.</small></div></li>
    </ol>
    <?php endif; ?>
    <p class="small muted">In alternativa: apri <b><?= h(preg_replace('#^https?://#', '', $appUrl)) ?></b> con Chrome, menu ⋮ › «Installa app» (o «Aggiungi a schermata Home»).</p>
  </section>

  <?php if ($qr): ?>
  <section class="card install-qr">
    <div class="qr-img"><?= preg_replace('/^.*?(<svg)/s', '$1', $qr) ?></div>
    <p><strong>Sei al computer?</strong><br>Inquadra il QR con la fotocamera del telefono per aprire questa guida.</p>
  </section>
  <?php endif; ?>
</main>
<?= powered_by() ?>
<script>
(function () {
  var tabs = document.querySelectorAll('.install-tabs button');
  function show(os) {
    tabs.forEach(function (b) { b.classList.toggle('on', b.dataset.os === os); });
    document.getElementById('os-ios').hidden = os !== 'ios';
    document.getElementById('os-android').hidden = os !== 'android';
  }
  tabs.forEach(function (b) { b.onclick = function () { show(b.dataset.os); }; });
  if (/Android/i.test(navigator.userAgent)) show('android');
})();
</script>
</body>
</html>
