<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';

if ($u = me()) redirect(home_for($u));

$err = '';
$ip = substr($_SERVER['REMOTE_ADDR'] ?? '0', 0, 45);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row = one('SELECT attempts, last_at FROM login_attempts WHERE ip=?', [$ip]);
    $blocked = $row && (int)$row['attempts'] >= 8 && strtotime($row['last_at']) > time() - 900;
    if ($blocked) {
        $err = 'Zbyt wiele nieudanych prób. Spróbuj ponownie za 15 minut.';
    } else {
        $email = mb_strtolower(post('email', 190));
        $user = one('SELECT * FROM users WHERE email=? AND active=1', [$email]);
        if ($user && password_verify((string)($_POST['pass'] ?? ''), $user['pass_hash'])) {
            q('DELETE FROM login_attempts WHERE ip=?', [$ip]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$user['id'];
            q('UPDATE users SET last_login=NOW() WHERE id=?', [$user['id']]);
            if (password_needs_rehash($user['pass_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET pass_hash=? WHERE id=?', [password_hash((string)$_POST['pass'], PASSWORD_DEFAULT), $user['id']]);
            }
            redirect($user['must_change_pw'] ? 'konto.php' : home_for($user));
        }
        if ($row && strtotime($row['last_at']) > time() - 900) q('UPDATE login_attempts SET attempts=attempts+1, last_at=NOW() WHERE ip=?', [$ip]);
        else q('REPLACE INTO login_attempts (ip, attempts, last_at) VALUES (?,1,NOW())', [$ip]);
        $err = 'Nieprawidłowy e-mail lub hasło.';
    }
}
$flash = $_SESSION['flash'] ?? []; unset($_SESSION['flash']);
?><!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logowanie · Terrava</title><meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">
<link rel="stylesheet" href="assets/app.css"></head>
<body>
<div class="login">
  <section class="art">
    <a class="brand" href="../"><?= logo_svg() ?></a>
    <div style="display:flex;flex-direction:column;gap:16px;max-width:460px">
      <p class="eyebrow" style="color:var(--copper-light)">Klub Terrava</p>
      <h1>Sprawdzone grunty i projekty. Tylko dla członków.</h1>
      <p style="color:#B9C0C9">Profile projektów, analizy i pokój danych widzą wyłącznie zaproszeni członkowie.</p>
    </div>
    <div class="stripes"><i></i><i></i></div>
  </section>
  <section class="box">
    <form method="post" class="f" autocomplete="on">
      <?= csrf_field() ?>
      <h2 style="font-size:28px">Zaloguj się</h2>
      <p class="muted small">Dostęp mają członkowie klubu, właściciele projektów i zespół Terrava.</p>
      <?php foreach ($flash as [$k, $m]): ?><div class="flash <?= e($k) ?>"><?= e($m) ?></div><?php endforeach; ?>
      <?php if ($err): ?><div class="flash err" role="alert"><?= e($err) ?></div><?php endif; ?>
      <div class="field"><label class="l" for="email">E-mail</label><input type="email" id="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label class="l" for="pass">Hasło</label><input type="password" id="pass" name="pass" required autocomplete="current-password"></div>
      <button class="btn">Zaloguj się</button>
      <p class="muted small">Nie pamiętasz hasła? Napisz do opiekuna klubu, ustawi nowe hasło tymczasowe.</p>
    </form>
  </section>
</div>
</body></html>
