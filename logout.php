<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

destruirSessao();
header('Location: index.php');
exit;
