<?php
/** Waiter app (installable PWA): live requests, table codes, bill closure. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/push.php';

$user = require_role(['waiter', 'manager', 'superadmin']);
$venue = venue((int) current_venue_id());

$config = [
    'api'   => app_path('api/waiter.php'),
    'csrf'  => csrf_token(),
    'vapid' => vapid_public_key(),
    'admin' => in_array($user['role'], ['manager', 'superadmin'], true) ? app_path('admin/') : null,
];
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f766e">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Chiamate">
<title>Chiamate · <?= h($venue['name']) ?></title>
<link rel="manifest" href="<?= h(app_path('waiter/manifest.php')) ?>">
<link rel="apple-touch-icon" href="<?= h(app_path('icon.php?s=180')) ?>">
<link rel="icon" href="<?= h(app_path('icon.php?s=192')) ?>">
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
</head>
<body class="waiter">
<header class="w-head">
  <div class="w-title">
    <strong><?= h($venue['name']) ?></strong>
    <span class="muted small"><?= h($user['name']) ?></span>
  </div>
  <span id="conn" class="conn" title="Connessione"></span>
  <button class="icon-btn" id="menuBtn" aria-label="Opzioni">☰</button>
</header>

<nav class="w-tabs">
  <button class="on" data-tab="calls">Richieste <span class="badge" id="callCount" hidden>0</span></button>
  <button data-tab="tables">Tavoli e codici</button>
</nav>

<div id="enable" class="w-enable" hidden>
  <p><strong>Attiva suono e notifiche</strong><br><span class="small" id="enableText">Tocca qui per sentire le chiamate e riceverle anche a telefono bloccato.</span></p>
  <button class="btn primary" id="enableBtn">Attiva</button>
</div>

<main>
  <section id="tab-calls" class="w-list"></section>
  <section id="tab-tables" hidden>
    <div class="w-filter"><input id="tableSearch" type="search" placeholder="Cerca tavolo…"></div>
    <div id="tableGrid" class="t-grid"></div>
  </section>
</main>

<dialog id="menuDialog" class="sheet">
  <h2>Opzioni</h2>
  <div id="zoneBox">
    <p class="small muted">Zone che segui (nessuna = tutte). Ricevi solo le chiamate di queste zone.</p>
    <div id="zoneList" class="chips"></div>
  </div>
  <p class="small" id="pushState"></p>
  <button class="btn block" id="pushTest">Invia notifica di prova</button>
  <?php if ($config['admin']): ?><a class="btn block" href="<?= h($config['admin']) ?>">Gestione locale</a><?php endif; ?>
  <a class="btn block" href="<?= h(app_path('logout.php')) ?>">Esci</a>
  <button class="btn ghost block" data-close>Chiudi</button>
</dialog>

<dialog id="closeDialog" class="sheet">
  <h2 id="closeTitle">Chiudere il tavolo?</h2>
  <p class="small">Il codice attuale smette di funzionare e il tavolo riceve un nuovo codice per i prossimi clienti.</p>
  <button class="btn danger block" id="closeYes">Sì, chiudi e genera nuovo codice</button>
  <button class="btn block" id="closeNo">Segna solo come fatto</button>
  <button class="btn ghost block" data-close>Annulla</button>
</dialog>

<p class="toast" id="toast" hidden></p>
<script>window.WAITER = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(asset('assets/waiter.js')) ?>"></script>
</body>
</html>
