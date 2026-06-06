<?php
include '../conn.php';
global $conn;

// 1. Grab the Player ID from the URL parameter to maintain the active session context
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id <= 0) {
    die("Error: Invalid or missing Player ID.");
}

// INNER JOIN connects player stats and player core table (gold, level) in a single step
$player_query = "SELECT ps.*, p.gold, p.level 
                 FROM player_stats ps
                 INNER JOIN player p ON ps.player_id = p.player_id
                 WHERE ps.player_id = ? LIMIT 1";

$p_stmt = mysqli_prepare($conn, $player_query);
mysqli_stmt_bind_param($p_stmt, "i", $player_id);
mysqli_stmt_execute($p_stmt);
$player_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($p_stmt));
mysqli_stmt_close($p_stmt);

if (!$player_stats) {
    die("Dungeon Error: Player data or profile stats could not be recovered.");
}

// 2. Step A: Pick ONE completely random event ID first to secure relationships
$random_query = "SELECT event_id FROM events ORDER BY RAND() LIMIT 1";
$random_result = mysqli_query($conn, $random_query);
$random_event = mysqli_fetch_assoc($random_result);

if (!$random_event) {
    die("Dungeon Error: No events found in your database tables.");
}
$event_id = $random_event['event_id'];

// 3. Step B: Fetch Event Details and ALL ordered story dialogue text lines
$dialogue_query = "SELECT e.event_name, e.sprite, dd.text, dd.order_no 
                   FROM events e
                   LEFT JOIN event_dialogue ed ON e.event_id = ed.event_id
                   LEFT JOIN detail_dialogue dd ON ed.id_event_dialogue = dd.event_dialogue_id
                   WHERE e.event_id = ?
                   ORDER BY dd.order_no ASC";

$stmt = mysqli_prepare($conn, $dialogue_query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$d_result = mysqli_stmt_get_result($stmt);

$dialogue_lines = [];
$event_name = '';
$event_sprite = '';

while ($row = mysqli_fetch_assoc($d_result)) {
    $event_name = $row['event_name'];
    $event_sprite = $row['sprite'];
    if (!empty($row['text'])) {
        $dialogue_lines[] = $row['text'];
    }
}
mysqli_stmt_close($stmt);

if (empty($dialogue_lines)) {
    $dialogue_lines[] = "Something mysterious catches your eye...";
}

// 4. Step C: Fetch available options along with their single-row conditions
$options_query = "SELECT eo.event_options_id, eo.option_name, 
                         r.req_max_hp, r.req_hp, r.req_gold, r.req_level, 
                         r.req_str, r.req_def, r.req_dex, r.req_int, r.req_fth, r.id_item
                  FROM event_options eo
                  -- KEEP: Locked to your exact table choice name here
                  LEFT JOIN event_option_requirements r ON eo.event_options_id = r.event_options_id
                  WHERE eo.event_id = ?";
$stmt = mysqli_prepare($conn, $options_query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$o_result = mysqli_stmt_get_result($stmt);

$event_options = [];
while ($row = mysqli_fetch_assoc($o_result)) {
    $event_options[] = $row;
}
mysqli_stmt_close($stmt);
?>

<style>
    /* Custom button hover behavior for your game choices */
    .choice-btn:hover:not([disabled]) {
        transform: scale(1.04);
        cursor: pointer;
    }

    .choice-btn {
        transition: all 0.2s ease-in-out !important;
        transform-origin: center center;
    }
</style>

<div class="row flex-grow-1" style="min-height: 0;">
    <div class="col-12">
        <div class="rounded-3 shadow-sm position-relative" style="background-color: #FAC79B; height: 870px; width: 100%; overflow: hidden; padding: 0;">

            <div style="position: absolute; top: 20px; left: 0; width: 100%; display: flex; justify-content: center; z-index: 20;">
                <div class="text-white px-3" style="border: none; border-radius: 10px; height: 55px; line-height: 47px; width: max-content;">
                    <h4 class="fw-bold mb-0 text-center" style="font-family: 'Jaro'; color: #fff; font-size: 32px; display: inline-block;">
                        <?= htmlspecialchars($event_name) ?>
                    </h4>
                </div>
                <hr style="position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 80%; border-top: 2px solid #D39670; z-index: -1;">
            </div>

            <div style="position: absolute; top: 90px; bottom: 240px; left: 0; width: 100%; display: flex; justify-content: center; align-items: center; z-index: 5;">
                <img src="../asset/events/<?php echo htmlspecialchars($event_sprite); ?>"
                    alt="<?php echo htmlspecialchars($event_name); ?>"
                    style="max-width: 90%; max-height: 100%; object-fit: contain;">
            </div>

            <div id="event-options-panel" class="d-none" style="position: absolute; bottom: 250px; right: 20px; width: 440px; background-color: #FAC79B; border-radius: 12px; padding: 15px; z-index: 15; overflow-x: hidden;">
                <h5 class="text-white fw-bold mb-2 text-center" style="font-family: 'Jaro'; font-size: 24px; letter-spacing: 1px;">Choose:</h5>

                <div class="d-flex flex-column gap-2 px-2" style="overflow-y: auto; overflow-x: hidden; max-height: 250px; padding-right: 3px;">
                    <?php foreach ($event_options as $option):
                        $is_locked = false;
                        $missing_reqs = [];

                        // 1. Check Level
                        if (!empty($option['req_level']) && $player_stats['level'] < $option['req_level']) {
                            $is_locked = true;
                            $missing_reqs[] = "LVL " . $option['req_level'];
                        }
                        // 2. Check Gold
                        if (!empty($option['req_gold']) && $player_stats['gold'] < $option['req_gold']) {
                            $is_locked = true;
                            $missing_reqs[] = $option['req_gold'] . " Gold";
                        }
                        // 3. Check Core Attributes
                        if (!empty($option['req_str']) && $player_stats['curr_str'] < $option['req_str']) {
                            $is_locked = true;
                            $missing_reqs[] = "STR " . $option['req_str'];
                        }
                        if (!empty($option['req_def']) && $player_stats['curr_def'] < $option['req_def']) {
                            $is_locked = true;
                            $missing_reqs[] = "DEF " . $option['req_def'];
                        }
                        if (!empty($option['req_dex']) && $player_stats['curr_dex'] < $option['req_dex']) {
                            $is_locked = true;
                            $missing_reqs[] = "DEX " . $option['req_dex'];
                        }
                        if (!empty($option['req_int']) && $player_stats['curr_int'] < $option['req_int']) {
                            $is_locked = true;
                            $missing_reqs[] = "INT " . $option['req_int'];
                        }
                        if (!empty($option['req_fth']) && $player_stats['curr_fth'] < $option['req_fth']) {
                            $is_locked = true;
                            $missing_reqs[] = "FTH " . $option['req_fth'];
                        }

                        // 4. Check Current HP & Max HP parameters
                        if (!empty($option['req_max_hp']) && $player_stats['curr_max_hp'] < $option['req_max_hp']) {
                            $is_locked = true;
                            $missing_reqs[] = "MAX HP " . $option['req_max_hp'];
                        }
                        if (!empty($option['req_hp']) && $player_stats['curr_hp'] < $option['req_hp']) {
                            $is_locked = true;
                            $missing_reqs[] = "HP " . $option['req_hp'];
                        }

                        // 5. Inventory item presence validator check
                        if (!empty($option['id_item'])) {
                            $tgt_item = intval($option['id_item']);
                            $item_check = mysqli_query($conn, "SELECT qty FROM bag WHERE player_id = $player_id AND item_id = $tgt_item LIMIT 1");
                            $inventory_row = mysqli_fetch_assoc($item_check);

                            if (!$inventory_row || $inventory_row['qty'] < 1) {
                                $is_locked = true;
                                $missing_reqs[] = "Missing Item";
                            }
                        }
                    ?>
                        <button class="btn text-white fw-bold text-start py-2 px-3 shadow-sm d-flex justify-content-between align-items-center choice-btn"
                            style="background-color: <?= $is_locked ? '#7C7C7C' : '#FAC79B' ?>; border: 5px solid <?= $is_locked ? '#555555' : '#D39670' ?>; border-radius: 6px; font-size: 18px; margin-bottom: 5px;"
                            onclick="submitChoice(<?= intval($option['event_options_id']) ?>)"
                            <?= $is_locked ? 'disabled' : '' ?>>
                            <span><?= htmlspecialchars($option['option_name']) ?></span>
                            <?php if ($is_locked): ?>
                                <span class="badge bg-danger text-white" style="font-size: 12px;"><?= implode(', ', $missing_reqs) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="position: absolute; bottom: 20px; left: 0; width: 100%; padding: 0 20px; z-index: 10;">
                <div class="card text-white p-3" style="height: 220px; background-color: #FAC79B; border: 10px solid #D39670; border-radius: 12px; width: 100%; position: relative;">
                    <p id="event-dialogue-text" class="m-0" style="font-size: 26px; line-height: 1.3; padding-bottom: 50px;"></p>
                    <div id="dialogue-controls" style="position: absolute; bottom: 15px; right: 20px;">
                        <button id="next-dialogue-btn" class="btn btn-light fw-bold" style="color: #B46940; border: 2px solid #B46940;" onclick="advanceDialogue()">Next ➔</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const dialogueLines = <?= json_encode($dialogue_lines) ?>;
    let currentLineIndex = 0;
    const playerId = <?= intval($player_id) ?>;

    function renderCurrentDialogueLine() {
        if (dialogueLines.length > 0) {
            document.getElementById('event-dialogue-text').innerText = dialogueLines[currentLineIndex];
        }
    }

    function advanceDialogue() {
        currentLineIndex++;
        if (currentLineIndex < dialogueLines.length) {
            renderCurrentDialogueLine();
        } else {
            document.getElementById('dialogue-controls').classList.add('d-none');
            document.getElementById('event-options-panel').classList.remove('d-none');
        }
    }

    function submitChoice(optionId) {
        if (!optionId) return;

        fetch('pages/processes/process_event_choice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `player_id=${playerId}&option_id=${optionId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network connection breakdown code: " + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('event-options-panel').classList.add('d-none');
                    document.getElementById('event-dialogue-text').innerText = data.text_output;

                    let controls = document.getElementById('dialogue-controls');
                    controls.innerHTML = `<div class="col-6 text-center">
                <a href="index.php?p=level1&id=${playerId}" onclick="triggerMapLeaveTransition(event, this.href)" class="btn btn-lg" style="background-color:#D39670; border-radius:12px; color:#fff; width:220px;">
                    <h2 class="m-0" style="font-family: 'Jaro', sans-serif; font-size: 24px;">Back To Map!</h2>
                </a>
            </div>`;
                    controls.classList.remove('d-none');
                } else {
                    alert("Database Transaction Processing Error: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error executing choice reward callback sequence:', error);
                alert("Runtime script processing failure. Check Developer Tools Console panel!");
            });
    }

    renderCurrentDialogueLine();
</script>