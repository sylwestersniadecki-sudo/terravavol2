<?php
// Skopiuj ten plik jako config.php i uzupełnij dane bazy z panelu hostingu.
return [
    'db_host'   => 'localhost',
    'db_name'   => 'terrava',
    'db_user'   => 'terrava',
    'db_pass'   => 'ZMIEN_TO_HASLO',
    'mail_from' => 'panel@terrava.pl',          // nadawca powiadomień
    'mail_team' => 'kontakt@terrava.pl',        // adres zespołu na nowe zgłoszenia i zainteresowania
    'seat_limit'=> 149,                          // maksymalna liczba członków i zaproszeń w toku
    'max_upload_mb' => 20,
];
