<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/app.php';
$u = require_login(STAFF);

$id = (int)($_GET['id'] ?? 0);
$p = $id ? one('SELECT * FROM projects WHERE id=?', [$id]) : null;
if ($id && !$p) { http_response_code(404); page_start('Nie znaleziono'); echo '<p>Nie ma takiego projektu. <a href="projekty.php">Wróć do listy</a></p>'; page_end(); exit; }
$edit = can_edit_projects();
$canInterests = in_array($u['role'], ['admin', 'opiekun'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = post('action', 20);
    if ($act === 'save' && $edit) {
        $stage = post('stage', 20); if (!isset(PRJ_STAGES[$stage])) $stage = 'zgloszenie';
        $pkg = post('package', 20); if (!isset(PACKAGES[$pkg])) $pkg = '';
        $published = isset($_POST['published']) && in_array($stage, ['prezentacja', 'realizacja'], true) ? 1 : 0;
        if (isset($_POST['published']) && !$published) flash('Projekt można opublikować członkom dopiero na etapie „Prezentacja” lub „W realizacji”.', 'err');
        $name = post('name', 200);
        if ($name === '') { flash('Podaj nazwę projektu.', 'err'); redirect($id ? "projekt.php?id=$id" : 'projekt.php?new=1'); }
        $data = [$name, post('location', 200), post('kind', 10) === 'grunt' ? 'grunt' : 'projekt', $stage, $pkg, post('company', 200), post('contact_name', 160), post('contact_email', 190), post('contact_phone', 40),
                 post('capital_text', 120), post('ticket_text', 120), post('horizon_text', 120), post('form_text', 160), post('security_text', 200), post('summary', 8000), post('risks', 8000),
                 date_or_null(post('committee_date', 10)), $published, (int)post('analyst_user_id', 10) ?: null];
        if ($p) {
            q('UPDATE projects SET name=?,location=?,kind=?,stage=?,package=?,company=?,contact_name=?,contact_email=?,contact_phone=?,capital_text=?,ticket_text=?,horizon_text=?,form_text=?,security_text=?,summary=?,risks=?,committee_date=?,published=?,analyst_user_id=? WHERE id=?', array_merge($data, [$id]));
            if ($p['stage'] !== $stage) add_note('project', $id, 'Zmiana etapu: ' . PRJ_STAGES[$p['stage']] . ' → ' . PRJ_STAGES[$stage]);
            if ((int)$p['published'] !== $published) add_note('project', $id, $published ? 'Opublikowano projekt członkom klubu.' : 'Wycofano projekt z widoku członków.');
        } else {
            q('INSERT INTO projects (name,location,kind,stage,package,company,contact_name,contact_email,contact_phone,capital_text,ticket_text,horizon_text,form_text,security_text,summary,risks,committee_date,published,analyst_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
            foreach (DEFAULT_DOCS as $dn) q('INSERT INTO project_docs (project_id, name) VALUES (?,?)', [$id, $dn]);
        }
        flash('Zapisano.');
    } elseif ($act === 'note' && $p) {
        add_note('project', $id, post('body', 4000));
    } elseif ($act === 'doc' && $p && $edit) {
        $docId = (int)post('doc_id', 10);
        $d = one('SELECT * FROM project_docs WHERE id=? AND project_id=?', [$docId, $id]);
        if ($d) {
            $status = post('status', 20); if (!isset(DOC_STATUS[$status])) $status = $d['status'];
            $visible = isset($_POST['visible']) ? 1 : 0;
            if (!empty($_FILES['file']['name'])) {
                $f = $_FILES['file'];
                $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
                if ($f['error'] !== UPLOAD_ERR_OK) flash('Nie udało się przesłać pliku (kod ' . (int)$f['error'] . ').', 'err');
                elseif (!in_array($ext, ['pdf', 'xlsx', 'docx', 'jpg', 'jpeg', 'png'], true)) flash('Dozwolone formaty: PDF, XLSX, DOCX, JPG, PNG.', 'err');
                elseif ($f['size'] > cfg('max_upload_mb', 20) * 1024 * 1024) flash('Plik jest za duży (limit ' . (int)cfg('max_upload_mb', 20) . ' MB).', 'err');
                else {
                    $stored = bin2hex(random_bytes(16)) . '.bin';
                    if (move_uploaded_file($f['tmp_name'], STORAGE_DIR . '/' . $stored)) {
                        if ($d['stored_name']) @unlink(STORAGE_DIR . '/' . basename($d['stored_name']));
                        q('UPDATE project_docs SET file_name=?, stored_name=?, uploaded_at=NOW() WHERE id=?', [mb_substr(basename((string)$f['name']), 0, 200), $stored, $docId]);
                        if ($status === 'brak') $status = 'przeslany';
                    } else flash('Nie udało się zapisać pliku. Sprawdź uprawnienia katalogu storage.', 'err');
                }
            }
            q('UPDATE project_docs SET status=?, visible=? WHERE id=?', [$status, $visible, $docId]);
        }
    } elseif ($act === 'docadd' && $p && $edit) {
        $dn = post('name', 200); if ($dn !== '') q('INSERT INTO project_docs (project_id, name) VALUES (?,?)', [$id, $dn]);
    } elseif ($act === 'interest' && $p && $canInterests) {
        $st = post('status', 20);
        if (in_array($st, ['nowe', 'przekazane', 'zamkniete'], true)) q('UPDATE interests SET status=? WHERE id=? AND project_id=?', [$st, (int)post('interest_id', 10), $id]);
    } elseif ($act === 'owner' && $p && $edit) {
        $uid = create_account((string)$p['contact_email'], $p['contact_name'] ?: $p['company'] ?: $p['name'], 'wlasciciel');
        if ($uid) { q('UPDATE projects SET owner_user_id=? WHERE id=?', [$uid, $id]); add_note('project', $id, 'Utworzono konto właściciela projektu.'); }
    }
    redirect("projekt.php?id=$id");
}

$docs = $p ? all('SELECT d.*, (SELECT COUNT(*) FROM downloads x WHERE x.doc_id=d.id) dl FROM project_docs d WHERE d.project_id=? ORDER BY d.id', [$id]) : [];
$ints = $p ? all('SELECT i.*, u.name, u.email, u.investor_id FROM interests i JOIN users u ON u.id=i.user_id WHERE i.project_id=? ORDER BY i.created_at DESC', [$id]) : [];
$v = $p ?? ['name' => '', 'location' => '', 'kind' => 'projekt', 'stage' => 'zgloszenie', 'package' => '', 'company' => '', 'contact_name' => '', 'contact_email' => '', 'contact_phone' => '', 'capital_text' => '', 'ticket_text' => '', 'horizon_text' => '', 'form_text' => '', 'security_text' => '', 'summary' => '', 'risks' => '', 'committee_date' => null, 'published' => 0, 'analyst_user_id' => $u['id'], 'owner_user_id' => null];

page_start($p ? $p['name'] : 'Nowy projekt', 'projekty');
?>
<p><a href="projekty.php">← Projekty</a></p>
<div class="head"><div><p class="eyebrow"><?= $p ? 'Projekt' : 'Nowy projekt' ?></p><h1><?= e($p['name'] ?? 'Dodaj projekt') ?></h1></div>
  <div class="actions"><?php if ($p): ?><?= stage_chip(PRJ_STAGES[$p['stage']], prj_tone($p['stage'])) ?><?php if ($p['published']): ?><a class="btn ghost sm" href="../projekt.php?id=<?= (int)$id ?>">Zobacz jak członek</a><?php endif; ?><?php endif; ?></div></div>

<div class="side">
  <div style="display:flex;flex-direction:column;gap:24px;min-width:0">
  <form method="post" class="card f">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <fieldset <?= $edit ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:16px">
      <div class="fields">
        <div class="field"><label class="l" for="name">Nazwa projektu</label><input type="text" id="name" name="name" required value="<?= e($v['name']) ?>"></div>
        <div class="field"><label class="l" for="location">Lokalizacja</label><input type="text" id="location" name="location" value="<?= e($v['location']) ?>"></div>
        <div class="field"><label class="l" for="kind">Rodzaj</label><select id="kind" name="kind"><?= options(['projekt' => 'Projekt deweloperski', 'grunt' => 'Grunt (bank ziemi)'], $v['kind']) ?></select></div>
        <div class="field"><label class="l" for="stage">Etap</label><select id="stage" name="stage"><?= options(PRJ_STAGES, $v['stage']) ?></select></div>
        <div class="field"><label class="l" for="package">Pakiet</label><select id="package" name="package"><?= options(PACKAGES, $v['package']) ?></select></div>
        <div class="field"><label class="l" for="committee_date">Data komitetu</label><input type="date" id="committee_date" name="committee_date" value="<?= e($v['committee_date']) ?>"></div>
        <div class="field"><label class="l" for="analyst_user_id">Analityk</label><select id="analyst_user_id" name="analyst_user_id"><?= staff_options($v['analyst_user_id']) ?></select></div>
      </div>
      <h2>Właściciel projektu</h2>
      <div class="fields">
        <div class="field"><label class="l" for="company">Spółka projektowa</label><input type="text" id="company" name="company" value="<?= e($v['company']) ?>"></div>
        <div class="field"><label class="l" for="contact_name">Osoba kontaktowa</label><input type="text" id="contact_name" name="contact_name" value="<?= e($v['contact_name']) ?>"></div>
        <div class="field"><label class="l" for="contact_email">E-mail</label><input type="email" id="contact_email" name="contact_email" value="<?= e($v['contact_email']) ?>"></div>
        <div class="field"><label class="l" for="contact_phone">Telefon</label><input type="tel" id="contact_phone" name="contact_phone" value="<?= e($v['contact_phone']) ?>"></div>
      </div>
      <h2>Profil dla członków</h2>
      <div class="fields">
        <div class="field"><label class="l" for="capital_text">Kapitał od inwestorów</label><input type="text" id="capital_text" name="capital_text" value="<?= e($v['capital_text']) ?>" placeholder="np. 3,4 mln zł"></div>
        <div class="field"><label class="l" for="ticket_text">Typowe wejście</label><input type="text" id="ticket_text" name="ticket_text" value="<?= e($v['ticket_text']) ?>"></div>
        <div class="field"><label class="l" for="form_text">Forma</label><input type="text" id="form_text" name="form_text" value="<?= e($v['form_text']) ?>" placeholder="Udziały w sp. z o.o."></div>
        <div class="field"><label class="l" for="horizon_text">Horyzont</label><input type="text" id="horizon_text" name="horizon_text" value="<?= e($v['horizon_text']) ?>"></div>
        <div class="field full"><label class="l" for="security_text">Zabezpieczenie</label><input type="text" id="security_text" name="security_text" value="<?= e($v['security_text']) ?>"></div>
        <div class="field full"><label class="l" for="summary">Analiza Terravy</label><textarea id="summary" name="summary" rows="6"><?= e($v['summary']) ?></textarea></div>
        <div class="field full"><label class="l" for="risks">Ryzyka</label><textarea id="risks" name="risks" rows="4"><?= e($v['risks']) ?></textarea></div>
        <label class="check"><input type="checkbox" name="published" <?= $v['published'] ? 'checked' : '' ?>> Opublikowany dla członków klubu</label>
      </div>
      <?php if ($edit): ?><button class="btn" style="align-self:flex-start">Zapisz</button><?php endif; ?>
    </fieldset>
  </form>

  <?php if ($p): ?>
  <section class="card">
    <div class="head"><h2>Dokumenty</h2><span class="muted small">Pliki trafiają do chronionego katalogu; członkowie widzą tylko zaznaczone jako widoczne</span></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Dokument</th><th>Plik</th><th>Status</th><th>Widoczny</th><th>Pobrania</th><th></th></tr></thead>
      <tbody><?php foreach ($docs as $d): ?>
        <tr><td><?= e($d['name']) ?></td>
          <td class="small"><?= $d['stored_name'] ? '<a href="../plik.php?id=' . (int)$d['id'] . '">' . e($d['file_name']) . '</a>' : '<span class="muted">—</span>' ?></td>
          <?php if ($edit): ?>
          <td colspan="3"><form method="post" enctype="multipart/form-data" class="actions">
            <?= csrf_field() ?><input type="hidden" name="action" value="doc"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
            <select name="status" aria-label="Status dokumentu" style="width:auto"><?= options(DOC_STATUS, $d['status']) ?></select>
            <label class="check"><input type="checkbox" name="visible" <?= $d['visible'] ? 'checked' : '' ?>> widoczny</label>
            <span class="small muted"><?= (int)$d['dl'] ?> pobr.</span>
            <input type="file" name="file" aria-label="Plik" accept=".pdf,.xlsx,.docx,.jpg,.jpeg,.png" style="max-width:220px;font-size:13px">
            <button class="btn ghost sm">Zapisz</button></form></td><td></td>
          <?php else: ?>
          <td><?= e(DOC_STATUS[$d['status']]) ?></td><td><?= $d['visible'] ? 'Tak' : 'Nie' ?></td><td class="num"><?= (int)$d['dl'] ?></td><td></td>
          <?php endif; ?></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php if ($edit): ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="docadd">
      <label class="l" for="docname" style="position:absolute;left:-9999px">Nazwa dokumentu</label><input type="text" id="docname" name="name" placeholder="Nazwa nowego dokumentu" style="max-width:320px"><button class="btn ghost sm">Dodaj pozycję</button></form><?php endif; ?>
  </section>

  <section class="card" id="zainteresowania">
    <h2>Zainteresowani członkowie</h2>
    <?php if (!$ints): ?><p class="muted">Nikt jeszcze nie zgłosił zainteresowania.</p><?php else: ?>
    <div class="tbl-wrap"><table><thead><tr><th>Członek</th><th>Wiadomość</th><th>Data</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($ints as $t): ?><tr>
        <td><?= $t['investor_id'] ? '<a class="row" href="inwestor.php?id=' . (int)$t['investor_id'] . '">' . e($t['name']) . '</a>' : e($t['name']) ?><br><span class="small muted"><?= e($t['email']) ?></span></td>
        <td class="small"><?= e($t['message'] ?: '—') ?></td><td class="num"><?= fmt_date($t['created_at']) ?></td>
        <td><?php if ($canInterests): ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="interest"><input type="hidden" name="interest_id" value="<?= (int)$t['id'] ?>">
          <select name="status" aria-label="Status" style="width:auto"><?= options(['nowe' => 'Nowe', 'przekazane' => 'Kontakt przekazany', 'zamkniete' => 'Zamknięte'], $t['status']) ?></select><button class="btn ghost sm">OK</button></form>
        <?php else: ?><?= e($t['status']) ?><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </section>
  <?php endif; ?>
  </div>

  <?php if ($p): ?>
  <aside style="display:flex;flex-direction:column;gap:24px">
    <div class="card">
      <h2>Konto właściciela</h2>
      <?php if ($p['owner_user_id']): $ow = one('SELECT id, email, last_login FROM users WHERE id=?', [$p['owner_user_id']]); ?>
        <div class="list small"><div><span class="muted">Login</span><b><?= e($ow['email'] ?? '—') ?></b></div><div><span class="muted">Ostatnie logowanie</span><b><?= !empty($ow['last_login']) ? date('d.m.Y H:i', strtotime($ow['last_login'])) : 'nigdy' ?></b></div></div>
      <?php elseif ($edit): ?>
        <p class="muted small">Właściciel zobaczy w panelu etap weryfikacji i status dokumentów. Konto zostanie założone na e-mail kontaktowy projektu.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="owner"><button class="btn sm">Utwórz konto właściciela</button></form>
      <?php else: ?><p class="muted small">Brak konta.</p><?php endif; ?>
    </div>
    <div class="card">
      <h2>Notatki</h2>
      <form method="post" class="f"><?= csrf_field() ?><input type="hidden" name="action" value="note">
        <label class="l" for="body" style="position:absolute;left:-9999px">Notatka</label><textarea id="body" name="body" placeholder="Ustalenia z weryfikacji, uwagi komitetu" required></textarea><button class="btn ghost sm" style="align-self:flex-start">Dodaj notatkę</button></form>
      <?php foreach (notes_for('project', $id) as $n): ?><div class="note"><span class="prose"><?= e($n['body']) ?></span><small><?= e($n['name'] ?? '—') ?> · <?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></small></div><?php endforeach; ?>
    </div>
  </aside>
  <?php endif; ?>
</div>
<?php page_end();
