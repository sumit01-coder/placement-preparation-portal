<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

$auth = new Auth();
$result = $auth->logout();

header('Location: ../../index.php');
exit;
