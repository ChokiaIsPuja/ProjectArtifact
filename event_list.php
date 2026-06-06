<?php
include 'conn.php';
global $conn;

$message = "";
$status = "";

// ==========================================
// 1. HANDLE UPDATE SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_event') {
    mysqli_begin_transaction($conn);
    try {
        $event_id = intval($_POST['event_id']);
        $event_name = trim($_POST['event_name']);
        $sprite = trim($_POST['sprite']);

        if (empty($event_name)) throw new Exception("Event Name cannot be empty.");

        // Update Base Event
        $u_event = mysqli_prepare($conn, "UPDATE events SET event_name = ?, sprite = ? WHERE event_id = ?");
        mysqli_stmt_bind_param($u_event, "ssi", $event_name, $sprite, $event_id);
        mysqli_stmt_execute($u_event);
        mysqli_stmt_close($u_event);

        // Update Dialogue Lines 
        $diag_check = mysqli_query($conn, "SELECT id_event_dialogue FROM event_dialogue WHERE event_id = $event_id LIMIT 1");
        $diag_row = mysqli_fetch_assoc($diag_check);

        if ($diag_row) {
            $event_dialogue_id = $diag_row['id_event_dialogue'];
            mysqli_query($conn, "DELETE FROM detail_dialogue WHERE event_dialogue_id = $event_dialogue_id");
        } else {
            mysqli_query($conn, "INSERT INTO event_dialogue (event_id) VALUES ($event_id)");
            $event_dialogue_id = mysqli_insert_id($conn);
        }

        if (isset($_POST['dialogue_texts'])) {
            $order_no = 1;
            $ins_detail = mysqli_prepare($conn, "INSERT INTO detail_dialogue (event_dialogue_id, text, order_no) VALUES (?, ?, ?)");
            foreach ($_POST['dialogue_texts'] as $text_line) {
                $text_line = trim($text_line);
                if (!empty($text_line)) {
                    mysqli_stmt_bind_param($ins_detail, "isi", $event_dialogue_id, $text_line, $order_no);
                    mysqli_stmt_execute($ins_detail);
                    $order_no++;
                }
            }
            mysqli_stmt_close($ins_detail);
        }

        // Process Choice Options Updates
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            // Fetch old option IDs to clean up requirements/rewards cleanly
            $old_opts_res = mysqli_query($conn, "SELECT event_options_id FROM event_options WHERE event_id = $event_id");
            while ($o_row = mysqli_fetch_assoc($old_opts_res)) {
                $oid = $o_row['event_options_id'];
                mysqli_query($conn, "DELETE FROM event_option_requirements WHERE event_options_id = $oid");
                mysqli_query($conn, "DELETE FROM event_text_output WHERE event_option_id = $oid");
                mysqli_query($conn, "DELETE FROM choice_reward WHERE event_options_id = $oid");
            }
            mysqli_query($conn, "DELETE FROM event_options WHERE event_id = $event_id");

            foreach ($_POST['options'] as $opt) {
                $option_name = trim($opt['option_name']);
                if (empty($option_name)) continue;

                $opt_stmt = mysqli_prepare($conn, "INSERT INTO event_options (event_id, option_name) VALUES (?, ?)");
                mysqli_stmt_bind_param($opt_stmt, "is", $event_id, $option_name);
                mysqli_stmt_execute($opt_stmt);
                $new_opt_id = mysqli_insert_id($conn);
                mysqli_stmt_close($opt_stmt);

                // Insert Requirements Matrix
                $req_stmt = mysqli_prepare($conn, "INSERT INTO event_option_requirements 
                    (event_options_id, req_max_hp, req_hp, req_gold, req_level, req_str, req_def, req_dex, req_int, req_fth, id_item) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $r_max_hp = intval($opt['req_max_hp']);
                $r_hp     = intval($opt['req_hp']);
                $r_gold   = intval($opt['req_gold']);
                $r_level  = intval($opt['req_level']);
                $r_str    = intval($opt['req_str']);
                $r_def    = intval($opt['req_def']);
                $r_dex    = intval($opt['req_dex']);
                $r_int    = intval($opt['req_int']);
                $r_fth    = intval($opt['req_fth']);
                $id_item  = !empty($opt['id_item']) ? intval($opt['id_item']) : null;

                mysqli_stmt_bind_param(
                    $req_stmt,
                    "iiiiiiiiiii",
                    $new_opt_id,
                    $r_max_hp,
                    $r_hp,
                    $r_gold,
                    $r_level,
                    $r_str,
                    $r_def,
                    $r_dex,
                    $r_int,
                    $r_fth,
                    $id_item
                );
                mysqli_stmt_execute($req_stmt);
                mysqli_stmt_close($req_stmt);

                // Insert Response Narrative text line
                $txt_stmt = mysqli_prepare($conn, "INSERT INTO event_text_output (event_option_id, text_output) VALUES (?, ?)");
                $text_output = empty($opt['text_output']) ? "You completed the encounter." : trim($opt['text_output']);
                mysqli_stmt_bind_param($txt_stmt, "is", $new_opt_id, $text_output);
                mysqli_stmt_execute($txt_stmt);
                mysqli_stmt_close($txt_stmt);

                // Insert Yield Rewards
                $rew_stmt = mysqli_prepare($conn, "INSERT INTO choice_reward 
                    (event_options_id, event_max_hp, event_hp, event_str, event_def, event_dex, event_int, event_fth, item_id, qty) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $rew_max_hp = intval($opt['rew_max_hp']);
                $rew_hp     = intval($opt['rew_hp']);
                $rew_str    = intval($opt['rew_str']);
                $rew_def    = intval($opt['rew_def']);
                $rew_dex    = intval($opt['rew_dex']);
                $rew_int    = intval($opt['rew_int']);
                $rew_fth    = intval($opt['rew_fth']);
                $rew_item_id = !empty($opt['rew_item_id']) ? intval($opt['rew_item_id']) : null;
                $rew_qty    = intval($opt['rew_qty']);

                mysqli_stmt_bind_param(
                    $rew_stmt,
                    "iiiiiiiiii",
                    $new_opt_id,
                    $rew_max_hp, 
                    $rew_hp,     
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
        $message = "✨ Event ID #$event_id updated successfully!";
        $status = "success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "❌ Error saving updates: " . $e->getMessage();
        $status = "danger";
    }
}

// ==========================================
// 2. FETCH EVENTS LIST RESOURCE FOR MAIN WINDOW VIEW
// ==========================================
$events_list = [];
$res = mysqli_query($conn, "SELECT * FROM events ORDER BY event_id DESC");
while ($row = mysqli_fetch_assoc($res)) {
    $events_list[] = $row;
}

// ==========================================
// 3. HANDLE SINGLE EVENT EXTRACTION EDITING REQUESTS (AJAX/Fetch Endpoint)
// ==========================================
if (isset($_GET['fetch_edit_id'])) {
    header('Content-Type: application/json');
    $edit_id = intval($_GET['fetch_edit_id']);

    $evt_q = mysqli_query($conn, "SELECT * FROM events WHERE event_id = $edit_id LIMIT 1");
    $evt_data = mysqli_fetch_assoc($evt_q);

    if (!$evt_data) {
        echo json_encode(['success' => false]);
        exit;
    }

    // Dialogue Extract
    $dial_lines = [];
    $dial_q = mysqli_query($conn, "SELECT dd.text FROM event_dialogue ed 
        INNER JOIN detail_dialogue dd ON ed.id_event_dialogue = dd.event_dialogue_id 
        WHERE ed.event_id = $edit_id ORDER BY dd.order_no ASC");
    while ($r = mysqli_fetch_assoc($dial_q)) {
        $dial_lines[] = $r['text'];
    }

    // Choices Pathways Matrix Extract
    $opts = [];
    $opt_q = mysqli_query($conn, "SELECT eo.event_options_id, eo.option_name, r.*, t.text_output, w.*
        FROM event_options eo
        LEFT JOIN event_option_requirements r ON eo.event_options_id = r.event_options_id
        LEFT JOIN event_text_output t ON eo.event_options_id = t.event_option_id
        LEFT JOIN choice_reward w ON eo.event_options_id = w.event_options_id
        WHERE eo.event_id = $edit_id");

    while ($r = mysqli_fetch_assoc($opt_q)) {
        $opts[] = $r;
    }

    echo json_encode([
        'success' => true,
        'event' => $evt_data,
        'dialogues' => $dial_lines,
        'options' => $opts
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dungeon Content Inventory Manager</title>
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

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark">📋 Live Dungeon Events Library</h2>
            <a href="event_admin.php" class="btn btn-primary fw-bold">+ Create New Event</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $status ?> text-center fw-bold shadow-sm"><?= $message ?></div>
        <?php endif; ?>

        <div class="table-responsive bg-white rounded shadow-sm p-3 mb-5">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Encounter Event Name</th>
                        <th>Assigned Sprite Asset String</th>
                        <th class="text-end" style="width: 140px;">Action Matrix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events_list)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No events found in database.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events_list as $e): ?>
                            <tr>
                                <td class="fw-bold text-secondary">#<?= $e['event_id'] ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($e['event_name']) ?></td>
                                <td><code class="text-danger"><?= htmlspecialchars($e['sprite'] ?: 'None (Blank)') ?></code></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-warning text-white fw-bold px-3" onclick="loadAndEditEvent(<?= $e['event_id'] ?>)">Edit Database Rows</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="modal fade" id="editorModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form method="POST" action="" class="modal-content card border-0 p-0">
                    <input type="hidden" name="action" value="update_event">
                    <input type="hidden" name="event_id" id="edit-event-id">

                    <div class="modal-header card-header border-0 d-flex justify-content-between">
                        <h5 class="modal-title fw-bold" id="modal-title-label">Modifying Database Matrix Node</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body bg-light px-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Event Name</label>
                                <input type="text" name="event_name" id="edit-event-name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sprite File Name</label>
                                <input type="text" name="sprite" id="edit-sprite" class="form-control">
                            </div>
                        </div>

                        <div class="section-title">💬 Narrative Storyboard Dialogue Text Loop</div>
                        <div id="modal-dialogue-container" class="d-flex flex-column gap-2 mb-3"></div>
                        <button type="button" class="btn btn-sm btn-secondary fw-bold mb-4" onclick="addNewDialogueRowEditor()">+ Append Story Dialogue Line</button>

                        <div class="section-title mb-3">🗺️ Choice Pathways Routes Matrix</div>
                        <div id="modal-options-wrapper"></div>
                        <button type="button" class="btn btn-sm btn-warning text-white fw-bold mb-3" onclick="createNewChoiceBlockEditor()">+ Add Route Pathway Node</button>
                    </div>

                    <div class="modal-footer border-0 bg-white d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel Changes</button>
                        <button type="submit" class="btn btn-success px-5 fw-bold shadow">Push Overwrite Updates ➔</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let choiceCounter = 0;
        const editorModal = new bootstrap.Modal(document.getElementById('editorModal'));

        function loadAndEditEvent(eventId) {
            choiceCounter = 0;
            document.getElementById('modal-dialogue-container').innerHTML = "";
            document.getElementById('modal-options-wrapper').innerHTML = "";

            fetch(`event_list.php?fetch_edit_id=${eventId}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert("Error accessing database records.");
                        return;
                    }

                    document.getElementById('edit-event-id').value = data.event.event_id;
                    document.getElementById('edit-event-name').value = data.event.event_name;
                    document.getElementById('edit-sprite').value = data.event.sprite;
                    document.getElementById('modal-title-label').innerText = `Editing Encounter Node Event #${data.event.event_id}: ${data.event.event_name}`;

                    // Inject Dialogues
                    if (data.dialogues.length === 0) data.dialogues.push("");
                    data.dialogues.forEach(line => addNewDialogueRowEditor(line));

                    // Inject Choice Pathways
                    data.options.forEach(opt => createNewChoiceBlockEditor(opt));

                    editorModal.show();
                });
        }

        function addNewDialogueRowEditor(textVal = "") {
            const container = document.getElementById('modal-dialogue-container');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'dialogue_texts[]';
            input.className = 'form-control';
            input.value = textVal;
            input.placeholder = "Narration dialogue frame sequence string text...";
            container.appendChild(input);
        }

        function createNewChoiceBlockEditor(data = null) {
            choiceCounter++;
            const wrapper = document.getElementById('modal-options-wrapper');
            const block = document.createElement('div');
            block.className = 'card bg-white border-secondary mb-4 p-3 option-block';
            block.id = `edit-opt-block-${choiceCounter}`;

            block.innerHTML = `
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <span class="fw-bold text-secondary">Route Option Pathway Variant #${choiceCounter}</span>
            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('edit-opt-block-${choiceCounter}').remove()">Delete Route</button>
        </div>
        <div class="mb-3">
            <label class="small fw-bold">Button Display Text</label>
            <input type="text" name="options[${choiceCounter}][option_name]" class="form-control form-control-sm" value="${data ? data.option_name : ''}" required>
        </div>
        <div class="row g-2 mb-3 bg-light p-2 rounded">
            <div class="col-md-3"><label class="small">Req Level</label><input type="number" name="options[${choiceCounter}][req_level]" class="form-control form-control-sm" value="${data ? data.req_level : 0}"></div>
            <div class="col-md-3"><label class="small">Req Gold</label><input type="number" name="options[${choiceCounter}][req_gold]" class="form-control form-control-sm" value="${data ? data.req_gold : 0}"></div>
            <div class="col-md-3"><label class="small">Req HP</label><input type="number" name="options[${choiceCounter}][req_hp]" class="form-control form-control-sm" value="${data ? data.req_hp : 0}"></div>
            <div class="col-md-3"><label class="small">Req Max HP</label><input type="number" name="options[${choiceCounter}][req_max_hp]" class="form-control form-control-sm" value="${data ? data.req_max_hp : 0}"></div>
            <div class="col-md-2"><label class="small">Req STR</label><input type="number" name="options[${choiceCounter}][req_str]" class="form-control form-control-sm" value="${data ? data.req_str : 0}"></div>
            <div class="col-md-2"><label class="small">Req DEF</label><input type="number" name="options[${choiceCounter}][req_def]" class="form-control form-control-sm" value="${data ? data.req_def : 0}"></div>
            <div class="col-md-2"><label class="small">Req DEX</label><input type="number" name="options[${choiceCounter}][req_dex]" class="form-control form-control-sm" value="${data ? data.req_dex : 0}"></div>
            <div class="col-md-2"><label class="small">Req INT</label><input type="number" name="options[${choiceCounter}][req_int]" class="form-control form-control-sm" value="${data ? data.req_int : 0}"></div>
            <div class="col-md-2"><label class="small">Req FTH</label><input type="number" name="options[${choiceCounter}][req_fth]" class="form-control form-control-sm" value="${data ? data.req_fth : 0}"></div>
            <div class="col-md-2"><label class="small">Req Item ID</label><input type="number" name="options[${choiceCounter}][id_item]" class="form-control form-control-sm" value="${data && data.id_item ? data.id_item : ''}" placeholder="None"></div>
        </div>
        <div class="mb-3">
            <label class="small fw-bold">Outcome Narrative Text Output</label>
            <textarea name="options[${choiceCounter}][text_output]" class="form-control form-control-sm" rows="2" required>${data ? data.text_output : ''}</textarea>
        </div>
        <div class="row g-2 bg-light p-2 rounded">
            <div class="col-md-3"><label class="small">Add Max HP</label><input type="number" name="options[${choiceCounter}][rew_max_hp]" class="form-control form-control-sm" value="${data ? data.event_max_hp : 0}"></div>
            <div class="col-md-3"><label class="small">Add HP (Healing)</label><input type="number" name="options[${choiceCounter}][rew_hp]" class="form-control form-control-sm" value="${data ? data.event_hp : 0}"></div>
            <div class="col-md-2"><label class="small">Add STR</label><input type="number" name="options[${choiceCounter}][rew_str]" class="form-control form-control-sm" value="${data ? data.event_str : 0}"></div>
            <div class="col-md-2"><label class="small">Add DEF</label><input type="number" name="options[${choiceCounter}][rew_def]" class="form-control form-control-sm" value="${data ? data.event_def : 0}"></div>
            <div class="col-md-2"><label class="small">Add DEX</label><input type="number" name="options[${choiceCounter}][rew_dex]" class="form-control form-control-sm" value="${data ? data.event_dex : 0}"></div>
            <div class="col-md-2"><label class="small">Add INT</label><input type="number" name="options[${choiceCounter}][rew_int]" class="form-control form-control-sm" value="${data ? data.event_int : 0}"></div>
            <div class="col-md-2"><label class="small">Add FTH</label><input type="number" name="options[${choiceCounter}][rew_fth]" class="form-control form-control-sm" value="${data ? data.event_fth : 0}"></div>
            <div class="col-md-4"><label class="small">Reward Item ID</label><input type="number" name="options[${choiceCounter}][rew_item_id]" class="form-control form-control-sm" value="${data && data.item_id ? data.item_id : ''}" placeholder="None"></div>
            <div class="col-md-4"><label class="small">Reward Qty</label><input type="number" name="options[${choiceCounter}][rew_qty]" class="form-control form-control-sm" value="${data ? data.qty : 0}"></div>
        </div>
    `;
            wrapper.appendChild(block);
        }
    </script>
</body>

</html>