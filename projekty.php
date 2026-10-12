<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(STAFF);

$rows = all("SELECT p.*, u.name analityk,
  (SELECT COUNT(*) FROM project_docs d WHERE d.project_id=p.id AND d.status='przyjety') acc,
  (SELECT COUNT(*) FROM project_docs d WHERE d.project_id=p.id) docs,
  (SELECT COUNT(*) FROM interests i WHERE i.project_id=p.id) ints
  FROM projects p LEFT JOIN users u ON u.id=p.analyst_user_id ORDER BY p.updated_at DESC");
$board = []; foreach ($rows as $r) $board[$r['stage']][] = $r;

page_start('Projekty', 'projekty');
?>
<div class="head"><div><p class="eyebrow">Lejek projektów</p><h1>Projekty</h1></div>
  <?php if (can_edit_projects()): ?><a class="btn" href="projekt.php?new=1">Dodaj projekt</a><?php endif; ?></div>

<div class="board" aria-label="Etapy projektów">
  <?php foreach (['zgloszenie', 'ocena', 'weryfikacja', 'komitet', 'prezentacja', 'realizacja'] as $s): ?>
    <section class="col"><h3><?= e(PRJ_STAGES[$s]) ?><span><?= count($board[$s] ?? []) ?></span></h3>
      <?php foreach ($board[$s] ?? [] as $r): ?>
        <a class="tile" href="projekt.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b><span class="muted"><?= e($r['location']) ?></span>
          <span class="small">Dokumenty <?= (int)$r['acc'] ?>/<?= (int)$r['docs'] ?><?= $r['ints'] ? ' · zainteresowani ' . (int)$r['ints'] : '' ?><?= $r['published'] ? ' · opublikowany' : '' ?></span></a>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>

<div class="tbl-wrap"><table>
  <thead><tr><th>Projekt</th><th>Etap</th><th>Rodzaj</th><th>Pakiet</th><th>Analityk</th><th>Dokumenty</th><th>Zainteresowani</th><th>Widoczny</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a class="row" href="projekt.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a><br><span class="small muted"><?= e($r['location']) ?></span></td>
      <td><?= stage_chip(PRJ_STAGES[$r['stage']], prj_tone($r['stage'])) ?></td>
      <td><?= $r['kind'] === 'grunt' ? 'Grunt' : 'Projekt' ?></td><td><?= e(PACKAGES[$r['package']]) ?></td>
      <td><?= e($r['analityk'] ?? '—') ?></td><td class="num"><?= (int)$r['acc'] ?> / <?= (int)$r['docs'] ?></td>
      <td class="num"><?= (int)$r['ints'] ?></td><td><?= $r['published'] ? 'Tak' : 'Nie' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">Brak projektów. Zgłoszenia z formularza na stronie pojawią się tutaj automatycznie.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php page_end();
