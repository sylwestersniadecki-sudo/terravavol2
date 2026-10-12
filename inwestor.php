<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(STAFF);

$id = (int)($_GET['id'] ?? 0);
$i = $id ? one('SELECT * FROM investors WHERE id=?', [$id]) : null;
if ($id && !$i) { http_response_code(404); page_start('Nie znaleziono'); echo '<p>Nie ma takiego inwestora. <a href="inwestorzy.php">Wróć do listy</a></p>'; page_end(); exit; }
$edit = can_edit_investors();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $edit) {
    csrf_check();
    $act = post('action', 20);
    if ($act === 'save') {
        $stage = post('stage', 20); if (!isset(INV_STAGES[$stage])) $stage = 'kontakt';
        $seated = ['zaproszony', 'czlonek'];
        $wasSeated = $i && in_array($i['stage'], $seated, true);
        if (in_array($stage, $seated, true) && !$wasSeated && seats_used() >= seat_limit()) {
            flash('Limit ' . seat_limit() . ' miejsc w klubie jest wyczerpany. Ustaw etap „Wstrzymany” (lista oczekujących) albo zwolnij miejsce.', 'err');
            redirect($id ? "inwestor.php?id=$id" : 'inwestor.php?new=1');
        }
        $name = post('name', 160);
        if ($name === '') { flash('Podaj imię i nazwisko.', 'err'); redirect($id ? "inwestor.php?id=$id" : 'inwestor.php?new=1'); }
        $memberSince = date_or_null(post('member_since', 10));
        if ($stage === 'czlonek' && !$memberSince) $memberSince = date('Y-m-d');
        $owner = (int)post('owner_user_id', 10) ?: null;
        $data = [$name, post('email', 190), post('phone', 40), post('company', 160), post('city', 80), post('source', 120), $stage, $owner,
                 post('next_action', 200), date_or_null(post('next_action_date', 10)), $memberSince, date_or_null(post('fee_until', 10)),
                 isset($_POST['nda']) ? 1 : 0, isset($_POST['survey']) ? 1 : 0];
        if ($i) {
            q('UPDATE investors SET name=?,email=?,phone=?,company=?,city=?,source=?,stage=?,owner_user_id=?,next_action=?,next_action_date=?,member_since=?,fee_until=?,nda=?,survey=? WHERE id=?', array_merge($data, [$id]));
            if ($i['stage'] !== $stage) add_note('investor', $id, 'Zmiana etapu: ' . INV_STAGES[$i['stage']] . ' → ' . INV_STAGES[$stage]);
            if (in_array($stage, ['odrzucony', 'wstrzymany'], true)) q("UPDATE users SET active=0 WHERE investor_id=? AND role='czlonek'", [$id]);
        } else {
            q('INSERT INTO investors (name,email,phone,company,city,source,stage,owner_user_id,next_action,next_action_date,member_since,fee_until,nda,survey) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
        }
        flash('Zapisano.');
    } elseif ($act === 'note' && $i) {
        add_note('investor', $id, post('body', 4000));
    } elseif ($act === 'account' && $i) {
        if ($i['stage'] !== 'czlonek') flash('Konto członka można utworzyć dopiero na etapie „Członek”.', 'err');
        elseif (create_account((string)$i['email'], $i['name'], 'czlonek', $id)) add_note('investor', $id, 'Utworzono konto w panelu członka.');
    } elseif ($act === 'reset' && $i) {
        $acc = one("SELECT id FROM users WHERE investor_id=? AND role='czlonek'", [$id]);
        if ($acc) { reset_password((int)$acc['id']); q('UPDATE users SET active=1 WHERE id=?', [$acc['id']]); add_note('investor', $id, 'Wygenerowano nowe hasło tymczasowe.'); }
    }
    redirect("inwestor.php?id=$id");
}

$acc = $i ? one("SELECT id, active, last_login FROM users WHERE investor_id=? AND role='czlonek'", [$id]) : null;
$ints = $i && $acc ? all('SELECT p.id, p.name, it.status, it.created_at FROM interests it JOIN projects p ON p.id=it.project_id WHERE it.user_id=? ORDER BY it.created_at DESC', [$acc['id']]) : [];
$v = $i ?? ['name' => '', 'email' => '', 'phone' => '', 'company' => '', 'city' => '', 'source' => '', 'stage' => 'kontakt', 'owner_user_id' => $u['id'], 'next_action' => 'Pierwsza rozmowa', 'next_action_date' => date('Y-m-d', strtotime('+3 days')), 'member_since' => null, 'fee_until' => null, 'nda' => 0, 'survey' => 0];

page_start($i ? $i['name'] : 'Nowy inwestor', 'inwestorzy');
?>
<p><a href="inwestorzy.php">← Inwestorzy</a></p>
<div class="head"><div><p class="eyebrow"><?= $i ? 'Inwestor' : 'Nowy inwestor' ?></p><h1><?= e($i['name'] ?? 'Dodaj inwestora') ?></h1></div>
  <?php if ($i): ?><?= stage_chip(INV_STAGES[$i['stage']], inv_tone($i['stage'])) ?><?php endif; ?></div>

<div class="side">
  <form method="post" class="card f">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <fieldset <?= $edit ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:16px">
      <div class="fields">
        <div class="field"><label class="l" for="name">Imię i nazwisko</label><input type="text" id="name" name="name" required value="<?= e($v['name']) ?>"></div>
        <div class="field"><label class="l" for="company">Firma</label><input type="text" id="company" name="company" value="<?= e($v['company']) ?>"></div>
        <div class="field"><label class="l" for="email">E-mail</label><input type="email" id="email" name="email" value="<?= e($v['email']) ?>"></div>
        <div class="field"><label class="l" for="phone">Telefon</label><input type="tel" id="phone" name="phone" value="<?= e($v['phone']) ?>"></div>
        <div class="field"><label class="l" for="city">Miasto</label><input type="text" id="city" name="city" value="<?= e($v['city']) ?>"></div>
        <div class="field"><label class="l" for="source">Źródło kontaktu</label><input type="text" id="source" name="source" value="<?= e($v['source']) ?>" placeholder="Polecenie, strona, wydarzenie"></div>
        <div class="field"><label class="l" for="stage">Etap</label><select id="stage" name="stage"><?= options(INV_STAGES, $v['stage']) ?></select></div>
        <div class="field"><label class="l" for="owner_user_id">Opiekun</label><select id="owner_user_id" name="owner_user_id"><?= staff_options($v['owner_user_id']) ?></select></div>
        <div class="field"><label class="l" for="next_action">Następny krok</label><input type="text" id="next_action" name="next_action" value="<?= e($v['next_action']) ?>"></div>
        <div class="field"><label class="l" for="next_action_date">Termin</label><input type="date" id="next_action_date" name="next_action_date" value="<?= e($v['next_action_date']) ?>"></div>
      </div>
      <h2 style="margin-top:8px">Członkostwo</h2>
      <div class="fields">
        <div class="field"><label class="l" for="member_since">Członek od</label><input type="date" id="member_since" name="member_since" value="<?= e($v['member_since']) ?>"></div>
        <div class="field"><label class="l" for="fee_until">Składka opłacona do</label><input type="date" id="fee_until" name="fee_until" value="<?= e($v['fee_until']) ?>"></div>
        <label class="check"><input type="checkbox" name="nda" <?= $v['nda'] ? 'checked' : '' ?>> Umowa poufności podpisana</label>
        <label class="check"><input type="checkbox" name="survey" <?= $v['survey'] ? 'checked' : '' ?>> Ankieta członka wypełniona</label>
      </div>
      <?php if ($edit): ?><button class="btn" style="align-self:flex-start">Zapisz</button><?php endif; ?>
    </fieldset>
    <p class="muted small">Etapy „Zaproszony” i „Członek” zajmują miejsce w limicie <?= seat_limit() ?> osób (zajęte: <?= seats_used() ?>).</p>
  </form>

  <aside style="display:flex;flex-direction:column;gap:24px">
    <?php if ($i): ?>
    <div class="card">
      <h2>Konto w panelu</h2>
      <?php if ($acc): ?>
        <div class="list small"><div><span class="muted">Status</span><b><?= $acc['active'] ? 'Aktywne' : 'Zablokowane' ?></b></div><div><span class="muted">Ostatnie logowanie</span><b><?= $acc['last_login'] ? date('d.m.Y H:i', strtotime($acc['last_login'])) : 'nigdy' ?></b></div></div>
        <?php if ($edit): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><button class="btn ghost sm">Nowe hasło tymczasowe</button></form><?php endif; ?>
      <?php elseif ($edit): ?>
        <p class="muted small">Konto tworzysz, gdy inwestor jest na etapie „Członek” i ma podany e-mail.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="account"><button class="btn sm" <?= $i['stage'] === 'czlonek' ? '' : 'disabled' ?>>Utwórz konto członka</button></form>
      <?php else: ?><p class="muted small">Brak konta.</p><?php endif; ?>
    </div>
    <?php if ($ints): ?><div class="card"><h2>Zainteresowania</h2><div class="list small"><?php foreach ($ints as $t): ?><div><a href="projekt.php?id=<?= (int)$t['id'] ?>"><?= e($t['name']) ?></a><span class="muted"><?= fmt_date($t['created_at']) ?></span></div><?php endforeach; ?></div></div><?php endif; ?>
    <div class="card">
      <h2>Notatki</h2>
      <?php if ($edit): ?><form method="post" class="f"><?= csrf_field() ?><input type="hidden" name="action" value="note">
        <label class="l" for="body" style="position:absolute;left:-9999px">Notatka</label><textarea id="body" name="body" placeholder="Przebieg rozmowy, ustalenia" required></textarea><button class="btn ghost sm" style="align-self:flex-start">Dodaj notatkę</button></form><?php endif; ?>
      <?php foreach (notes_for('investor', $id) as $n): ?><div class="note"><span class="prose"><?= e($n['body']) ?></span><small><?= e($n['name'] ?? '—') ?> · <?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></small></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </aside>
</div>
<?php page_end();
