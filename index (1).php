<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
$u = require_login(['czlonek', 'admin', 'opiekun', 'analityk']);

$projects = all("SELECT * FROM projects WHERE published=1 AND stage IN ('prezentacja','realizacja') ORDER BY FIELD(stage,'prezentacja','realizacja'), updated_at DESC");
$mine = all('SELECT i.created_at, i.status, p.id, p.name FROM interests i JOIN projects p ON p.id=i.project_id WHERE i.user_id=? ORDER BY i.created_at DESC', [$u['id']]);
$first = explode(' ', $u['name'])[0];

page_start('Projekty', is_staff() ? 'podglad' : 'pulpit');
?>
<?php if (is_staff()): ?><div class="flash">Widzisz panel tak, jak widzą go członkowie klubu.</div><?php endif; ?>
<div class="head">
  <div><p class="eyebrow">Dzień dobry, <?= e($first) ?></p><h1>Projekty w klubie</h1></div>
  <p class="muted small" style="max-width:520px">Materiały są poufne, mają charakter informacyjny i nie stanowią oferty publicznej ani rekomendacji inwestycyjnej. Umowy zawierasz bezpośrednio ze spółką projektową.</p>
</div>

<?php if (!$projects): ?>
  <div class="card"><h2>Brak projektów w prezentacji</h2><p class="muted">Gdy komitet selekcji dopuści nowy projekt, pojawi się tutaj, a opiekun klubu zaprosi Cię na prezentację.</p></div>
<?php else: ?>
  <div class="grid g3">
    <?php foreach ($projects as $p): ?>
      <a class="card" href="projekt.php?id=<?= (int)$p['id'] ?>" style="text-decoration:none;color:inherit">
        <div class="actions" style="justify-content:space-between">
          <span class="eyebrow"><?= $p['kind'] === 'grunt' ? 'Terrava Ziemia' : 'Terrava Projekty' ?></span>
          <?= stage_chip(PRJ_STAGES[$p['stage']], prj_tone($p['stage'])) ?>
        </div>
        <h2><?= e($p['name']) ?></h2>
        <p class="muted small"><?= e($p['location']) ?></p>
        <div class="facts" style="margin-top:auto">
          <div><span>Kapitał</span><b><?= e($p['capital_text'] ?: '—') ?></b></div>
          <div><span>Horyzont</span><b><?= e($p['horizon_text'] ?: '—') ?></b></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Moje zainteresowania</h2>
  <?php if (!$mine): ?><p class="muted">Nie zgłosiłeś jeszcze zainteresowania żadnym projektem.</p>
  <?php else: ?><div class="list">
    <?php foreach ($mine as $m): ?>
      <div><a href="projekt.php?id=<?= (int)$m['id'] ?>"><?= e($m['name']) ?></a>
        <span class="small muted"><?= e(['nowe' => 'Zgłoszone', 'przekazane' => 'Kontakt przekazany spółce', 'zamkniete' => 'Zamknięte'][$m['status']]) ?> · <?= fmt_date($m['created_at']) ?></span></div>
    <?php endforeach; ?>
  </div><?php endif; ?>
</div>
<?php page_end();
