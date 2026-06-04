<?php
// process_insert_item.php
require 'conn.php'; // Adjust path to your database connection file
global $conn;

// Intercept non-POST routing request intrusions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_insert_item.php");
    exit;
}

// 1. Gather Master Item Data
$item_name  = mysqli_real_escape_string($conn, $_POST['item_name']);
$item_type  = mysqli_real_escape_string($conn, $_POST['item_type']);
$item_desc  = mysqli_real_escape_string($conn, $_POST['item_desc'] ?? '');
$buy_price  = isset($_POST['buy_price']) ? intval($_POST['buy_price']) : 0;
$sell_price = (int)($buy_price * 0.4);
$sprite     = mysqli_real_escape_string($conn, $_POST['sprite'] ?? '../asset/sprites/items/default.png');

// FIXED: Extract ALL combat attribute modifiers from the form POST keys
$att_str    = isset($_POST['att_str']) ? intval($_POST['att_str']) : 0;
$att_def    = isset($_POST['att_def']) ? intval($_POST['att_def']) : 0;
$att_dex    = isset($_POST['att_dex']) ? intval($_POST['att_dex']) : 0;
$att_int    = isset($_POST['att_int']) ? intval($_POST['att_int']) : 0; // Added this line
$att_fth    = isset($_POST['att_fth']) ? intval($_POST['att_fth']) : 0; // Added this line
$att_heal   = isset($_POST['att_heal']) ? intval($_POST['att_heal']) : 0;
$att_max_hp = isset($_POST['att_max_hp']) ? intval($_POST['att_max_hp']) : 0;

// Extract stat requirements (equipment_att_req)
$req_str    = isset($_POST['req_str']) ? intval($_POST['req_str']) : 0;
$req_def    = isset($_POST['req_def']) ? intval($_POST['req_def']) : 0;
$req_dex    = isset($_POST['req_dex']) ? intval($_POST['req_dex']) : 0;
$req_int    = isset($_POST['req_int']) ? intval($_POST['req_int']) : 0;
$req_fth    = isset($_POST['req_fth']) ? intval($_POST['req_fth']) : 0;

// Validation processing step
if (empty($item_name) || empty($item_type)) {
    header("Location: admin_insert_item.php?status=missing");
    exit;
}

// Begin database insertion pipeline block 
mysqli_begin_transaction($conn);

try {
    // PHASE A: Insert the ITEM first to get the ID
    $item_query = "INSERT INTO item (item_name, item_type, item_desc, buy_price, sell_price, sprite) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_item = mysqli_prepare($conn, $item_query);
    mysqli_stmt_bind_param($stmt_item, "sssiis", $item_name, $item_type, $item_desc, $buy_price, $sell_price, $sprite);
    mysqli_stmt_execute($stmt_item);
    $item_id = mysqli_insert_id($conn); // Get the ID of the new item
    mysqli_stmt_close($stmt_item);

    // PHASE B: Insert into item_attributes
    $attr_query = "INSERT INTO item_attributes (item_id, att_str, att_def, att_dex, att_int, att_fth, att_heal, att_max_hp) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_attr = mysqli_prepare($conn, $attr_query);

    // FIXED: Swapped out the $req_ variables and cleanly mapped the real $att_int and $att_fth item stats!
    mysqli_stmt_bind_param($stmt_attr, "iiiiiiii", $item_id, $att_str, $att_def, $att_dex, $att_int, $att_fth, $att_heal, $att_max_hp);

    mysqli_stmt_execute($stmt_attr);
    mysqli_stmt_close($stmt_attr);

    // PHASE C: Insert into equipment_att_req (using the item_id we just got)
    $req_query = "INSERT INTO equipment_att_req (req_str, req_def, req_dex, req_int, req_fth, item_id) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_req = mysqli_prepare($conn, $req_query);
    mysqli_stmt_bind_param($stmt_req, "iiiiii", $req_str, $req_def, $req_dex, $req_int, $req_fth, $item_id);
    mysqli_stmt_execute($stmt_req);
    mysqli_stmt_close($stmt_req);

    mysqli_commit($conn);
    header("Location: admin_items.php?status=success");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);
    // CRITICAL: Echo the error to see exactly what is failing
    die("Database Error: " . $e->getMessage());
}