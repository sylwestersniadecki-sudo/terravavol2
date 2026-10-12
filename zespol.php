<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = post('action', 20);
    if ($act === 'add') {
        $role = post('role', 20);
        if (!in_array($role, STAFF, true)) flash('Wybierz rolę.', 'err');
        elseif (post('name', 160) === '') flash('Podaj imię i nazwisko.', 'err');
        else create_account(post('email', 190), post('name', 160), $role);
    } else {
        $tid = (int)post('user_id', 10);
        if ($tid === (int)$u['id']) flash('Nie możesz zmienić własnego konta w tym miejscu.', 'err');
        elseif ($act === 'toggle') q('UPDATE users SET active=1-active WHERE id=?', [$tid]);
        elseif ($act === 'reset') reset_password($tid);
        elseif ($act === 'role') { $role = post('role', 20); if (isset(ROLE_LABEL[$role])) q('UPDATE users SET role=? WHERE id=? AND role IN (\'admin\',\'opiekun\',\'analityk\')', [$role, $tid]); }
    }
    redirect('zespol.php');
}

$users = all('SELECT * FROM users ORDER BY FIELD(role,\'admin\',\'opiekun\',\'analityk\',\'czlonek\',\'wlasciciel\'), name');
$staffRoles = array_intersect_key(ROLE_LABEL, array_flip(STAFF));
page_start('Zespół i konta', 'zespol');
?>
<div class="head"><div><p class="eyebrow">Administracja</p><h1>Zespół i konta</h1></div></div>
<div class="side">
  <div class="tbl-wrap"><table>
    <thead><tr><th>Osoba</th><th>Rola</th><th>Status</th><th>Ostatnie logowanie</th><th>Działania</th></tr></thead>
    <tbody><?php foreach ($users as $x): $isStaff = in_array($x['role'], STAFF, true); ?>
      <tr><td><b><?= e($x['name']) ?></b><br><span class="small muted"><?= e($x['email']) ?></span></td>
        <td><?php if ($isStaff && (int)$x['id'] !== (int)$u['id']): ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$x['id'] ?>"><select name="role" aria-label="Rola" style="width:auto"><?= options($staffRoles, $x['role']) ?></select><button class="btn ghost sm">Zmień</button></form><?php else: ?><?= e(ROLE_LABEL[$x['role']]) ?><?php endif; ?></td>
        <td><?= $x['active'] ? stage_chip('Aktywne', 'solid') : stage_chip('Zablokowane', 'muted') ?></td>
        <td class="num"><?= $x['last_login'] ? date('d.m.Y H:i', strtotime($x['last_login'])) : '—' ?></td>
        <td><?php if ((int)$x['id'] !== (int)$u['id']): ?><div class="actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int)$x['id'] ?>"><button class="btn ghost sm">Nowe hasło</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$x['id'] ?>"><button class="btn ghost sm"><?= $x['active'] ? 'Zablokuj' : 'Odblokuj' ?></button></form>
        </div><?php else: ?><span class="muted small">to Ty</span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <form method="post" class="card f">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>Dodaj osobę z zespołu</h2>
    <div class="field"><label class="l" for="name">Imię i nazwisko</label><input type="text" id="name" name="name" required></div>
    <div class="field"><label class="l" for="email">E-mail</label><input type="email" id="email" name="email" required></div>
    <div class="field"><label class="l" for="role">Rola</label><select id="role" name="role"><?= options($staffRoles, 'opiekun') ?></select></div>
    <p class="muted small">Opiekun klubu prowadzi inwestorów i członków. Analityk prowadzi projekty i dokumenty. Administrator ma pełny dostęp i zarządza kontami.</p>
    <button class="btn" style="align-self:flex-start">Utwórz konto</button>
  </form>
</div>
<?php page_end();
