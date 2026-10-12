<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
$u = require_login(['wlasciciel']);

$projects = all('SELECT * FROM projects WHERE owner_user_id=? ORDER BY created_at DESC', [$u['id']]);
$order = ['zgloszenie', 'ocena', 'weryfikacja', 'komitet', 'prezentacja'];

page_start('Mój projekt', 'wlasciciel');
?>
<div class="head"><div><p class="eyebrow">Panel właściciela projektu</p><h1>Twoje projekty w Terravie</h1></div></div>
<?php if (!$projects): ?>
  <div class="card"><p class="muted">Nie ma jeszcze projektu przypisanego do Twojego konta. Skontaktuj się z opiekunem.</p></div>
<?php endif; ?>
<?php foreach ($projects as $p):
    $docs = all('SELECT * FROM project_docs WHERE project_id=? ORDER BY id', [$p['id']]);
    $acc = count(array_filter($docs, fn($d) => $d['status'] === 'przyjety'));
    $pos = array_search($p['stage'], $order, true); if ($pos === false) $pos = count($order);
    $interested = (int)val("SELECT COUNT(*) FROM interests WHERE project_id=? AND status IN ('przekazane','zamkniete')", [$p['id']]);
?>
<section class="card">
  <div class="head">
    <div><h2><?= e($p['name']) ?></h2><p class="muted small"><?= e($p['location']) ?> · Pakiet: <?= e(PACKAGES[$p['package']]) ?></p></div>
    <?= stage_chip(PRJ_STAGES[$p['stage']], prj_tone($p['stage'])) ?>
  </div>
  <ol class="grid g4" style="list-style:none;margin:0;padding:0" aria-label="Etapy">
    <?php foreach ($order as $i => $s): $state = $i < $pos ? 'Zakończono' : ($i === $pos ? 'W toku' : 'Następny krok'); ?>
      <li style="border-top:3px solid <?= $i < $pos ? 'var(--navy)' : ($i === $pos ? 'var(--copper)' : 'var(--line)') ?>;padding-top:10px;display:flex;flex-direction:column;gap:2px">
        <span class="small" style="color:<?= $i === $pos ? 'var(--copper-text)' : 'var(--muted)' ?>;font-weight:500"><?= $state ?></span>
        <b><?= e(PRJ_STAGES[$s]) ?></b>
      </li>
    <?php endforeach; ?>
  </ol>
  <div class="grid g2">
    <div>
      <div class="head" style="margin-bottom:8px"><h3>Dokumenty do weryfikacji</h3><span class="muted small"><?= $acc ?> z <?= count($docs) ?> przyjętych</span></div>
      <div class="meter" role="img" aria-label="<?= $acc ?> z <?= count($docs) ?> dokumentów przyjętych"><i class="a" style="width:<?= count($docs) ? round($acc / count($docs) * 100) : 0 ?>%"></i></div>
      <div class="list" style="margin-top:8px">
        <?php foreach ($docs as $d): ?>
          <div><span><?= e($d['name']) ?></span><?= stage_chip(DOC_STATUS[$d['status']], $d['status'] === 'przyjety' ? 'solid' : ($d['status'] === 'do_poprawy' ? 'copper' : 'muted')) ?></div>
        <?php endforeach; ?>
      </div>
      <p class="muted small" style="margin-top:8px">Dokumenty przesyłaj opiekunowi e-mailem; po sprawdzeniu zmieni ich status tutaj.</p>
    </div>
    <div class="card dark">
      <h3>Po prezentacji</h3>
      <div class="kpi"><b><?= $interested ?></b><span>członków, których kontakt przekazaliśmy Twojej spółce</span></div>
      <p class="muted small">Rozmowy i umowy z inwestorami prowadzi Twoja spółka. Terrava nie przyjmuje wpłat.</p>
    </div>
  </div>
</section>
<?php endforeach; ?>
<?php page_end();
