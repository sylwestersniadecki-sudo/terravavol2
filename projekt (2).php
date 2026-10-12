<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
$u = require_login(['czlonek', 'admin', 'opiekun', 'analityk']);

$id = (int)($_GET['id'] ?? 0);
$p = one('SELECT * FROM projects WHERE id=?', [$id]);
$visible = $p && $p['published'] && in_array($p['stage'], ['prezentacja', 'realizacja'], true);
if (!$p || (!$visible && !is_staff())) { http_response_code(404); page_start('Nie znaleziono'); echo '<div class="card"><h1>Projekt niedostępny</h1><p><a href="index.php">Wróć do listy projektów</a></p></div>'; page_end(); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $u['role'] === 'czlonek') {
    csrf_check();
    $msg = post('message', 2000);
    q('INSERT IGNORE INTO interests (project_id, user_id, message) VALUES (?,?,?)', [$id, $u['id'], $msg]);
    notify_team('Terrava: zainteresowanie projektem ' . $p['name'], $u['name'] . ' (' . $u['email'] . ") zgłosił zainteresowanie projektem: {$p['name']}\n\n$msg");
    flash('Dziękujemy. Opiekun klubu skontaktuje się z Tobą i umówi rozmowę ze spółką projektową.');
    redirect('projekt.php?id=' . $id);
}

$docs = all('SELECT * FROM project_docs WHERE project_id=? AND visible=1 AND stored_name IS NOT NULL ORDER BY id', [$id]);
$already = one('SELECT status, created_at FROM interests WHERE project_id=? AND user_id=?', [$id, $u['id']]);

page_start($p['name'], is_staff() ? 'podglad' : 'pulpit');
?>
<?php if (is_staff() && !$visible): ?><div class="flash err">Ten projekt nie jest opublikowany. Członkowie go nie widzą.</div><?php endif; ?>
<p><a href="index.php">← Wszystkie projekty</a></p>
<div class="head">
  <div style="display:flex;flex-direction:column;gap:8px">
    <span class="eyebrow"><?= $p['kind'] === 'grunt' ? 'Terrava Ziemia' : 'Terrava Projekty' ?></span>
    <h1><?= e($p['name']) ?></h1>
    <p class="muted"><?= e($p['location']) ?><?= $p['company'] ? ' · ' . e($p['company']) : '' ?></p>
  </div>
  <div class="actions"><?= stage_chip(PRJ_STAGES[$p['stage']], prj_tone($p['stage'])) ?><?php if ($p['committee_date']): ?><span class="chip muted">Komitet: <?= fmt_date($p['committee_date']) ?></span><?php endif; ?></div>
</div>

<div class="side">
  <div style="display:flex;flex-direction:column;gap:24px;min-width:0">
    <div class="facts">
      <div><span>Kapitał od inwestorów</span><b><?= e($p['capital_text'] ?: '—') ?></b></div>
      <div><span>Typowe wejście</span><b><?= e($p['ticket_text'] ?: '—') ?></b></div>
      <div><span>Forma</span><b><?= e($p['form_text'] ?: '—') ?></b></div>
      <div><span>Horyzont</span><b><?= e($p['horizon_text'] ?: '—') ?></b></div>
      <div><span>Zabezpieczenie</span><b><?= e($p['security_text'] ?: '—') ?></b></div>
    </div>
    <div class="card">
      <h2>Analiza Terravy</h2>
      <p class="prose"><?= e($p['summary'] ?: 'Analiza zostanie uzupełniona przed prezentacją.') ?></p>
    </div>
    <div class="card">
      <h2>Ryzyka</h2>
      <p class="prose"><?= e($p['risks'] ?: '—') ?></p>
      <p class="risk">Wypłata zależy od wyników spółki projektowej. Możliwa jest utrata części lub całości zainwestowanego kapitału.</p>
    </div>
    <div class="card">
      <div class="head"><h2>Pokój danych</h2><span class="muted small">Pobrania są rejestrowane</span></div>
      <?php if (!$docs): ?><p class="muted">Dokumenty zostaną udostępnione przed prezentacją.</p>
      <?php else: ?><div class="list"><?php foreach ($docs as $d): ?>
        <div><span><?= e($d['name']) ?></span><a class="btn ghost sm" href="plik.php?id=<?= (int)$d['id'] ?>">Pobierz</a></div>
      <?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
  <aside class="card" style="position:sticky;top:84px">
    <h2>Zainteresowany projektem?</h2>
    <p class="small">Przekażemy Twój kontakt spółce projektowej i umówimy rozmowę. Warunki ustalasz bezpośrednio ze spółką.</p>
    <p class="risk">Inwestycja w projekt deweloperski wiąże się z ryzykiem utraty kapitału. Terrava nie przyjmuje wpłat, nie jest stroną umowy i nie udziela rekomendacji inwestycyjnych.</p>
    <?php if ($already): ?>
      <div class="flash">Zgłosiłeś zainteresowanie <?= fmt_date($already['created_at']) ?>. Opiekun klubu jest w kontakcie.</div>
    <?php elseif ($u['role'] === 'czlonek'): ?>
      <form method="post" class="f">
        <?= csrf_field() ?>
        <div class="field"><label class="l" for="message">Wiadomość (opcjonalnie)</label><textarea id="message" name="message" placeholder="Np. preferowana kwota, pytania do dewelopera"></textarea></div>
        <button class="btn copper">Zgłoś zainteresowanie</button>
      </form>
    <?php else: ?>
      <p class="muted small">Przycisk zgłoszenia widzą członkowie klubu.</p>
    <?php endif; ?>
  </aside>
</div>
<?php page_end();
