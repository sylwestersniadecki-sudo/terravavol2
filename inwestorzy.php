<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(STAFF);

$qtxt = trim((string)($_GET['q'] ?? '')); $st = (string)($_GET['etap'] ?? '');
$where = []; $par = [];
if ($qtxt !== '') { $where[] = '(i.name LIKE ? OR i.email LIKE ? OR i.company LIKE ? OR i.city LIKE ?)'; $like = '%' . $qtxt . '%'; array_push($par, $like, $like, $like, $like); }
if (isset(INV_STAGES[$st])) { $where[] = 'i.stage=?'; $par[] = $st; }
$rows = all('SELECT i.*, u.name opiekun FROM investors i LEFT JOIN users u ON u.id=i.owner_user_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.updated_at DESC LIMIT 500', $par);

$board = [];
foreach (all("SELECT id, name, city, stage, next_action, next_action_date FROM investors WHERE stage IN ('kontakt','rozmowa','zaproszony') ORDER BY next_action_date IS NULL, next_action_date, updated_at DESC") as $r) $board[$r['stage']][] = $r;

page_start('Inwestorzy', 'inwestorzy');
?>
<div class="head"><div><p class="eyebrow">Lejek inwestorów</p><h1>Inwestorzy</h1></div>
  <?php if (can_edit_investors()): ?><a class="btn" href="inwestor.php?new=1">Dodaj inwestora</a><?php endif; ?></div>

<div class="board" aria-label="Etapy pozyskania">
  <?php foreach (['kontakt', 'rozmowa', 'zaproszony'] as $s): ?>
    <section class="col"><h3><?= e(INV_STAGES[$s]) ?><span><?= count($board[$s] ?? []) ?></span></h3>
      <?php foreach ($board[$s] ?? [] as $r): ?>
        <a class="tile" href="inwestor.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b><span class="muted"><?= e($r['city']) ?></span>
          <?php if ($r['next_action_date']): ?><span class="due"><?= e($r['next_action']) ?> · <?= fmt_date($r['next_action_date']) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>

<form method="get" class="actions" role="search">
  <label class="l" for="q" style="position:absolute;left:-9999px">Szukaj</label>
  <input type="search" id="q" name="q" placeholder="Szukaj: nazwisko, e-mail, firma, miasto" value="<?= e($qtxt) ?>" style="max-width:360px">
  <select name="etap" aria-label="Etap" style="max-width:200px"><option value="">Wszystkie etapy</option><?= options(INV_STAGES, $st) ?></select>
  <button class="btn ghost">Filtruj</button>
</form>
<div class="tbl-wrap"><table>
  <thead><tr><th>Osoba</th><th>Etap</th><th>Miasto</th><th>Opiekun</th><th>Następny krok</th><th>Źródło</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a class="row" href="inwestor.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a><br><span class="small muted"><?= e($r['email']) ?></span></td>
      <td><?= stage_chip(INV_STAGES[$r['stage']], inv_tone($r['stage'])) ?></td>
      <td><?= e($r['city']) ?></td><td><?= e($r['opiekun'] ?? '—') ?></td>
      <td class="small"><?= e($r['next_action'] ?: '—') ?><?= $r['next_action_date'] ? ' · ' . fmt_date($r['next_action_date']) : '' ?></td>
      <td class="small"><?= e($r['source']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Brak inwestorów spełniających kryteria.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php page_end();
