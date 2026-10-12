<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(STAFF);

$used = seats_used(); $limit = seat_limit();
$members = (int)val("SELECT COUNT(*) FROM investors WHERE stage='czlonek'");
$invited = $used - $members;
$invByStage = array_column(all('SELECT stage, COUNT(*) c FROM investors GROUP BY stage'), 'c', 'stage');
$prjByStage = array_column(all('SELECT stage, COUNT(*) c FROM projects GROUP BY stage'), 'c', 'stage');
$todo = all("SELECT id, name, next_action, next_action_date FROM investors WHERE next_action_date IS NOT NULL AND next_action_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND stage NOT IN ('odrzucony') AND (owner_user_id=? OR ?) ORDER BY next_action_date LIMIT 12", [$u['id'], $u['role'] === 'admin' ? 1 : 0]);
$fees = all("SELECT id, name, fee_until FROM investors WHERE stage='czlonek' AND (fee_until IS NULL OR fee_until <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) ORDER BY fee_until IS NULL DESC, fee_until LIMIT 12");
$interests = all("SELECT i.id, i.created_at, u.name uname, p.id pid, p.name pname FROM interests i JOIN users u ON u.id=i.user_id JOIN projects p ON p.id=i.project_id WHERE i.status='nowe' ORDER BY i.created_at DESC LIMIT 10");
$newProjects = all("SELECT id, name, location, created_at FROM projects WHERE stage='zgloszenie' ORDER BY created_at DESC LIMIT 8");

page_start('Pulpit CRM', 'crm');
?>
<div class="head"><div><p class="eyebrow">CRM Terrava</p><h1>Pulpit</h1></div>
  <div class="actions"><a class="btn" href="inwestor.php?new=1">Dodaj inwestora</a><a class="btn ghost" href="projekt.php?new=1">Dodaj projekt</a></div></div>

<div class="grid g3">
  <div class="card dark">
    <div class="head"><h2>Miejsca w klubie</h2><span class="kpi"><b><?= $used ?> / <?= $limit ?></b></span></div>
    <div class="meter" role="img" aria-label="<?= $used ?> z <?= $limit ?> miejsc zajętych" style="background:#2A4361"><i class="a" style="width:<?= round($members / $limit * 100, 2) ?>%;background:#F8F7F4"></i><i class="b" style="width:<?= round($invited / $limit * 100, 2) ?>%"></i></div>
    <p class="muted small">Członkowie: <?= $members ?> · zaproszenia w toku: <?= $invited ?> · wolne: <?= max(0, $limit - $used) ?></p>
  </div>
  <div class="card">
    <h2>Lejek inwestorów</h2>
    <div class="list small"><?php foreach (INV_STAGES as $k => $l): ?><div><span><?= e($l) ?></span><b><?= (int)($invByStage[$k] ?? 0) ?></b></div><?php endforeach; ?></div>
  </div>
  <div class="card">
    <h2>Lejek projektów</h2>
    <div class="list small"><?php foreach (PRJ_STAGES as $k => $l): ?><div><span><?= e($l) ?></span><b><?= (int)($prjByStage[$k] ?? 0) ?></b></div><?php endforeach; ?></div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h2><?= $u['role'] === 'admin' ? 'Zadania na 7 dni' : 'Moje zadania na 7 dni' ?></h2>
    <?php if (!$todo): ?><p class="muted">Brak zaplanowanych działań.</p><?php else: ?><div class="list"><?php foreach ($todo as $t): ?>
      <div><a href="inwestor.php?id=<?= (int)$t['id'] ?>"><?= e($t['name']) ?></a><span class="small"><?= e($t['next_action']) ?> · <b style="color:<?= $t['next_action_date'] < date('Y-m-d') ? 'var(--err)' : 'var(--ink)' ?>"><?= fmt_date($t['next_action_date']) ?></b></span></div>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="card">
    <h2>Nowe zainteresowania projektami</h2>
    <?php if (!$interests): ?><p class="muted">Brak nowych zgłoszeń od członków.</p><?php else: ?><div class="list"><?php foreach ($interests as $i): ?>
      <div><span><b><?= e($i['uname']) ?></b> → <a href="projekt.php?id=<?= (int)$i['pid'] ?>#zainteresowania"><?= e($i['pname']) ?></a></span><span class="small muted"><?= fmt_date($i['created_at']) ?></span></div>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="card">
    <h2>Składki do odnowienia (30 dni)</h2>
    <?php if (!$fees): ?><p class="muted">Wszystkie składki są opłacone dłużej niż 30 dni.</p><?php else: ?><div class="list"><?php foreach ($fees as $f): ?>
      <div><a href="inwestor.php?id=<?= (int)$f['id'] ?>"><?= e($f['name']) ?></a><span class="small"><?= $f['fee_until'] ? 'do ' . fmt_date($f['fee_until']) : 'brak daty składki' ?></span></div>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="card">
    <h2>Nowe zgłoszenia projektów</h2>
    <?php if (!$newProjects): ?><p class="muted">Brak nowych zgłoszeń.</p><?php else: ?><div class="list"><?php foreach ($newProjects as $p): ?>
      <div><a href="projekt.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a><span class="small muted"><?= e($p['location']) ?> · <?= fmt_date($p['created_at']) ?></span></div>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<?php page_end();
