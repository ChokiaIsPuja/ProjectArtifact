<?php

include_once __DIR__ . '/../../../conn.php';


/// --- STEP 1.5: RESOLVE CONTENT PATHS ---
$content = ''; 

// Normalize the URL parameter just in case there are hidden spaces
$current_page = isset($_GET['p']) ? trim($_GET['p']) : '';

if ($current_page === "boss_level1") {
    $content = __DIR__ . '/boss_lv1.php';
}