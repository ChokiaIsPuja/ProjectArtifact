<?php
// Adjust the relative path to your database connection file as necessary
include 'conn.php';
global $conn;

$message = "";
$status = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);

    try {
        // 1. Insert Core Event Details
        $event_name = trim($_POST['event_name']);
        $sprite = trim($_POST['sprite']); // Plain string manually input by you

        if (empty($event_name)) {
            throw new Exception("Event Name is required.");
        }

        $event_stmt = mysqli_prepare($conn, "INSERT INTO events (event_name, sprite) VALUES (?, ?)");
        mysqli_stmt_bind_param($event_stmt, "ss", $event_name, $sprite);
        mysqli_stmt_execute($event_stmt);
        $event_id = mysqli_insert_id($conn);
        mysqli_stmt_close($event_stmt);

        // 2. Insert Event Dialogue Master Entry
        $dialogue_master_stmt = mysqli_prepare($conn, "INSERT INTO event_dialogue (event_id) VALUES (?)");
        mysqli_stmt_bind_param($dialogue_master_stmt, "i", $event_id);
        mysqli_stmt_execute($dialogue_master_stmt);
        $event_dialogue_id = mysqli_insert_id($conn);
        mysqli_stmt_close($dialogue_master_stmt);

        // 3. Insert Detailed Ordered Dialogue Lines
        if (isset($_POST['dialogue_texts']) && $event_dialogue_id) {
            $order_no = 1;
            $detail_stmt = mysqli_prepare($conn, "INSERT INTO detail_dialogue (event_dialogue_id, text, order_no) VALUES (?, ?, ?)");

            foreach ($_POST['dialogue_texts'] as $text_line) {
                $text_line = trim($text_line);
                if (!empty($text_line)) {
                    mysqli_stmt_bind_param($detail_stmt, "isi", $event_dialogue_id, $text_line, $order_no);
                    mysqli_stmt_execute($detail_stmt);
                    $order_no++;
                }
            }
            mysqli_stmt_close($detail_stmt);
        }

        // 4. Process Choices, Requirements, and Rewards
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            foreach ($_POST['options'] as $opt) {
                $option_name = trim($opt['option_name']);
                if (empty($option_name)) continue;

                // A. Insert Option
                $opt_stmt = mysqli_prepare($conn, "INSERT INTO event_options (event_id, option_name) VALUES (?, ?)");
                mysqli_stmt_bind_param($opt_stmt, "is", $event_id, $option_name);
                mysqli_stmt_execute($opt_stmt);
                $event_options_id = mysqli_insert_id($conn);
                mysqli_stmt_close($opt_stmt);

                // B. Insert Requirements
                $req_max_hp = intval($opt['req_max_hp']);
                $req_hp = intval($opt['req_hp']);
                $req_gold = intval($opt['req_gold']);
                $req_level = intval($opt['req_level']);
                $req_str = intval($opt['req_str']);
                $req_def = intval($opt['req_def']);
                $req_dex = intval($opt['req_dex']);
                $req_int = intval($opt['req_int']);
                $req_fth = intval($opt['req_fth']);
                $id_item = !empty($opt['id_item']) ? intval($opt['id_item']) : null;

                $req_stmt = mysqli_prepare($conn, "INSERT INTO event_option_requirements 
                    (event_options_id, req_max_hp, req_hp, req_gold, req_level, req_str, req_def, req_dex, req_int, req_fth, id_item) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param(
                    $req_stmt,
                    "iiiiiiiiiii",
                    $event_options_id,
                    $req_max_hp,
                    $req_hp,
                    $req_gold,
                    $req_level,
                    $req_str,
                    $req_def,
                    $req_dex,
                    $req_int,
                    $req_fth,
                    $id_item
                );
                mysqli_stmt_execute($req_stmt);
                mysqli_stmt_close($req_stmt);

                // C. Insert Reward Narrative Text
                $text_output = trim($opt['text_output']);
                if (empty($text_output)) $text_output = "You completed the encounter.";

                $txt_stmt = mysqli_prepare($conn, "INSERT INTO event_text_output (event_option_id, text_output) VALUES (?, ?)");
                mysqli_stmt_bind_param($txt_stmt, "is", $event_options_id, $text_output);
                mysqli_stmt_execute($txt_stmt);
                mysqli_stmt_close($txt_stmt);

                // D. Insert Choice Reward Matrix
                $rew_max_hp = intval($opt['rew_max_hp']);
                $rew_hp = intval($opt['rew_hp']); // Captured safely here
                $rew_str = intval($opt['rew_str']);
                $rew_def = intval($opt['rew_def']);
                $rew_dex = intval($opt['rew_dex']);
                $rew_int = intval($opt['rew_int']);
                $rew_fth = intval($opt['rew_fth']);
                $rew_item_id = !empty($opt['rew_item_id']) ? intval($opt['rew_item_id']) : null;
                $rew_qty = intval($opt['rew_qty']);

                $rew_stmt = mysqli_prepare($conn, "INSERT INTO choice_reward 
                    (event_options_id, event_max_hp, event_hp, event_str, event_def, event_dex, event_int, event_fth, item_id, qty) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param(
                    $rew_stmt,
                    "iiiiiiiiii",
                    $event_options_id,
                    $rew_max_hp,
                    $rew_hp, // Safely bound into row values mapping structure
                    $rew_str,
                    $rew_def,
                    $rew_dex,
                    $rew_int,
                    $rew_fth,
                    $rew_item_id,
                    $rew_qty
                );
                mysqli_stmt_execute($rew_stmt);
                mysqli_stmt_close($rew_stmt);
            }
        }

        mysqli_commit($conn);
        $message = "🎉 Event completely created and linked successfully!";
        $status = "success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "❌ Error saving event: " . $e->getMessage();
        $status = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dungeon Event Administrator Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #F3E1CE;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .card {
            border-radius: 12px;
            border: 4px solid #D39670;
            background-color: #FAC79B;
        }

        .card-header {
            background-color: #D39670;
            color: #fff;
            font-weight: bold;
            font-size: 20px;
        }

        label {
            font-weight: 600;
            color: #5A3A22;
        }

        .section-title {
            border-bottom: 2px dashed #B46940;
            margin-bottom: 15px;
            padding-bottom: 5px;
            color: #B46940;
            font-weight: bold;
        }
    </style>
</head>

<body class="py-5">

    <div class="container" style="max-width: 900px;">
        <h1 class="text-center mb-4 fw-bold text-dark">⚔️ Event Creator Matrix</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $status ?> shadow-sm text-center fw-bold"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="card shadow-sm mb-4">
                <div class="card-header">Step 1: Core Event Identity</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Event Name</label>
                        <input type="text" name="event_name" class="form-control" placeholder="e.g., Shifty Merchant Altar" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sprite File Name</label>
                        <input type="text" name="sprite" class="form-control" placeholder="e.g., goblin_vendor.png">
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">Step 2: Narrative Dialogue Lines (In Order)</div>
                <div class="card-body">
                    <div id="dialogue-container" class="d-flex flex-column gap-2">
                        <input type="text" name="dialogue_texts[]" class="form-control" placeholder="Line 1: A mysterious shadowy figure blocks your path...">
                        <input type="text" name="dialogue_texts[]" class="form-control" placeholder="Line 2: 'Pay the toll or face the dungeon's wrath!'">
                    </div>
                    <button type="button" class="btn btn-sm btn-light mt-2 border fw-bold text-secondary" onclick="addDialogueRow()">+ Add Dialogue Line</button>
                </div>
            </div>

            <div id="options-master-wrapper">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-5">
                <button type="button" class="btn btn-warning fw-bold text-white shadow-sm" onclick="createNewChoiceBlock()">+ Add Route Choice Path</button>
                <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow">Save Event Structure ➔</button>
            </div>
        </form>
    </div>

    <script>
        let choiceCounter = 0;

        function addDialogueRow() {
            const container = document.getElementById('dialogue-container');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'dialogue_texts[]';
            input.className = 'form-control';
            input.placeholder = `Next Line Description...`;
            container.appendChild(input);
        }

        function createNewChoiceBlock() {
            choiceCounter++;
            const wrapper = document.getElementById('options-master-wrapper');

            const block = document.createElement('div');
            block.className = 'card shadow-sm mb-4 option-block';
            block.id = `option-block-${choiceCounter}`;

            block.innerHTML = `
        <div class="card-header d-flex justify-content-between align-items-center bg-secondary">
            <span>Route Option Pathway #${choiceCounter}</span>
            <button type="button" class="btn btn-danger btn-sm text-white fw-bold" onclick="removeChoiceBlock(${choiceCounter})">Remove Path</button>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Button Display Action Text</label>
                <input type="text" name="options[${choiceCounter}][option_name]" class="form-control" placeholder="e.g., Smash open the chest (Requires 15 STR)" required>
            </div>

            <div class="section-title">🛑 Entry/Click Requirements (0 or Blank = Free)</div>
            <div class="row g-2 mb-3">
                <div class="col-md-3"><label class="small">Req Level</label><input type="number" name="options[${choiceCounter}][req_level]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-3"><label class="small">Req Gold</label><input type="number" name="options[${choiceCounter}][req_gold]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-3"><label class="small">Req Curr HP</label><input type="number" name="options[${choiceCounter}][req_hp]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-3"><label class="small">Req Max HP</label><input type="number" name="options[${choiceCounter}][req_max_hp]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Req STR</label><input type="number" name="options[${choiceCounter}][req_str]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Req DEF</label><input type="number" name="options[${choiceCounter}][req_def]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Req DEX</label><input type="number" name="options[${choiceCounter}][req_dex]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Req INT</label><input type="number" name="options[${choiceCounter}][req_int]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Req FTH</label><input type="number" name="options[${choiceCounter}][req_fth]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Req Item ID</label><input type="number" name="options[${choiceCounter}][id_item]" class="form-control form-control-sm" placeholder="None"></div>
            </div>

            <div class="section-title">💬 Outcome Narrative Response Line</div>
            <div class="mb-3">
                <textarea name="options[${choiceCounter}][text_output]" class="form-control" rows="2" placeholder="e.g., You shatter the lock! Inside you discover stat updates and legendary equipment." required></textarea>
            </div>

            <div class="section-title">🎁 Choice Stat Modifications / Rewards</div>
            <div class="row g-2">
                <div class="col-md-3"><label class="small">Add Max HP</label><input type="number" name="options[${choiceCounter}][rew_max_hp]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-3"><label class="small">Add Current HP (Healing)</label><input type="number" name="options[${choiceCounter}][rew_hp]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Add STR</label><input type="number" name="options[${choiceCounter}][rew_str]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Add DEF</label><input type="number" name="options[${choiceCounter}][rew_def]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Add DEX</label><input type="number" name="options[${choiceCounter}][rew_dex]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Add INT</label><input type="number" name="options[${choiceCounter}][rew_int]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-2"><label class="small">Add FTH</label><input type="number" name="options[${choiceCounter}][rew_fth]" class="form-control form-control-sm" value="0"></div>
                <div class="col-md-4"><label class="small">Reward Item ID</label><input type="number" name="options[${choiceCounter}][rew_item_id]" class="form-control form-control-sm" placeholder="None"></div>
                <div class="col-md-4"><label class="small">Reward Item Qty</label><input type="number" name="options[${choiceCounter}][rew_qty]" class="form-control form-control-sm" value="0"></div>
            </div>
        </div>
    `;
            wrapper.appendChild(block);
        }

        function removeChoiceBlock(id) {
            const target = document.getElementById(`option-block-${id}`);
            if (target) target.remove();
        }

        // Instantiate at least one choice block on page boot automatically
        createNewChoiceBlock();
    </script>
</body>

</html>