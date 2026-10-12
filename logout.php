<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }
$_SESSION = [];
session_regenerate_id(true);
session_destroy();
redirect('login.php');
