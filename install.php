<?php
// Jednorazowa instalacja: tworzy tabele i pierwsze konto administratora. Po instalacji usuń ten plik z serwera.
declare(strict_types=1);
require __DIR__ . '/inc/app.php';

$lock = STORAGE_DIR . '/installed.lock';
$err = '';
if (is_file($lock)) {
    page_start('Instalacja');
    echo '<div class="card"><h1>Panel jest już zainstalowany</h1><p>Usuń plik <b>install.php</b> z serwera i <a href="login.php">zaloguj się</a>.</p></div>';
    page_end(); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('name', 160); $email = mb_strtolower(post('email', 190)); $pass = (string)($_POST['pass'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Podaj imię i nazwisko oraz poprawny e-mail.';
    elseif (mb_strlen($pass) < 12) $err = 'Hasło musi mieć co najmniej 12 znaków.';
    elseif (!is_writable(STORAGE_DIR)) $err = 'Katalog storage musi mieć prawo zapisu (chmod 755 lub 775).';
    else {
        try {
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) {
                if (strpos($stmt, 'CREATE') !== false) db()->exec($stmt);
            }
            q('INSERT INTO users (email,name,pass_hash,role,must_change_pw) VALUES (?,?,?,?,0)', [$email, $name, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
            file_put_contents($lock, date('c'));
            flash('Instalacja zakończona. Zaloguj się i usuń plik install.php z serwera.');
            redirect('login.php');
        } catch (Throwable $ex) {
            $err = 'Nie udało się połączyć z bazą lub utworzyć tabel. Sprawdź dane w config.php. (' . $ex->getMessage() . ')';
        }
    }
}
page_start('Instalacja');
?>
<div class="card" style="max-width:560px">
  <p class="eyebrow">Instalacja</p>
  <h1>Konto administratora</h1>
  <p class="muted">Utworzymy tabele w bazie z config.php i pierwsze konto z pełnymi uprawnieniami.</p>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="f">
    <?= csrf_field() ?>
    <div class="field"><label class="l" for="name">Imię i nazwisko</label><input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div>
    <div class="field"><label class="l" for="email">E-mail (login)</label><input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div class="field"><label class="l" for="pass">Hasło (min. 12 znaków)</label><input type="password" id="pass" name="pass" required minlength="12" autocomplete="new-password"></div>
    <button class="btn">Zainstaluj</button>
  </form>
</div>
<?php page_end();
