<?php
// Terrava — obsługa formularza kontaktowego.
// Wymaga PHP 7.4+ i działającej funkcji mail() na serwerze (standard na większości hostingów).
// USTAW: adres, na który mają przychodzić wiadomości, oraz adres nadawcy w Twojej domenie.

$DO     = 'kontakt@terrava.pl';   // odbiorca wiadomości
$OD     = 'formularz@terrava.pl'; // nadawca (musi istnieć w domenie terrava.pl)

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

// Pułapka na boty: ukryte pole musi być puste
if (!empty($_POST['www'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function pole($k, $max = 2000) {
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}

$rola    = pole('rola', 20) === 'projekt' ? 'Właściciel projektu' : 'Inwestor';
$imie    = pole('imie', 120);
$firma   = pole('firma', 160);
$email   = pole('email', 160);
$telefon = pole('telefon', 40);
$tresc   = pole('wiadomosc', 4000);
$zgoda   = pole('zgoda', 5) === 'tak';

if ($imie === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$zgoda) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

$temat = '=?UTF-8?B?' . base64_encode("Terrava: nowa wiadomość ($rola)") . '?=';
$body  = "Rola: $rola\nImię i nazwisko: $imie\nFirma: $firma\nE-mail: $email\nTelefon: $telefon\n\nWiadomość:\n$tresc\n\n"
       . "Zgoda na przetwarzanie danych: tak\nData: " . date('Y-m-d H:i') . "\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n";

$naglowki = "From: Terrava <$OD>\r\n"
          . "Reply-To: $email\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 8bit\r\n";

$ok = @mail($DO, $temat, $body, $naglowki, "-f$OD");

// Zapis zgłoszenia w CRM (jeśli panel jest zainstalowany)
$cfgFile = __DIR__ . '/panel/config.php';
if (is_file($cfgFile)) {
    try {
        $c = require $cfgFile;
        $pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($rola === 'Inwestor') {
            $st = $pdo->prepare("INSERT INTO investors (name, email, phone, company, source, stage, next_action, next_action_date) VALUES (?,?,?,?, 'Formularz na stronie', 'kontakt', 'Oddzwonić', CURDATE())");
            $st->execute([$imie, $email, $telefon, $firma]);
            $iid = (int)$pdo->lastInsertId();
            if ($tresc !== '') $pdo->prepare("INSERT INTO notes (entity, entity_id, user_id, body) VALUES ('investor', ?, 0, ?)")->execute([$iid, "Wiadomość z formularza:\n" . $tresc]);
        } else {
            $st = $pdo->prepare("INSERT INTO projects (name, company, contact_name, contact_email, contact_phone, summary, stage) VALUES (?,?,?,?,?,?, 'zgloszenie')");
            $st->execute(['Zgłoszenie: ' . ($firma ?: $imie), $firma, $imie, $email, $telefon, $tresc]);
            $pid = (int)$pdo->lastInsertId();
            $docs = ['Odpis KRS i umowa spółki', 'Księgi wieczyste i umowa nabycia gruntu', 'Wypis i wyrys z MPZP lub decyzja WZ', 'Pozwolenie na budowę i projekt', 'Budżet z harmonogramem wydatków', 'Analiza sprzedaży', 'Warunki dla inwestorów', 'Doświadczenie dewelopera', 'Kredyty i obciążenia', 'Wycena rzeczoznawcy'];
            $ins = $pdo->prepare('INSERT INTO project_docs (project_id, name) VALUES (?,?)');
            foreach ($docs as $d) $ins->execute([$pid, $d]);
        }
        $ok = true;
    } catch (Throwable $e) {
        // Brak bazy nie blokuje wysyłki e-maila
    }
}

echo json_encode(['ok' => (bool)$ok]);
