<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
logoutUser();
flash('You have been logged out.', 'info');
header('Location: /login.php');
exit;
