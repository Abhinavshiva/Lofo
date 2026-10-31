<?php
// LOFO - Route Protection Guard
require_once __DIR__ . '/../config/app.php';

// Ensures user is logged in
requireLogin();
