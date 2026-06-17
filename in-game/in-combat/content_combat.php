<?php
include '../../conn.php';

if (empty($_GET['p'])) {
    header("Location: ../index.php");
    exit;
}

$content = null;

if ($_GET['p'] == "combat_level1") {
    $content = __DIR__ . '/combat_lv1.php';
}

if ($_GET['p'] == "boss_level1") {
    $content = __DIR__ . '/boss_lv1.php';
}

if ($content && file_exists($content)) {
    include $content;
}