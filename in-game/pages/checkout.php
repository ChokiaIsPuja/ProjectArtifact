<?php
// /Ro-Golike/in-game/pages/checkout.php

// Keep errors displaying just in case, but our JSON will handle DB errors cleanly now
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Your working connection path!
require_once '../../conn.php';
global $conn;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!empty($input['items'])) {
        $node_id = 7;

        if (!isset($_SESSION['shop_inventory'])) {
            $_SESSION['shop_inventory'] = [];
        }
        if (!isset($_SESSION['shop_inventory'][$node_id])) {
            $_SESSION['shop_inventory'][$node_id] = ['purchased' => []];
        }

        $purchased_item_ids = [];

        foreach ($input['items'] as $purchasedItem) {
            $p_id = intval($purchasedItem['item_id']);
            $purchased_item_ids[] = $p_id; // Store ID for DB queries

            if (!in_array($p_id, $_SESSION['shop_inventory'][$node_id]['purchased'])) {
                $_SESSION['shop_inventory'][$node_id]['purchased'][] = $p_id;
            }
        }

        // =========================================================================
        // [DATABASE OPERATIONS]
        // =========================================================================

        // 1. Read the player_id from the incoming JavaScript JSON payload instead of $_SESSION
        if (!isset($input['player_id']) || empty($input['player_id'])) {
            echo json_encode(['success' => false, 'message' => 'Error: Player ID was not passed from the URL.']);
            exit;
        }
        $player_id = intval($input['player_id']);

        // 2. Calculate total gold cost
        $id_list = implode(',', $purchased_item_ids);
        $price_query = "SELECT SUM(sell_price) AS total FROM item WHERE item_id IN ($id_list)";
        $price_res = mysqli_query($conn, $price_query);

        if (!$price_res) {
            echo json_encode(['success' => false, 'message' => 'DB Error (Price): ' . mysqli_error($conn)]);
            exit;
        }

        $price_row = mysqli_fetch_assoc($price_res);
        $total_cost = intval($price_row['total']);

        // 3. Subtract gold 
        $gold_query = "UPDATE player SET gold = gold - ? WHERE player_id = ?";
        $stmt1 = mysqli_prepare($conn, $gold_query);

        if (!$stmt1) {
            echo json_encode(['success' => false, 'message' => 'DB Error (Gold Update): ' . mysqli_error($conn)]);
            exit;
        }

        mysqli_stmt_bind_param($stmt1, "ii", $total_cost, $player_id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        // 4. Add items to inventory
        $inv_query = "INSERT INTO bag (player_id, item_id) VALUES (?, ?)";
        $stmt2 = mysqli_prepare($conn, $inv_query);

        if (!$stmt2) {
            echo json_encode(['success' => false, 'message' => 'DB Error (Inventory): ' . mysqli_error($conn)]);
            exit;
        }

        foreach ($purchased_item_ids as $p_id) {
            mysqli_stmt_bind_param($stmt2, "ii", $player_id, $p_id);
            mysqli_stmt_execute($stmt2);
        }
        mysqli_stmt_close($stmt2);

        // 5. Fetch the freshly updated gold amount straight from the database to ensure 100% accuracy
        $sync_query = "SELECT gold FROM player WHERE player_id = ?";
        $stmt_sync = mysqli_prepare($conn, $sync_query);
        mysqli_stmt_bind_param($stmt_sync, "i", $player_id);
        mysqli_stmt_execute($stmt_sync);
        $result_sync = mysqli_stmt_get_result($stmt_sync);
        $player_data = mysqli_fetch_assoc($result_sync);
        $new_gold_balance = $player_data['gold'];
        mysqli_stmt_close($stmt_sync);

        // 6. Send the new gold amount back to JavaScript!
        echo json_encode([
            'success' => true,
            'new_gold' => $new_gold_balance
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Cart payload missing.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
exit;
