<?php
require_once __DIR__ . '/includes/auth.php';
logoutUser();
header('Location: ' . APP_URL . '/index.php');
exit;