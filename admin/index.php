<?php
/** Venue settings: name, colour, logo, menu PDF, code length, bill request. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

$user = require_role(['manager', 'superadmin']);
$venueId = (int) current_venue_id();
$venue = venue($venueId);
$dir = __DIR__ . '/../storage/v' . $venueId;

const REMIND_OPTIONS = [0 => 'Mai', 30 => 'Dopo 30 secondi', 60 => 'Dopo 1 minuto', 90 => 'Dopo 1 minuto e mezzo', 120 => 'Dopo 2 minuti'];
const ESCALATE_OPTIONS = [0 => 'Mai', 60 => 'Dopo 1 minuto', 120 => 'Dopo 2 minuti', 180 => 'Dopo 3 minuti', 300 => 'Dopo 5 minuti'];

/** Saves an uploaded file in the venue folder; returns the new file name or an error string in $err. */
function save_upload(string $field, array $allowed, int $maxBytes, string $prefix, string $dir, ?string &$err): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) { $err = 'Caricamento non riuscito (file troppo grande?).'; return null; }
    if ($f['size'] > $maxBytes) { $err = 'File troppo grande (massimo ' . round($maxBytes / 1048576) . ' MB).'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) { $err = 'Formato non valido.'; return null; }
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) { $err = 'Cartella di salvataggio non scrivibile.'; return null; }
    $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { $err = 'Salvataggio non riuscito.'; return null; }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $err = null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['color'] ?? '')) ? strtolower($_POST['color']) : $venue['color'];
    if (!empty($_POST['brand_color'])) $color = BRAND_COLOR;
    $menuUrl = trim((string) ($_POST['menu_url'] ?? ''));
    if ($menuUrl !== '' && !preg_match('#^https?://#i', $menuUrl)) $err = 'Il link del menu deve iniziare con https://';
    if ($name === '') $err = 'Il nome del locale è obbligatorio.';

    $logo = $venue['logo_file'];
    $menu = $venue['menu_file'];
    if (!$err && ($new = save_upload('logo', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 3 << 20, 'logo', $dir, $err))) {
        if ($logo) @unlink($dir . '/' . $logo);
        $logo = $new;
    }
    if (!$err && ($new = save_upload('menu', ['application/pdf' => 'pdf'], 25 << 20, 'menu', $dir, $err))) {
        if ($menu) @unlink($dir . '/' . $menu);
        $menu = $new;
    }
    if (!empty($_POST['remove_logo']) && $logo) { @unlink($dir . '/' . $logo); $logo = null; }
    if (!empty($_POST['remove_menu']) && $menu) { @unlink($dir . '/' . $menu); $menu = null; }

    if ($err) {
        flash($err, 'err');
    } else {
        db()->prepare('UPDATE venues SET name = ?, color = ?, welcome_text = ?, logo_file = ?, menu_file = ?, menu_url = ?,
                              code_length = ?, bill_enabled = ?, bill_ask_payment = ?, remind_after = ?, escalate_after = ? WHERE id = ?')
            ->execute([
                mb_substr($name, 0, 120), $color, mb_substr(trim((string) ($_POST['welcome_text'] ?? '')), 0, 500) ?: null,
                $logo, $menu, $menuUrl ?: null,
                max(3, min(6, (int) ($_POST['code_length'] ?? 4))),
                empty($_POST['bill_enabled']) ? 0 : 1, empty($_POST['bill_ask_payment']) ? 0 : 1,
                in_array((int) ($_POST['remind_after'] ?? 60), array_keys(REMIND_OPTIONS), true) ? (int) $_POST['remind_after'] : 60,
                in_array((int) ($_POST['escalate_after'] ?? 120), array_keys(ESCALATE_OPTIONS), true) ? (int) $_POST['escalate_after'] : 120,
                $venueId,
            ]);
        flash('Impostazioni salvate.');
    }
    redirect('admin/');
}

$counts = db()->prepare('SELECT (SELECT COUNT(*) FROM venue_tables WHERE venue_id = ? AND active = 1) AS tables,
                                (SELECT COUNT(*) FROM users WHERE venue_id = ? AND active = 1) AS staff');
$counts->execute([$venueId, $venueId]);
$counts = $counts->fetch();

page_head('Impostazioni');
admin_nav('settings');
?>
<main class="wrap">
  <?php if (!$counts['tables'] || $counts['staff'] < 2): ?>
  <div class="card steps">
    <h2>Configurazione rapida</h2>
    <?php if ($video = demo_video_url()): ?>
      <p class="small"><a href="<?= h($video) ?>" download="Chiamata-video-guida.mp4">▶ Scarica il video guida</a>: tutti i passi in 3 minuti, e un esempio cliente–cameriere.</p>
    <?php endif; ?>
    <ol>
      <li class="<?= $counts['tables'] ? 'done' : '' ?>"><a href="<?= h(app_path('admin/tables.php')) ?>">Crea i tavoli</a> (anche tutti insieme: Tavolo 1…30)</li>
      <li><a href="<?= h(app_path('admin/qr.php')) ?>">Stampa i QR</a> e mettili sui tavoli</li>
      <li class="<?= $counts['staff'] > 1 ? 'done' : '' ?>"><a href="<?= h(app_path('admin/staff.php')) ?>">Aggiungi i camerieri</a></li>
      <li>Ogni cameriere installa l'app sul telefono (<a href="<?= h(app_path('cameriere/installa.php')) ?>" target="_blank">guida per iPhone e Android</a>) e tocca "Attiva"</li>
    </ol>
  </div>
  <?php endif; ?>

  <form class="card form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>Locale</h2>
    <label>Nome del locale<input name="name" required maxlength="120" value="<?= h($venue['name']) ?>"></label>
    <label>Colore della pagina clienti<input type="color" name="color" value="<?= h($venue['color']) ?>"></label>
    <label class="check"><input type="checkbox" name="brand_color" value="1"<?= strtolower($venue['color']) === BRAND_COLOR ? ' checked' : '' ?>> Usa i colori Upgrade (azzurro del logo)</label>
    <label>Messaggio di benvenuto (facoltativo)<textarea name="welcome_text" rows="2" maxlength="500"><?= h($venue['welcome_text']) ?></textarea></label>
    <label>Logo (PNG, JPG o WEBP, max 3 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
    <?php if ($venue['logo_file']): ?>
      <div class="preview"><img src="<?= h(app_path('file.php?v=' . $venueId . '&f=logo&h=' . substr(md5($venue['logo_file']), 0, 8))) ?>" alt="Logo">
        <label class="check"><input type="checkbox" name="remove_logo" value="1"> Rimuovi logo</label></div>
    <?php endif; ?>

    <h2>Menu</h2>
    <label>Menu in PDF (max 25 MB)<input type="file" name="menu" accept="application/pdf"></label>
    <?php if ($venue['menu_file']): ?>
      <p class="small">Menu caricato. <a href="<?= h(app_path('file.php?v=' . $venueId . '&f=menu')) ?>" target="_blank">Apri il PDF</a>
        <label class="check"><input type="checkbox" name="remove_menu" value="1"> Rimuovi menu</label></p>
    <?php endif; ?>
    <label>Oppure link a un menu online (usato se non c'è il PDF)<input name="menu_url" type="url" placeholder="https://…" value="<?= h($venue['menu_url']) ?>"></label>

    <h2>Chiamate</h2>
    <label>Cifre del codice tavolo
      <select name="code_length">
        <?php for ($i = 3; $i <= 6; $i++): ?><option value="<?= $i ?>"<?= (int) $venue['code_length'] === $i ? ' selected' : '' ?>><?= $i ?> cifre</option><?php endfor; ?>
      </select>
      <span class="small muted">Vale per i nuovi codici, generati a ogni chiusura conto.</span>
    </label>
    <label class="check"><input type="checkbox" name="bill_enabled" value="1"<?= $venue['bill_enabled'] ? ' checked' : '' ?>> Il cliente può chiedere il conto</label>
    <label class="check"><input type="checkbox" name="bill_ask_payment" value="1"<?= $venue['bill_ask_payment'] ? ' checked' : '' ?>> Chiedi se paga in contanti o con carta</label>

    <h2>Se nessuno risponde</h2>
    <p class="small muted">Quando nessun cameriere tocca "Prendo io".</p>
    <label>Ricorda la chiamata agli stessi camerieri
      <select name="remind_after">
        <?php foreach (REMIND_OPTIONS as $s => $label): ?><option value="<?= $s ?>"<?= (int) $venue['remind_after'] === $s ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Avvisa tutto il personale, anche chi segue altri tavoli (e ripeti finché qualcuno risponde)
      <select name="escalate_after">
        <?php foreach (ESCALATE_OPTIONS as $s => $label): ?><option value="<?= $s ?>"<?= (int) $venue['escalate_after'] === $s ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select>
    </label>

    <button class="btn primary">Salva</button>
  </form>
</main>
<?php
page_foot();
