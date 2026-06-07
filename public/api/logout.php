<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../config/config.php';

$auth = new Auth();
$auth->logout();

header('Location: ' . BASE_URL . '/index.php');
exit;
?>