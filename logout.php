<?php
// ============================================================
// logout.php — Destroy session and redirect to login
// ============================================================
require_once 'config.php';

$_SESSION = [];
session_destroy();

redirect('newindex.php');
