<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(STAFF);

$used = seats_used(); $limit = seat_limit();
$rows = all("SELECT i.*, us.id uid, us.active uactive, us.last_login FROM investors i LEFT JOIN users us ON us.investor_id=i.id AND us.role='czlonek' WHERE i.stage IN ('zaproszony','czlonek') ORDER BY FIELD(i.stage,'czlonek','zaproszony'), i.fee_until IS NULL DESC, i.fee_until");
$waiting = (int)val("SELECT COUNT(*) FROM investors WHERE stage='wstrzymany'");
$members = count(array_filter($rows, fn($r) => $r['stage'] === 'czlonek'));
$soon = date('Y-m-d', strtotime('+30 days'));

page_start('Członkowie', 'czlonkowie');
?>
<div class="head"><div><p class="eyebrow">Klub</p><h1>Członkowie i składki</h1></div></div>
<div class="grid g3">
  <div class="card dark"><div class="kpi"><b><?= $used ?> / <?= $limit ?></b><span>zajęte miejsca (członkowie i zaproszenia w toku)</span></div>
    <div class="meter" role="img" aria-label="<?= $used ?> z <?= $limit ?> miejsc" style="background:#2A4361"><i class="a" style="width:<?= round($members / $limit * 100, 2) ?>%;background:#F8F7F4"></i><i class="b" style="width:<?= round(($used - $members) / $limit * 100, 2) ?>%"></i></div></div>
  <div class="card"><div class="kpi"><b><?= max(0, $limit - $used) ?></b><span>wolnych miejsc</span></div></div>
  <div class="card"><div class="kpi"><b><?= $waiting ?></b><span>osób na liście oczekujących (etap „Wstrzymany”)</span></div></div>
</div>
<div class="tbl-wrap"><table>
  <thead><tr><th>Osoba</th><th>Status</th><th>Członek od</th><th>Składka do</th><th>NDA</th><th>Ankieta</th><th>Konto</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $fee = $r['fee_until']; $late = $r['stage'] === 'czlonek' && (!$fee || $fee < date('Y-m-d')); $due = $fee && !$late && $fee <= $soon; ?>
    <tr><td><a class="row" href="inwestor.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a><br><span class="small muted"><?= e($r['city']) ?></span></td>
      <td><?= stage_chip(INV_STAGES[$r['stage']], inv_tone($r['stage'])) ?></td>
      <td class="num"><?= fmt_date($r['member_since']) ?></td>
      <td class="num" style="color:<?= $late ? 'var(--err)' : ($due ? 'var(--copper-text)' : 'inherit') ?>;font-weight:<?= $late || $due ? 600 : 400 ?>"><?= $fee ? fmt_date($fee) : '—' ?><?= $late ? ' · do opłacenia' : ($due ? ' · wkrótce' : '') ?></td>
      <td><?= $r['nda'] ? 'Tak' : 'Nie' ?></td><td><?= $r['survey'] ? 'Tak' : 'Nie' ?></td>
      <td class="small"><?= $r['uid'] ? ($r['uactive'] ? 'Aktywne' . ($r['last_login'] ? ', log. ' . fmt_date($r['last_login']) : ', bez logowania') : 'Zablokowane') : '<span class="muted">brak</span>' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">Brak członków i zaproszeń.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php page_end();
