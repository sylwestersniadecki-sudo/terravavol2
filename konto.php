<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
$u = require_login();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cur = (string)($_POST['cur'] ?? ''); $new = (string)($_POST['new'] ?? ''); $rep = (string)($_POST['rep'] ?? '');
    if (!password_verify($cur, $u['pass_hash'])) $err = 'Obecne hasło jest nieprawidłowe.';
    elseif (mb_strlen($new) < 12) $err = 'Nowe hasło musi mieć co najmniej 12 znaków.';
    elseif ($new !== $rep) $err = 'Hasła nie są takie same.';
    elseif ($new === $cur) $err = 'Nowe hasło musi różnić się od obecnego.';
    else {
        q('UPDATE users SET pass_hash=?, must_change_pw=0 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('Hasło zostało zmienione.');
        redirect(home_for($u));
    }
}
page_start('Moje konto', 'konto');
?>
<div class="head"><div><p class="eyebrow">Moje konto</p><h1><?= e($u['name']) ?></h1></div></div>
<div class="grid g2">
  <div class="card">
    <h2>Zmiana hasła</h2>
    <?php if ($u['must_change_pw']): ?><div class="flash">Logujesz się hasłem tymczasowym. Ustaw własne hasło, aby przejść dalej.</div><?php endif; ?>
    <?php if ($err): ?><div class="flash err" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post" class="f">
      <?= csrf_field() ?>
      <div class="field"><label class="l" for="cur">Obecne hasło</label><input type="password" id="cur" name="cur" required autocomplete="current-password"></div>
      <div class="field"><label class="l" for="new">Nowe hasło (min. 12 znaków)</label><input type="password" id="new" name="new" required minlength="12" autocomplete="new-password"></div>
      <div class="field"><label class="l" for="rep">Powtórz nowe hasło</label><input type="password" id="rep" name="rep" required minlength="12" autocomplete="new-password"></div>
      <button class="btn" style="align-self:flex-start">Zapisz hasło</button>
    </form>
  </div>
  <div class="card">
    <h2>Dane konta</h2>
    <div class="list">
      <div><span class="muted">E-mail</span><b><?= e($u['email']) ?></b></div>
      <div><span class="muted">Rola</span><b><?= e(ROLE_LABEL[$u['role']]) ?></b></div>
      <div><span class="muted">Ostatnie logowanie</span><b><?= e($u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—') ?></b></div>
    </div>
    <p class="muted small">Zmianę adresu e-mail lub danych kontaktowych zgłoś opiekunowi klubu.</p>
  </div>
</div>
<?php page_end();
