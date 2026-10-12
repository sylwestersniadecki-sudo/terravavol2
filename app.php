<?php
// Terrava — wspólny rdzeń aplikacji: konfiguracja, baza, sesja, uprawnienia, widoki.
declare(strict_types=1);

define('APP_DIR', dirname(__DIR__));
define('STORAGE_DIR', APP_DIR . '/storage');

if (!is_file(APP_DIR . '/config.php')) {
    http_response_code(500);
    exit('Brak pliku config.php. Skopiuj config.sample.php jako config.php i uzupełnij dane bazy.');
}
$CONFIG = require APP_DIR . '/config.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Warsaw');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function cfg(string $k, $default = null) { global $CONFIG; return $CONFIG[$k] ?? $default; }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function one(string $sql, array $p = []) { return q($sql, $p)->fetch(); }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }

// ---------- Sesja ----------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('terrava_sess');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (isset($_SESSION['uid'], $_SESSION['seen']) && time() - $_SESSION['seen'] > 7200) {
    $_SESSION = []; session_regenerate_id(true);
}
$_SESSION['seen'] = time();

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function redirect(string $to) { header('Location: ' . $to); exit; }
function flash(string $msg, string $kind = 'ok') { $_SESSION['flash'][] = [$kind, $msg]; }
function post(string $k, int $max = 5000): string {
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
    return mb_substr(str_replace("\0", '', $v), 0, $max);
}
function date_or_null(string $v) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null; }
function fmt_date($d): string { return $d ? date('d.m.Y', strtotime((string)$d)) : '—'; }

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400); exit('Sesja wygasła. Odśwież stronę i spróbuj ponownie.');
    }
}

// ---------- Użytkownik i role ----------
const STAFF = ['admin', 'opiekun', 'analityk'];
const ROLE_LABEL = ['admin' => 'Administrator', 'opiekun' => 'Opiekun klubu', 'analityk' => 'Analityk projektów', 'czlonek' => 'Członek klubu', 'wlasciciel' => 'Właściciel projektu'];

function me() {
    static $u = false;
    if ($u === false) {
        $u = empty($_SESSION['uid']) ? null : one('SELECT * FROM users WHERE id=? AND active=1', [$_SESSION['uid']]);
    }
    return $u ?: null;
}
function require_login(array $roles = []) {
    $u = me();
    if (!$u) redirect(base_url() . 'login.php');
    if ($u['must_change_pw'] && basename($_SERVER['SCRIPT_NAME']) !== 'konto.php') redirect(base_url() . 'konto.php');
    if ($roles && !in_array($u['role'], $roles, true)) { http_response_code(403); page_start('Brak dostępu'); echo '<p>Nie masz uprawnień do tej części panelu.</p>'; page_end(); exit; }
    return $u;
}
function is_staff(): bool { $u = me(); return $u && in_array($u['role'], STAFF, true); }
function base_url(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim(preg_replace('#/crm$#', '', $dir), '/') . '/';
}
function home_for(array $u): string {
    return in_array($u['role'], STAFF, true) ? 'crm/index.php' : ($u['role'] === 'wlasciciel' ? 'wlasciciel.php' : 'index.php');
}
function temp_password(): string {
    $a = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $p = ''; for ($i = 0; $i < 12; $i++) $p .= $a[random_int(0, strlen($a) - 1)];
    return $p;
}
function notify_team(string $subject, string $body) {
    $to = cfg('mail_team'); $from = cfg('mail_from');
    if (!$to || !$from) return;
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body,
        "From: Terrava <$from>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n", "-f$from");
}

// ---------- Słowniki ----------
const INV_STAGES = ['kontakt' => 'Kontakt', 'rozmowa' => 'Rozmowa', 'zaproszony' => 'Zaproszony', 'czlonek' => 'Członek', 'wstrzymany' => 'Wstrzymany', 'odrzucony' => 'Odrzucony'];
const PRJ_STAGES = ['zgloszenie' => 'Zgłoszenie', 'ocena' => 'Wstępna ocena', 'weryfikacja' => 'Weryfikacja', 'komitet' => 'Komitet selekcji', 'prezentacja' => 'Prezentacja', 'realizacja' => 'W realizacji', 'zakonczony' => 'Zakończony', 'odrzucony' => 'Odrzucony'];
const DOC_STATUS = ['brak' => 'Brakuje', 'przeslany' => 'Przesłany', 'przyjety' => 'Przyjęty', 'do_poprawy' => 'Do poprawy'];
const PACKAGES = ['' => '—', 'weryfikacja' => 'Weryfikacja', 'prezentacja' => 'Prezentacja', 'premium' => 'Premium'];
const DEFAULT_DOCS = ['Odpis KRS i umowa spółki', 'Księgi wieczyste i umowa nabycia gruntu', 'Wypis i wyrys z MPZP lub decyzja WZ', 'Pozwolenie na budowę i projekt', 'Budżet z harmonogramem wydatków', 'Analiza sprzedaży', 'Warunki dla inwestorów', 'Doświadczenie dewelopera', 'Kredyty i obciążenia', 'Wycena rzeczoznawcy'];

function seats_used(): int { return (int)val("SELECT COUNT(*) FROM investors WHERE stage IN ('zaproszony','czlonek')"); }
function seat_limit(): int { return (int)cfg('seat_limit', 149); }

function stage_chip(string $label, string $tone = ''): string { return '<span class="chip ' . e($tone) . '">' . e($label) . '</span>'; }
function inv_tone(string $s): string { return ['czlonek' => 'solid', 'zaproszony' => 'copper', 'odrzucony' => 'muted', 'wstrzymany' => 'muted'][$s] ?? ''; }
function prj_tone(string $s): string { return ['prezentacja' => 'copper', 'realizacja' => 'solid', 'zakonczony' => 'muted', 'odrzucony' => 'muted'][$s] ?? ''; }

// ---------- Uprawnienia CRM i konta ----------
function can_edit_investors(): bool { $u = me(); return $u && in_array($u['role'], ['admin', 'opiekun'], true); }
function can_edit_projects(): bool { $u = me(); return $u && in_array($u['role'], ['admin', 'analityk'], true); }
function create_account(string $email, string $name, string $role, ?int $investorId = null): ?int {
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Podaj poprawny adres e-mail, aby utworzyć konto.', 'err'); return null; }
    if (one('SELECT id FROM users WHERE email=?', [$email])) { flash('Konto z tym adresem e-mail już istnieje.', 'err'); return null; }
    $pass = temp_password();
    q('INSERT INTO users (email,name,pass_hash,role,investor_id,must_change_pw) VALUES (?,?,?,?,?,1)', [$email, $name, password_hash($pass, PASSWORD_DEFAULT), $role, $investorId]);
    $_SESSION['secret'] = ['Konto utworzone: ' . ROLE_LABEL[$role], $email, $pass];
    return (int)db()->lastInsertId();
}
function reset_password(int $userId) {
    $u = one('SELECT email, role FROM users WHERE id=?', [$userId]); if (!$u) return;
    $pass = temp_password();
    q('UPDATE users SET pass_hash=?, must_change_pw=1 WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $userId]);
    $_SESSION['secret'] = ['Nowe hasło tymczasowe', $u['email'], $pass];
}
function add_note(string $entity, int $id, string $body) {
    if ($body !== '') q('INSERT INTO notes (entity, entity_id, user_id, body) VALUES (?,?,?,?)', [$entity, $id, me()['id'], $body]);
}
function notes_for(string $entity, int $id): array {
    return all('SELECT n.*, u.name FROM notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.entity=? AND n.entity_id=? ORDER BY n.created_at DESC', [$entity, $id]);
}
function staff_options($selected): string {
    $o = '<option value="">—</option>';
    foreach (all("SELECT id, name FROM users WHERE role IN ('admin','opiekun','analityk') AND active=1 ORDER BY name") as $s)
        $o .= '<option value="' . (int)$s['id'] . '"' . ((int)$selected === (int)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
    return $o;
}
function options(array $map, $selected): string {
    $o = ''; foreach ($map as $k => $v) $o .= '<option value="' . e($k) . '"' . ((string)$selected === (string)$k ? ' selected' : '') . '>' . e($v) . '</option>';
    return $o;
}

// ---------- Widok ----------
function logo_svg(): string {
    return file_get_contents(APP_DIR . '/assets/logo.svg');
}
function page_start(string $title, string $active = '') {
    $u = me(); $b = base_url();
    $nav = [];
    if ($u && in_array($u['role'], STAFF, true)) {
        $nav = ['crm' => ['crm/index.php', 'Pulpit'], 'inwestorzy' => ['crm/inwestorzy.php', 'Inwestorzy'], 'projekty' => ['crm/projekty.php', 'Projekty'], 'czlonkowie' => ['crm/czlonkowie.php', 'Członkowie']];
        if ($u['role'] === 'admin') $nav['zespol'] = ['crm/zespol.php', 'Zespół'];
        $nav['podglad'] = ['index.php', 'Widok członka'];
    } elseif ($u && $u['role'] === 'czlonek') {
        $nav = ['pulpit' => ['index.php', 'Projekty'], 'konto' => ['konto.php', 'Moje konto']];
    } elseif ($u && $u['role'] === 'wlasciciel') {
        $nav = ['wlasciciel' => ['wlasciciel.php', 'Mój projekt'], 'konto' => ['konto.php', 'Moje konto']];
    }
    echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' · Terrava</title><meta name="robots" content="noindex,nofollow">';
    echo '<link rel="icon" type="image/svg+xml" href="' . $b . 'assets/favicon.svg">';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">';
    echo '<link rel="stylesheet" href="' . $b . 'assets/app.css"></head><body>';
    if ($u) {
        echo '<header class="bar"><div class="wrap bar-in"><a class="brand" href="' . $b . e(home_for($u)) . '">' . logo_svg() . '</a>';
        if (in_array($u['role'], STAFF, true)) echo '<span class="tag">CRM</span>';
        echo '<nav class="menu" aria-label="Menu">';
        foreach ($nav as $k => [$href, $label]) echo '<a href="' . $b . e($href) . '"' . ($k === $active ? ' class="on" aria-current="page"' : '') . '>' . e($label) . '</a>';
        echo '</nav><div class="who"><span>' . e($u['name']) . '<small>' . e(ROLE_LABEL[$u['role']]) . '</small></span>';
        echo '<form method="post" action="' . $b . 'logout.php">' . csrf_field() . '<button class="link">Wyloguj</button></form></div></div></header>';
    }
    echo '<main class="wrap page">';
    foreach ($_SESSION['flash'] ?? [] as [$k, $m]) echo '<div class="flash ' . e($k) . '" role="status">' . e($m) . '</div>';
    unset($_SESSION['flash']);
    if (!empty($_SESSION['secret'])) {
        [$label, $email, $pass] = $_SESSION['secret']; unset($_SESSION['secret']);
        echo '<div class="card dark" role="status"><h3>' . e($label) . '</h3><p class="muted small">Przekaż te dane osobiście lub telefonicznie. Hasło pokazujemy tylko raz; przy pierwszym logowaniu trzeba je zmienić.</p>';
        echo '<div class="list"><div><span class="muted">Login</span><b>' . e($email) . '</b></div><div><span class="muted">Hasło tymczasowe</span><b style="font-family:var(--mono)">' . e($pass) . '</b></div><div><span class="muted">Adres panelu</span><b>' . e(($_SERVER['HTTP_HOST'] ?? '') . base_url() . 'login.php') . '</b></div></div></div>';
    }
}
function page_end() {
    echo '</main><footer class="foot wrap">Terrava · strefa poufna. Materiały nie stanowią oferty publicznej ani rekomendacji inwestycyjnej.</footer></body></html>';
}
