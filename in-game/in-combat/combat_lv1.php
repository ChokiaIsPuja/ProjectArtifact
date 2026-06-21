<?php
include_once __DIR__ . '/../../conn.php';

// Player ID must come from URL parameter (user can have multiple characters)
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

$player_data = [];

if ($player_id > 0) {
    $query = "SELECT p.*, c.class_name, c.avatar, ps.curr_max_hp, ps.curr_str, ps.curr_def, ps.curr_dex, ps.curr_int, ps.curr_fth, p.level
              FROM player p
              LEFT JOIN class c ON p.class_id = c.class_id
              LEFT JOIN player_stats ps ON p.player_id = ps.player_id 
              WHERE p.player_id = ?";

    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $player_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $player_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if (!empty($player_data)) {
    $player_data['name'] = $player_data['player_name'] ?? $player_data['name'] ?? 'Mimi';
    $player_data['str'] = intval($player_data['curr_str'] ?? 10);
    $player_data['def'] = intval($player_data['curr_def'] ?? 5);
    $player_data['num_dex'] = intval(($player_data['curr_dex'] ?? $player_data['dex']) ?: 10);
    $player_data['dex'] = $player_data['num_dex'];
    $player_data['curr_dex'] = $player_data['dex'];
    $player_data['int'] = intval(($player_data['curr_int'] ?? $player_data['int']) ?: 10);
    $player_data['curr_int'] = $player_data['int'];
    $player_data['fth'] = intval(($player_data['curr_fth'] ?? $player_data['fth']) ?: 10);
    $player_data['curr_fth'] = $player_data['fth'];

    $row = $player_data;
    $row['name'] = $player_data['name'];

    if (!isset($current_hp) || $current_hp === null) {
        $current_hp = intval($player_data['curr_hp'] ?? 100);
    }
    if (!isset($max_hp) || $max_hp === null) {
        $max_hp = intval($player_data['curr_max_hp'] ?? 100);
    }
} else {
    $player_data = [
        'name' => 'Mimi',
        'avatar' => 'player_avatar.png',
        'curr_max_hp' => 100,
        'curr_hp' => 100,
        'curr_str' => 10,
        'curr_def' => 5,
        'dex' => 10,
        'curr_dex' => 10,
        'int' => 10,
        'curr_int' => 10,
        'fth' => 10,
        'curr_fth' => 10,
        'level' => 1
    ];
    $row = $player_data;

    if (!isset($current_hp) || $current_hp === null) {
        $current_hp = 100;
    }
    if (!isset($max_hp) || $max_hp === null) {
        $max_hp = 100;
    }
}

// --- MULTI-ENEMY POPULATION ENGINE ---
$active_enemies = [];
$turn_order_stack = [];
$encounter_limit = rand(2, 4);

$stmt2 = mysqli_prepare($conn, "SELECT e.enemy_id, e.enemy_name, e.sprite, es.enemy_hp, es.enemy_str, es.enemy_def, es.enemy_dex, es.enemy_int, es.enemy_fth 
                                FROM enemy e 
                                JOIN enemy_stats es ON e.enemy_id = es.enemy_id");
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);

$enemy_template_pool = [];
while ($template = mysqli_fetch_assoc($res2)) {
    $enemy_template_pool[] = $template;
}
mysqli_stmt_close($stmt2);

$player_dex = intval($player_data['dex'] ?? 10);
$turn_order_stack[] = [
    'id'       => $player_id,
    'name'     => $player_data['name'],
    'type'     => 'player',
    'sprite'   => $player_data['avatar'] ?? 'player_avatar.png',
    'text_dex' => $player_dex,
    'dex'      => $player_dex,
    'curr_dex' => $player_dex
];

if (!empty($enemy_template_pool)) {
    for ($i = 0; $i < $encounter_limit; $i++) {
        $base = $enemy_template_pool[array_rand($enemy_template_pool)];
        $enemyDex = intval($base['enemy_dex'] ?? 8);

        $enemy_instance = [
            'id'         => $i,
            'enemy_id'   => $base['enemy_id'],
            'name'       => $base['enemy_name'] . ' ' . ($i + 1),
            'enemy_name' => $base['enemy_name'],
            'sprite'     => $base['sprite'],
            'hp'         => intval($base['enemy_hp']),
            'max_hp'     => intval($base['enemy_hp']),
            'str'        => intval($base['enemy_str']),
            'def'        => intval($base['enemy_def']),
            'dex'        => $enemyDex,
            'curr_dex'   => $enemyDex,
            'int'        => intval($base['enemy_int'] ?? 0),
            'fth'        => intval($base['enemy_fth'] ?? 0),
            'alive'      => true
        ];

        $active_enemies[] = $enemy_instance;

        $turn_order_stack[] = [
            'id'       => $i,
            'name'     => $enemy_instance['name'],
            'type'     => 'enemy',
            'sprite'   => $enemy_instance['sprite'],
            'text_dex' => $enemyDex,
            'dex'      => $enemy_instance['dex'],
            'curr_dex' => $enemy_instance['curr_dex']
        ];
    }
}

usort($turn_order_stack, function ($a, $b) {
    return $b['dex'] <=> $a['dex'];
});

$enemies     = $active_enemies;
$enemy_party = $active_enemies;
$combatants  = $turn_order_stack;

$player_inventory = [];
if ($player_id > 0) {
    $stmt3 = mysqli_prepare($conn, "SELECT b.qty, i.item_id, i.item_name, i.item_type, ia.att_heal, ia.att_max_hp 
                                    FROM bag b 
                                    JOIN item i ON b.item_id = i.item_id 
                                    LEFT JOIN item_attributes ia ON i.item_id = ia.item_id 
                                    WHERE b.player_id = ? AND i.item_type='consumables'");
    mysqli_stmt_bind_param($stmt3, "i", $player_id);
    mysqli_stmt_execute($stmt3);
    $res3 = mysqli_stmt_get_result($stmt3);
    while ($row3 = mysqli_fetch_assoc($res3)) {
        $player_inventory[] = $row3;
    }
    mysqli_stmt_close($stmt3);
}

$player_skills = [];
if (!empty($player_data)) {
    $player_level = intval($player_data['level'] ?? 1);
    $player_class = intval($player_data['class_id'] ?? 0);

    $skill_query = "SELECT ob.skill_id, ob.skill_name, ob.skill_desc, ob.mana_cost, ob.lvl_required, ob.cooldown, ob.skill_area,
                           sa.skill_str, sa.skill_def, sa.skill_heal, sa.skill_int, sa.skill_dex, sa.skill_fth,
                           GROUP_CONCAT(CONCAT(sse.status_effect_id, ':', sse.stack)) as attached_statuses
                    FROM obtainable_skill ob
                    LEFT JOIN skill_attributes sa ON ob.skill_id = sa.skill_id
                    LEFT JOIN skill_status_effects sse ON ob.skill_id = sse.skill_id
                    WHERE ob.class_id = ? AND ob.lvl_required <= ?
                    GROUP BY ob.skill_id
                    ORDER BY ob.skill_id ASC";

    $stmt_skills = mysqli_prepare($conn, $skill_query);
    if ($stmt_skills) {
        mysqli_stmt_bind_param($stmt_skills, "ii", $player_class, $player_level);
        mysqli_stmt_execute($stmt_skills);
        $skill_result = mysqli_stmt_get_result($stmt_skills);

        while ($skill_row = mysqli_fetch_assoc($skill_result)) {
            $skill_row['mana_cost'] = intval($skill_row['mana_cost'] ?? 0);

            $statuses = [];
            if (!empty($skill_row['attached_statuses'])) {
                foreach (explode(',', $skill_row['attached_statuses']) as $pair) {
                    list($status_id, $stack_count) = explode(':', $pair);
                    $statuses[] = [
                        'status_effect_id' => intval($status_id),
                        'stacks' => intval($stack_count)
                    ];
                }
            }
            $skill_row['effects'] = $statuses;
            unset($skill_row['attached_statuses']);

            $player_skills[] = $skill_row;
        }
        mysqli_stmt_close($stmt_skills);
    }
}
?>

<style>
    /* ==========================================================================
       🛡️ PRELOADER SYSTEM (COMBINED WITH READY GATE)
       ========================================================================== */
    #combat-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        background: #222;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transform: translateY(-100%);
        animation: slideDown 0.35s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    #combat-preloader.loaded {
        animation: slideUp 0.35s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    #combat-preloader .preloader-image {
        max-width: 220px;
        width: 100%;
        display: block;
        margin: 0 auto;
        animation: pulse 1.2s ease-in-out infinite;
    }

    .preloader-ready-btn {
        background-color: #B46940;
        color: white;
        border: 4px solid #ff9d41;
        border-radius: 8px;
        padding: 15px 50px;
        font-size: 1.8rem;
        font-weight: bold;
        text-shadow: 2px 2px 0px #000;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);
        cursor: pointer;
        transition: transform 0.1s ease, filter 0.2s ease;
        animation: pulseReady 2s infinite;
        margin-top: 20px;
    }

    .preloader-ready-btn:active {
        transform: scale(0.95);
    }

    @keyframes pulseReady {
        0% { box-shadow: 0 0 0 0 rgba(255, 157, 65, 0.7); }
        70% { box-shadow: 0 0 0 15px rgba(255, 157, 65, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 157, 65, 0); }
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.04); }
    }

    @keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
    @keyframes slideUp { from { transform: translateY(0); } to { transform: translateY(-100%); } }

    main {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.6s ease, transform 0.6s ease;
        transition-delay: 0.4s;
        width: 100%;
        height: 100%;
    }

    body.page-ready main {
        opacity: 1;
        transform: translateY(0);
    }

    /* ==========================================================================
       ⚔️ BATTLE ANIMATIONS & FX
       ========================================================================== */
    @keyframes battleFloat {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0px); }
    }

    @keyframes snappyJolt {
        0% { transform: translate(0, 0); }
        15% { transform: translate(-4px, 2px); }
        30% { transform: translate(4px, -2px); }
        45% { transform: translate(-3px, -1px); }
        60% { transform: translate(3px, 2px); }
        75% { transform: translate(-1px, -1px); }
        90% { transform: translate(1px, 1px); }
        100% { transform: translate(0, 0); }
    }

    @keyframes cutInSlice {
        0% { transform: translate(-50%, -50%) scaleX(0); opacity: 0; }
        15% { transform: translate(-50%, -50%) scaleX(1.1); opacity: 1; }
        20% { transform: translate(-50%, -50%) scaleX(1); }
        80% { transform: translate(-50%, -50%) scaleX(1); opacity: 1; filter: brightness(1); }
        95% { transform: translate(-50%, -50%) scaleX(1.05); opacity: 0.5; }
        100% { transform: translate(-50%, -50%) scaleX(0); opacity: 0; filter: brightness(2); }
    }

    @keyframes eyeGlance {
        0% { transform: scale(1.3) translate(-10px, 0); filter: saturate(0.5); }
        20% { transform: scale(1.1) translate(0, 0); filter: saturate(1.2); }
        80% { transform: scale(1.1) translate(5px, 0); }
        100% { transform: scale(1.4) translate(20px, 0); }
    }

    /* --- 🌟 VICTORY SCREEN ANIMATIONS --- */
    @keyframes victoryFadeIn {
        from { opacity: 0; backdrop-filter: blur(0px); }
        to { opacity: 1; backdrop-filter: blur(5px); }
    }

    @keyframes victoryPopIn {
        0% { transform: scale(0.8) translateY(20px); opacity: 0; }
        100% { transform: scale(1) translateY(0); opacity: 1; }
    }

    .victory-screen-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.75);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: victoryFadeIn 0.5s ease-out forwards;
    }

    .victory-panel {
        background-color: #FAC79B;
        border: 6px solid #8B5A3C;
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6), inset 0 0 20px rgba(255, 157, 65, 0.3);
        animation: victoryPopIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        max-width: 500px;
        width: 90%;
    }

    .persona-cutin-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.4);
        z-index: 9998;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .persona-cutin-overlay.active { opacity: 1; }

    .persona-banner-line {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 100%;
        height: 140px;
        background: #000;
        border-top: 4px solid #ff9d41;
        border-bottom: 4px solid #ff9d41;
        box-shadow: 0 0 30px rgba(255, 157, 65, 0.5);
        z-index: 9999;
        overflow: hidden;
        transform-origin: center;
        display: none;
    }

    .persona-banner-line.animate {
        display: block;
        animation: cutInSlice 1.4s cubic-bezier(0.15, 0.85, 0.15, 1) forwards;
    }

    .persona-eyes-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 35%;
        transform: scale(1.1);
    }

    .persona-banner-line.animate .persona-eyes-img {
        animation: eyeGlance 1.4s cubic-bezier(0.1, 0.8, 0.1, 1) forwards;
    }

    .enemy-jolt { animation: snappyJolt 0.15s ease-in-out both; }
    .shake-trigger { animation: snappyJolt 0.15s ease-in-out both; }
    .enemy-hover-float { animation: battleFloat 3.2s ease-in-out infinite; }

    .turn-card {
        border-radius: 6px;
        border: 2px solid #5A3A2A;
        transition: all 0.25s ease;
    }

    .turn-card.active-unit-highlight {
        border-color: #ff9d41 !important;
        box-shadow: 0 0 10px rgba(255, 157, 65, 0.6);
        transform: scale(1.05);
    }

    .enemy-container {
        transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.2s ease, outline 0.2s ease;
    }

    .enemy-sprite { transition: filter 0.15s ease-in-out; }
    .enemy-hurt { filter: brightness(0.6) sepia(1) hue-rotate(-50deg) saturate(6) opacity(0.6) !important; }
    .active-turn-scale { transform: scale(1.5) !important; z-index: 10; }

    #hud-interaction-deck { transition: transform 0.3s ease, outline 0.2s ease; }

    .active-player-stage-glow {
        outline: 4px solid #ff9d41 !important;
        outline-offset: 2px;
        box-shadow: 0 0 15px 4px rgba(255, 157, 65, 0.6) !important;
        border-radius: 4px;
    }

    #btn-attack, .skill-btn { transition: all 0.15s ease; }

    /* --- 🔮 RPG TOOLTIP PANEL GRAPHICS --- */
    #rpg-cursor-tooltip {
        position: fixed;
        padding: 10px 14px;
        background: #2D1B13;
        color: #F3E5D8;
        font-size: 0.85rem;
        border: 2px solid #ff9d41;
        border-radius: 6px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.7), inset 0 0 6px rgba(255, 157, 65, 0.2);
        z-index: 10000;
        pointer-events: none;
        display: none;
        max-width: 260px;
        line-height: 1.35;
        text-shadow: 1px 1px 2px #000;
        transform: translate(15px, 15px);
    }
</style>

<div id="combat-preloader">
    <h3 class="text-white mb-2 fw-bold" style="letter-spacing: 2px; text-shadow: 2px 2px 0px #000;">ENCOUNTER IMMINENT</h3>
    <button class="preloader-ready-btn" onclick="uncageAudioAndStartCombat()">READY</button>
</div>

<div class="persona-cutin-overlay" id="cutin-dimmer"></div>
<div class="persona-banner-line" id="ultimate-cutin-line">
    <img src="../../asset/sprites/classes/<?= htmlspecialchars($player_data['avatar'] ?? 'player_avatar.png') ?>" class="persona-eyes-img" id="cutin-eyes-asset" alt="Eyes View">
</div>

<div id="rpg-cursor-tooltip"></div>

<main>
    <div class="d-flex flex-column justify-content-between h-100 p-3" style="user-select: none;">
        <div class="damage-popup text-danger fw-bold position-absolute d-none fs-2" id="player-damage-pop" style="z-index: 99; top: 25%; left: 15%; text-shadow: 2px 2px 0px #000;"> -0 </div>

        <div class="battle-stage d-flex align-items-center justify-content-around flex-grow-1 mb-3"
            style="min-height: 200px; background-image: radial-gradient(circle, #b4694000 60%, #b4694065 90%), url('../../asset/img/background/plains1.png'); background-size: auto, contain; background-position: center; background-repeat: no-repeat; background-color:#FAC79B; border-radius: 12px; border: 4px solid #8B5A3C;">
            <div class="enemy-party d-flex gap-4 align-items-end justify-content-center w-100" style="padding-bottom: 60px;">
                <?php if (!empty($active_enemies)): ?>
                    <?php foreach ($active_enemies as $index => $enemy):
                        $enemyUniqueId = $enemy['id'];
                    ?>
                        <div class="enemy-container d-flex flex-column align-items-center justify-content-end text-center position-relative"
                            id="enemy-target-<?= $enemyUniqueId ?>"
                            style="min-width: 120px; <?= !$enemy['alive'] ? 'opacity: 0.4;' : '' ?>">

                            <div class="damage-popup text-warning fw-bold position-absolute top-0 start-50 translate-middle fs-2 d-none" id="enemy-damage-<?= $enemyUniqueId ?>" style="z-index: 99; text-shadow: 2px 2px 0px #000;">-0</div>

                            <div id="enemy-statuses-<?= $enemyUniqueId ?>" class="d-flex gap-1 mb-1 justify-content-center flex-wrap" style="min-height: 20px; width:100%; z-index:5;"></div>

                            <div class="progress mb-2 w-100" style="max-width: 100px; height: 10px; background-color: rgba(0,0,0,0.3); border-radius: 4px;">
                                <div id="enemy-hp-bar-<?= $enemyUniqueId ?>"
                                    class="progress-bar bg-danger"
                                    role="progressbar"
                                    style="width: <?= ($enemy['hp'] / $enemy['max_hp']) * 100 ?>%; transition: width 0.3s ease;"
                                    aria-valuenow="<?= $enemy['hp'] ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="<?= $enemy['max_hp'] ?>"></div>
                            </div>

                            <?php if (!empty($enemy['sprite'])): ?>
                                <div class="d-flex align-items-end justify-content-center" style="height: 180px; width: 100%;">
                                    <img src="../../asset/sprites/enemies/lv1/<?= htmlspecialchars($enemy['sprite']) ?>"
                                        class="enemy-sprite enemy-hover-float item-rendering-pixelated"
                                        style="max-height: 100%; max-width: 100%; object-fit: contain; filter: drop-shadow(0px 8px 4px rgba(0,0,0,0.2)); animation-delay: <?= $index * 0.45 ?>s;"
                                        alt="<?= htmlspecialchars($enemy['name']) ?>"
                                        onerror="this.src='https://placehold.co/120x180?text=Enemy'">
                                </div>
                            <?php endif; ?>

                            <span class="badge mt-2" style="background-color: #B46940; font-size: 0.85rem; width: fit-content; border: 1px solid #5A3A2A;">
                                <?= htmlspecialchars($enemy['name']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted">No enemies spotted in this area.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-2 pb-2">
            <div class="col-9">
                <div class="col-12 p-2 rounded mb-2 d-flex align-items-center gap-3" style="background-color: #FAC79B; border: 2px solid #8B5A3C;">
                    <div class="fw-bold text-dark style-font" style="font-size: 0.85rem; white-space: nowrap;">
                        🔮 MANA: <span id="player-mana-display">1</span> / 10
                    </div>
                    <div class="progress flex-grow-1" style="height: 12px; background-color: rgba(0,0,0,0.3); border-radius: 6px;">
                        <div id="player-mana-bar"
                            class="progress-bar bg-info progress-bar-striped progress-bar-animated"
                            role="progressbar"
                            style="width: 100%; transition: width 0.4s ease, background-color 0.3s ease;"
                            aria-valuenow="10"
                            aria-valuemin="0"
                            aria-valuemax="10"></div>
                    </div>
                </div>

                <div class="p-3 d-flex flex-column gap-2 justify-content-center" id="hud-interaction-deck" style="background-color: #FAC79B; min-height: 100px; height: 180px; border-radius: 12px; border: 2px solid #8B5A3C; overflow-y: auto;">
                    <div id="skills-panel" style="display: block;">
                        <?php if (!empty($player_skills)): ?>
                            <div class="row g-2">
                                <?php foreach ($player_skills as $skill): ?>
                                    <div class="col-6">
                                        <button type="button" class="btn w-100 skill-btn fw-bold py-2 position-relative d-flex flex-column align-items-center justify-content-center"
                                            id="skill-btn-<?= htmlspecialchars($skill['skill_id']) ?>"
                                            style="background-color: #8B5A3C; color: white; border: 2px solid #5A3A2A; border-radius: 8px;"
                                            onclick="triggerSkillSelection(<?= intval($skill['skill_id']) ?>, this)"
                                            data-skill-id="<?= htmlspecialchars($skill['skill_id']) ?>"
                                            data-tooltip="📝 <?= htmlspecialchars($skill['skill_desc'] ?? 'Skill calculation description profile index.') ?>">
                                            <div><?= htmlspecialchars($skill['skill_name']) ?></div>
                                            <small class="text-warning" style="font-size:0.75rem;">
                                                🔮 Cost: <?= htmlspecialchars($skill['mana_cost']) ?> | 🎯 Area: <?= htmlspecialchars(ucfirst($skill['skill_area'] ?? 'Enemy')) ?>
                                            </small>
                                            <div class="position-absolute top-50 start-50 translate-middle w-100 h-100 d-none align-items-center justify-content-center rounded bg-dark bg-opacity-75 cd-overlay" style="z-index: 5; color: #fff; font-size: 1.2rem;">0</div>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-2">
                                <small>No actions learned yet</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="targets-panel" style="display: none;">
                        <div class="d-flex flex-column gap-1">
                            <div class="text-dark fw-bold mb-1" style="font-size: 0.9rem;" id="target-instructions-label">Select Encounter Target:</div>
                            <div class="d-flex gap-2 flex-wrap" id="targets-button-group">
                                <?php foreach ($active_enemies as $enemy): ?>
                                    <button type="button" class="btn flex-grow-1 enemy-target-btn fw-bold py-2"
                                        id="list-target-btn-<?= $enemy['id'] ?>"
                                        style="background-color: #B46940; color: white; border: 2px solid #8B4513; border-radius: 8px; text-align: left; font-size: 0.85rem;"
                                        onclick="selectEnemyTarget(<?= intval($enemy['id'] ?? 0) ?>)">
                                        🎯 <?= htmlspecialchars($enemy['name']) ?> <small style="opacity: 0.85;">(HP: <span id="list-hp-<?= $enemy['id'] ?>"><?= intval($enemy['hp'] ?? 0) ?></span>/<?= intval($enemy['max_hp'] ?? 0) ?>)</small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm fw-bold py-1 mt-2 mx-auto text-white" style="background-color: #555; border: none; border-radius: 6px; width: 100px;" onclick="cancelSkillSelection()">Back</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-3">
                <div class="p-2 d-flex flex-column gap-2 justify-content-center h-100" style="background-color: #FAC79B; border-radius: 12px; border: 2px solid #8B5A3C; min-height: 180px;">
                    <button type="button" id="btn-attack" onclick="executePlayerAction('open_skills')" class="btn btn-lg text-white w-100 py-2 fw-bold combat-btn shadow-sm" style="background-color: #B46940; border-radius: 8px; border:3px #ff9d41 solid; height:80px; text-shadow: 1px 1px 2px #000;">Attack!</button>
                    <div class="row g-2">
                        <div class="col-6 mt-2">
                            <button type="button" class="btn btn-dark w-100 fw-bold shadow-sm" id="btn-bag" style="background-color: #8B5A3C; border: 2px solid #5A3A2A; color:#fff; height:60px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalInventory">Bag</button>
                        </div>
                        <div class="col-6 mt-2">
                            <button type="button" id="btn-defend" onclick="executePlayerAction('defend')" class="btn text-white w-100 py-2 fw-bold combat-btn shadow-sm" style="background-color: #8B5A3C; border: 2px solid #5A3A2A; border-radius: 8px; height:60px; font-size: 0.85rem;">Defend</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="p-3 text-white d-flex align-items-center" id="combat-dialogue-box" style="background-color: #D39670; border-radius: 12px; max-height: 55px; font-size: 1.05rem; border: 3px solid #FAC79B; overflow-y: auto;">
                    <p class="m-0" id="combat-log-text">Ready for Battle!</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    if (typeof window.combatState === 'undefined') {
        window.combatState = {
            player: {
                id: parseInt(<?= json_encode($player_id); ?>),
                name: <?= json_encode($player_data['name']); ?>,
                hp: parseInt(<?= json_encode($current_hp); ?>) || 100,
                maxHp: parseInt(<?= json_encode($max_hp); ?>) || 100,
                atk: parseInt(<?= json_encode($player_data['curr_str'] ?? 10); ?>) || 10,
                def: parseInt(<?= json_encode($player_data['curr_def'] ?? 5); ?>) || 5,
                text: '',
                dex: parseInt(<?= json_encode($player_dex); ?>) || 10,
                int: parseInt(<?= json_encode($player_data['curr_int'] ?? 10); ?>) || 10,
                fth: parseInt(<?= json_encode($player_data['curr_fth'] ?? 10); ?>) || 10,
                mana: 10,
                isDefending: false
            },
            enemies: <?= json_encode($active_enemies); ?>,
            turnOrder: <?= json_encode($turn_order_stack); ?>,
            inventory: <?= json_encode($player_inventory); ?>,
            skills: <?= json_encode($player_skills); ?>,
            skillCooldowns: {},
            selectedSkill: null,
            currentTurnIndex: 0,
            isPlayerTurn: false,
            battleFinished: false
        };
    } else {
        window.combatState.player.id = parseInt(<?= json_encode($player_id); ?>);
        window.combatState.player.hp = window.combatState.player.hp ?? parseInt(<?= json_encode($current_hp); ?>);
        window.combatState.player.maxHp = window.combatState.player.maxHp ?? parseInt(<?= json_encode($max_hp); ?>);

        window.combatState.player.atk = parseInt(<?= json_encode($player_data['curr_str'] ?? 10); ?>) || 10;
        window.combatState.player.def = parseInt(<?= json_encode($player_data['curr_def'] ?? 5); ?>) || 5;
        window.combatState.player.dex = parseInt(<?= json_encode($player_dex); ?>) || 10;
        window.combatState.player.int = parseInt(<?= json_encode($player_data['curr_int'] ?? 10); ?>) || 10;
        window.combatState.player.fth = parseInt(<?= json_encode($player_data['curr_fth'] ?? 10); ?>) || 10;

        window.combatState.player.mana = window.combatState.player.mana ?? 10;
        window.combatState.player.isDefending = false;
        window.combatState.enemies = <?= json_encode($active_enemies); ?>;
        window.combatState.turnOrder = <?= json_encode($turn_order_stack); ?>;
        window.combatState.skillCooldowns = window.combatState.skillCooldowns ?? {};
        window.combatState.selectedSkill = null;
        window.combatState.currentTurnIndex = 0;
        window.combatState.isPlayerTurn = false;
        window.combatState.battleFinished = false;
    }

    if (typeof window.battleAudioInstance === 'undefined') {
        window.battleAudioInstance = new Audio('../../asset/bgm/battle_bgm.mp3');
        window.battleAudioInstance.loop = true;
        window.battleAudioInstance.volume = 0.02;
    }

    if (typeof window.sfx === 'undefined') {
        window.sfx = {
            hover: new Audio('../../asset/sfx/hover.wav'),
            select: new Audio('../../asset/sfx/select.wav'),
            cancel: new Audio('../../asset/sfx/cancel.wav'),
            purchase: new Audio('../../asset/sfx/purchace_complete.wav'),
            ultimate: new Audio('../../asset/sfx/ultimate.wav'),
            ultimateUnleash: new Audio('../../asset/sfx/ultimateunleash.wav'),
            enemyAttack: new Audio('../../asset/sfx/enemyattack.wav'),
            playerAttack: new Audio('../../asset/sfx/playerattack.wav'),
        };

        window.sfx.hover.volume = 0.20;
        window.sfx.select.volume = 0.15;
        window.sfx.cancel.volume = 0.35;
        window.sfx.purchase.volume = 0.40;
        window.sfx.ultimate.volume = 0.60;
        window.sfx.ultimateUnleash.volume = 0.55;
        window.sfx.enemyAttack.volume = 0.65;
        window.sfx.playerAttack.volume = 0.55;
    }

    function playSFX(soundName) {
        if (window.sfx && window.sfx[soundName]) {
            window.sfx[soundName].currentTime = 0;
            window.sfx[soundName].play().catch(() => {});
        }
    }

    const statusEffectMap = {
        1: { name: 'Poison', icon: 'poison.png', dotDmg: 5 },
        2: { name: 'Burn', icon: 'burn.png', dotDmg: 8 },
        3: { name: 'Bleed', icon: 'bleed.png', dotDmg: 10 },
        4: { name: 'Freezerburn', icon: 'freezerburn.png', dotDmg: 12 }
    };

    function renderStatusEffectsUI(enemy) {
        const container = document.getElementById(`enemy-statuses-${enemy.id}`);
        if (!container) return;
        container.innerHTML = '';
        if (!enemy.active_statuses || enemy.active_statuses.length === 0) return;

        enemy.active_statuses.forEach(status => {
            const effectData = statusEffectMap[status.status_effect_id];
            if (effectData) {
                container.innerHTML += `
                    <div class="position-relative" title="${effectData.name} (${status.duration_left} turns)">
                        <img src="../../asset/status_effects/${effectData.icon}" style="width: 20px; height: 20px;border: 1px solid #D39670; margin: 0 3px;background-color:#FAC79B; border-radius: 4px;" alt="${effectData.name}">
                        <span class="position-absolute badge rounded-pill" style="font-size: 0.52rem; padding: 0.15em 0.3em; bottom: -4px; right: -2px; color:#000;">
                            ${status.duration_left}
                        </span>
                    </div>
                `;
            }
        });
    }

    function evaluateSkillTargetType(skill) {
        if (!skill) return 'enemy';
        let rawData = skill.skill_area || skill.area || skill.target || skill.target_type || '';
        let cleanText = String(rawData).toLowerCase().trim();
        let descText = String(skill.skill_desc || '').toLowerCase();

        if (cleanText === 'all' || cleanText === 'aoe' || cleanText === 'enemy_all' || cleanText.includes('all') || cleanText.includes('aoe') ||
            descText.includes('all enemies') || descText.includes('aoe')) {
            return 'aoe';
        }
        if (cleanText === 'self' || cleanText === 'player' || cleanText.includes('self') ||
            descText.includes('self') || descText.includes('heal yourself')) {
            return 'self';
        }
        return 'enemy';
    }

    function tryUncageBattleMusic() {
        if (window.battleAudioInstance && window.battleAudioInstance.paused && !window.combatState.battleFinished) {
            window.battleAudioInstance.play().catch(() => {});
        }
    }

    function fadeOutBattleMusicAndClose(durationMs = 800) {
        if (!window.battleAudioInstance || window.battleAudioInstance.paused) return;
        const startVolume = window.battleAudioInstance.volume;
        const fadeInterval = 50;
        const totalSteps = durationMs / fadeInterval;
        const volumeSubtractStep = startVolume / totalSteps;

        let currentStep = 0;
        const faderIntervalLoop = setInterval(() => {
            currentStep++;
            if (window.battleAudioInstance.volume - volumeSubtractStep > 0) {
                window.battleAudioInstance.volume -= volumeSubtractStep;
            } else {
                window.battleAudioInstance.volume = 0;
            }

            if (currentStep >= totalSteps || window.battleAudioInstance.volume <= 0) {
                clearInterval(faderIntervalLoop);
                window.battleAudioInstance.pause();
                window.battleAudioInstance.currentTime = 0;
                window.battleAudioInstance.volume = startVolume;
            }
        }, fadeInterval);
    }

    function triggerPersonaCutInSequence(callbackAction) {
        const dimmer = document.getElementById('cutin-dimmer');
        const banner = document.getElementById('ultimate-cutin-line');
        if (!dimmer || !banner) {
            if (callbackAction) callbackAction();
            return;
        }

        dimmer.classList.add('active');
        banner.classList.add('animate');
        playSFX('ultimate');

        setTimeout(() => {
            dimmer.classList.remove('active');
            banner.classList.remove('animate');
            if (callbackAction) callbackAction();
        }, 1400);
    }

    function updateTurnOrderSidebar() {
        const listContainer = document.getElementById('turn-sidebar-list');
        if (!listContainer) return;

        let htmlContent = '';
        const activeUnit = window.combatState.activeUnit;

        window.combatState.turnOrder.forEach(combatant => {
            let isAlive = true;
            let currentHp = 1;
            let maxHp = 1;

            if (combatant.type === 'enemy') {
                const enemyUnit = window.combatState.enemies.find(e => e.id == combatant.id);
                if (enemyUnit) {
                    isAlive = enemyUnit.alive;
                    currentHp = enemyUnit.hp;
                    maxHp = enemyUnit.max_hp;
                }
            } else {
                isAlive = window.combatState.player.hp > 0;
                currentHp = window.combatState.player.hp;
                maxHp = window.combatState.player.maxHp;
            }

            if (!isAlive) return;

            const isActive = (activeUnit && combatant.type === activeUnit.type && combatant.id == activeUnit.id);
            const bgColor = combatant.type === 'player' ? '#8B5A3C' : '#B46940';
            const imgPath = combatant.type === 'player' ? `../../asset/sprites/classes/${combatant.sprite}` : `../../asset/sprites/enemies/lv1/${combatant.sprite}`;

            htmlContent += `
                <div class="turn-card p-2 d-flex align-items-center gap-2 ${isActive ? 'active-unit-highlight' : ''}" 
                     style="background-color: ${bgColor}; color: white;">
                    <div style="width: 35px; height: 35px; border-radius: 4px; overflow: hidden; background-color: rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.25);">
                        <img src="${imgPath}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.src='https://placehold.co/35x35?text=?';">
                    </div>
                    <div class="flex-grow-1" style="line-height: 1.1;">
                        <div class="fw-bold" style="font-size: 0.85rem;">${combatant.name} ${isActive ? '👑' : ''}</div>
                        <small style="font-size: 0.72rem; opacity: 0.85;">HP: ${currentHp}/${maxHp} | ⚡ DEX: ${combatant.dex}</small>
                    </div>
                </div>
            `;
        });

        listContainer.innerHTML = htmlContent || '<div class="text-center text-muted py-2"><small>No actions left</small></div>';
    }

    function runNextTurnSequence() {
        if (window.combatState.battleFinished) return;

        const livingEnemies = window.combatState.enemies.filter(e => e.alive);
        if (livingEnemies.length === 0) { processBattleEndVictory(); return; }
        if (window.combatState.player.hp <= 0) { processBattleEndDefeat(); return; }

        let index = window.combatState.currentTurnIndex;
        let combatant = window.combatState.turnOrder[index];

        if (combatant.type === 'enemy') {
            const enemyUnit = window.combatState.enemies.find(e => e.id == combatant.id);
            if (!enemyUnit || !enemyUnit.alive) { advanceTurnPointer(); return; }
        }

        window.combatState.activeUnit = combatant;
        if (typeof updateTurnOrderSidebar === 'function') updateTurnOrderSidebar();
        manageVisualStageHighlights(combatant);

        if (combatant.type === 'player') {
            window.combatState.isPlayerTurn = true;
            toggleHUDControls(true);
            updateCombatLog(`${window.combatState.player.name}'s turn! Pick a skill down below.`);
        } else {
            window.combatState.isPlayerTurn = false;
            toggleHUDControls(false);
            document.getElementById('targets-panel').style.display = 'none';
            document.getElementById('skills-panel').style.display = 'block';
            setTimeout(() => { executeEnemyAutomatedTurn(combatant.id); }, 1200);
        }
    }

    function manageVisualStageHighlights(activeUnit) {
        document.querySelectorAll('.enemy-container').forEach(el => {
            el.classList.remove('active-player-stage-glow', 'active-turn-scale');
        });

        if (activeUnit.type === 'enemy' || activeUnit.type === 'boss') {
            const targetEl = document.getElementById(`enemy-target-${activeUnit.id}`);
            if (targetEl) targetEl.classList.add('active-turn-scale');
        }
    }

    function advanceTurnPointer() {
        if (window.combatState.battleFinished) return;

        let nextIndex = window.combatState.currentTurnIndex + 1;
        if (nextIndex >= window.combatState.turnOrder.length) {
            window.combatState.currentTurnIndex = 0;
            startNewRoundUpkeep();
        } else {
            window.combatState.currentTurnIndex = nextIndex;
            runNextTurnSequence();
        }
    }

    function startNewRoundUpkeep() {
        Object.keys(window.combatState.skillCooldowns).forEach(skillId => {
            if (window.combatState.skillCooldowns[skillId] > 0) {
                window.combatState.skillCooldowns[skillId]--;
            }
        });

        window.combatState.player.isDefending = false;

        let baseManaRegen = 1;
        const intStat = parseInt(window.combatState.player.int || 10);
        const doubleManaChance = Math.min(0.75, intStat * 0.02);

        if (Math.random() <= doubleManaChance) {
            baseManaRegen = 2;
            updateCombatLog("✨ INT Proc! Mimi recovers 2 Mana this turn!");
        } else {
            updateCombatLog("Mimi recovers 1 Mana.");
        }

        window.combatState.player.mana = Math.min(10, window.combatState.player.mana + baseManaRegen);
        syncManaBarInterfaceDisplay();
        updateSkillButtonsCooldownUI();

        runNextTurnSequence();
    }

    function dynamicRebuildTargetsLayoutDeck() {
        const buttonGroup = document.getElementById('targets-button-group');
        if (!buttonGroup) return;

        const skill = window.combatState.selectedSkill;
        if (!skill) return;

        const parsedTargetType = evaluateSkillTargetType(skill);
        let contentHtml = '';

        if (parsedTargetType === 'aoe') {
            contentHtml = `
                <button type="button" class="btn w-100 fw-bold py-3 text-white pulse-glow-btn"
                    id="all-targets-aoe-btn"
                    style="background-color: #dc3545; border: 3px solid #900c1c; border-radius: 8px; font-size: 1.1rem; text-shadow: 1px 1px 2px #000;"
                    onmouseenter="playSFX('hover')"
                    onclick="executeAoESkillFromPanel()">
                    💥 Strike All Active Enemies!
                </button>
            `;
        } else {
            window.combatState.enemies.forEach(enemy => {
                contentHtml += `
                    <button type="button" class="btn flex-grow-1 enemy-target-btn fw-bold py-2"
                        id="list-target-btn-${enemy.id}"
                        style="background-color: #B46940; color: white; border: 2px solid #8B4513; border-radius: 8px; text-align: left; font-size: 0.85rem;"
                        ${!enemy.alive ? 'disabled' : ''}
                        onmouseenter="if(!this.disabled) playSFX('hover')"
                        onclick="selectEnemyTarget(${parseInt(enemy.id)})">
                        🎯 ${enemy.name} <small style="opacity: 0.85;">(HP: <span id="list-hp-${enemy.id}">${parseInt(enemy.hp)}</span>/${parseInt(enemy.max_hp)})</small>
                    </button>
                `;
            });
        }

        buttonGroup.innerHTML = contentHtml;
    }

    function toggleHUDControls(enable) {
        const attackBtn = document.getElementById('btn-attack');
        const defendBtn = document.getElementById('btn-defend');
        const bagBtn = document.getElementById('btn-bag');

        if (attackBtn) {
            attackBtn.disabled = !enable;
            attackBtn.style.filter = enable ? 'none' : 'grayscale(60%)';
        }
        if (defendBtn) {
            defendBtn.disabled = !enable;
            defendBtn.style.filter = enable ? 'none' : 'grayscale(60%)';
        }
        if (bagBtn) bagBtn.disabled = !enable;
    }

    function selectEnemyTarget(enemyId) {
        if (window.combatState.battleFinished || window.combatState.isPlayerTurn === false) return;

        playSFX('select');
        tryUncageBattleMusic();

        const targetEnemy = window.combatState.enemies.find(e => e.id == enemyId);
        if (!targetEnemy || !targetEnemy.alive) return;

        window.combatState.isPlayerTurn = false;
        toggleHUDControls(false);

        let skillName = String(window.combatState.selectedSkill?.skill_name || '').toLowerCase();
        if (skillName.includes('ultimate')) {
            triggerPersonaCutInSequence(() => {
                playSFX('playerAttack');
                executeSkillOnTarget(window.combatState.selectedSkill, targetEnemy);
            });
        } else {
            playSFX('playerAttack');
            executeSkillOnTarget(window.combatState.selectedSkill, targetEnemy);
        }
    }

    function executeAoESkillFromPanel() {
        if (window.combatState.battleFinished || window.combatState.isPlayerTurn === false) return;

        playSFX('select');
        tryUncageBattleMusic();
        window.combatState.isPlayerTurn = false;
        toggleHUDControls(false);

        let skillName = String(window.combatState.selectedSkill?.skill_name || '').toLowerCase();
        if (skillName.includes('ultimate')) {
            triggerPersonaCutInSequence(() => {
                playSFX('playerAttack');
                executeAoESkill(window.combatState.selectedSkill);
            });
        } else {
            playSFX('playerAttack');
            executeAoESkill(window.combatState.selectedSkill);
        }
    }

    function executePlayerAction(actionType) {
        if (!window.combatState.isPlayerTurn || window.combatState.battleFinished) return;
        tryUncageBattleMusic();

        if (actionType === 'open_skills') {
            cancelSkillSelection();
            updateCombatLog("Choose an offensive execution skill from the option cards below.");
        }

        if (actionType === 'defend') {
            playSFX('select');
            window.combatState.player.isDefending = true;
            updateCombatLog(`${window.combatState.player.name} takes a defensive stance! Defense is doubled.`);
            consumeResourcesAndApplyCooldowns();
            setTimeout(advanceTurnPointer, 1500);
        }
    }

    function triggerSkillSelection(skillId, element) {
        if (!window.combatState.isPlayerTurn || window.combatState.battleFinished) return;

        const skill = window.combatState.skills.find(s => s.skill_id == skillId);
        if (!skill) return;

        if (window.combatState.player.mana < skill.mana_cost) {
            updateCombatLog(`🔮 Not enough mana! Requires ${skill.mana_cost} Mana.`);
            const manaBar = document.getElementById('player-mana-bar');
            if (manaBar) {
                manaBar.classList.replace('bg-info', 'bg-danger');
                setTimeout(() => manaBar.classList.replace('bg-danger', 'bg-info'), 400);
            }
            return;
        }

        if (window.combatState.skillCooldowns[skill.skill_id] > 0) {
            updateCombatLog(`⏳ Skill is on cooldown for ${window.combatState.skillCooldowns[skill.skill_id]} more turn(s)!`);
            return;
        }

        window.combatState.selectedSkill = skill;
        playSFX('select');

        document.querySelectorAll('.skill-btn').forEach(btn => btn.style.opacity = '0.5');

        if (element) {
            element.style.opacity = '1';
            element.style.boxShadow = '0 0 10px #ff9d41';
        }

        const parsedTargetType = evaluateSkillTargetType(skill);

        if (parsedTargetType === 'self') {
            window.combatState.isPlayerTurn = false;
            let skillName = String(skill.skill_name || '').toLowerCase();
            if (skillName.includes('ultimate')) {
                triggerPersonaCutInSequence(() => {
                    executeSkillOnSelf(skill);
                });
            } else {
                executeSkillOnSelf(skill);
            }
        } else {
            dynamicRebuildTargetsLayoutDeck();

            if (parsedTargetType === 'aoe') {
                document.getElementById('target-instructions-label').innerText = `Confirm Area Execution for [${skill.skill_name}]:`;
            } else {
                document.getElementById('target-instructions-label').innerText = `Select target for [${skill.skill_name}]:`;
            }

            document.getElementById('skills-panel').style.display = 'none';
            document.getElementById('targets-panel').style.display = 'block';
            updateCombatLog(`${skill.skill_name} selected. Confirm execution down in the layout deck.`);
        }
    }

    function cancelSkillSelection() {
        playSFX('cancel');
        window.combatState.selectedSkill = null;

        const atkBtn = document.getElementById('btn-attack');
        if (atkBtn) {
            atkBtn.style.boxShadow = 'none';
            atkBtn.style.transform = 'none';
        }

        document.querySelectorAll('.skill-btn').forEach(btn => {
            btn.style.opacity = '1';
            btn.style.boxShadow = 'none';
        });

        document.getElementById('targets-panel').style.display = 'none';
        document.getElementById('skills-panel').style.display = 'block';
    }

    function consumeResourcesAndApplyCooldowns() {
        if (window.combatState.selectedSkill && window.combatState.selectedSkill.skill_name !== 'Attack') {
            window.combatState.player.mana -= window.combatState.selectedSkill.mana_cost;
            syncManaBarInterfaceDisplay();

            if (window.combatState.selectedSkill.skill_id) {
                window.combatState.skillCooldowns[window.combatState.selectedSkill.skill_id] = window.combatState.selectedSkill.cooldown;
            }
        }
    }

    function syncManaBarInterfaceDisplay() {
        const manaVal = window.combatState.player.mana;
        const manaText = document.getElementById('player-mana-display');
        if (manaText) manaText.innerText = manaVal;

        const manaBar = document.getElementById('player-mana-bar');
        if (manaBar) {
            const percentage = Math.min(100, Math.max(0, (manaVal / 10) * 100));
            manaBar.style.width = `${percentage}%`;
            manaBar.setAttribute('aria-valuenow', manaVal);
        }
    }

    function executeAoESkill(skill) {
        updateCombatLog(`${window.combatState.player.name} unleashes ${skill ? skill.skill_name : 'Attack'} across the enemy line!`);
        consumeResourcesAndApplyCooldowns();

        const pStr = parseInt(window.combatState.player.atk || 10);
        const pDef = parseInt(window.combatState.player.def || 5);
        const pDex = parseInt(window.combatState.player.dex || 10);
        const pInt = parseInt(window.combatState.player.int || 10);
        const pFth = parseInt(window.combatState.player.fth || 10);

        const strMod = parseFloat(skill?.skill_str || 0) / 100;
        const defMod = parseFloat(skill?.skill_def || 0) / 100;
        const dexMod = parseFloat(skill?.skill_dex || 0) / 100;
        const intMod = parseFloat(skill?.skill_int || 0) / 100;
        const fthMod = parseFloat(skill?.skill_fth || 0) / 100;

        window.combatState.enemies.forEach(enemy => {
            if (!enemy.alive) return;

            let calculatedDamage = 0;
            if (strMod > 0 || defMod > 0 || dexMod > 0 || intMod > 0 || fthMod > 0) {
                calculatedDamage = (pStr * strMod) + (pDef * defMod) + (pDex * dexMod) + (pInt * intMod) + (pFth * fthMod);
            } else {
                calculatedDamage = pStr;
            }

            const skillDamage = Math.max(1, Math.floor(calculatedDamage - enemy.def));
            enemy.hp -= skillDamage;
            if (enemy.hp <= 0) {
                enemy.hp = 0;
                enemy.alive = false;
            }

            if (skill && skill.effects && skill.effects.length > 0) {
                enemy.active_statuses = enemy.active_statuses || [];
                skill.effects.forEach(effect => {
                    enemy.active_statuses.push({
                        status_effect_id: effect.status_effect_id,
                        duration_left: effect.stacks,
                        inflicted_by: 'player'
                    });
                });
                renderStatusEffectsUI(enemy);
            }

            const container = document.getElementById(`enemy-target-${enemy.id}`);
            if (container) {
                container.classList.add('enemy-jolt');
                setTimeout(() => container.classList.remove('enemy-jolt'), 150);

                const sprite = container.querySelector('.enemy-sprite');
                if (sprite) {
                    sprite.classList.add('enemy-hurt');
                    setTimeout(() => sprite.classList.remove('enemy-hurt'), 250);
                }
            }

            const hpBar = document.getElementById(`enemy-hp-bar-${enemy.id}`);
            if (hpBar) hpBar.style.width = `${(enemy.hp / enemy.max_hp) * 100}%`;

            const listHp = document.getElementById(`list-hp-${enemy.id}`);
            if (listHp) listHp.innerText = enemy.hp;

            const dmgPop = document.getElementById(`enemy-damage-${enemy.id}`);
            if (dmgPop) {
                dmgPop.innerText = `-${skillDamage}`;
                dmgPop.classList.remove('d-none');
                setTimeout(() => dmgPop.classList.add('d-none'), 1200);
            }

            if (!enemy.alive) {
                const btn = document.getElementById('list-target-btn-' + enemy.id);
                if (btn) btn.disabled = true;
                setTimeout(() => {
                    const container = document.getElementById(`enemy-target-${enemy.id}`);
                    if (container) container.style.opacity = '0.4';
                }, 1000);
            }
        });

        setTimeout(() => {
            clearVisualIndicators();
            checkBattleResult();
        }, 1500);
    }

    function executeSkillOnTarget(skill, enemy) {
        consumeResourcesAndApplyCooldowns();

        let skillName = String(skill?.skill_name || '').toLowerCase();
        if (skillName && skillName.includes('ultimate')) {
            playSFX('ultimateUnleash');
        }

        const pStr = parseInt(window.combatState.player.atk || 10);
        const pDef = parseInt(window.combatState.player.def || 5);
        const pDex = parseInt(window.combatState.player.dex || 10);
        const pInt = parseInt(window.combatState.player.int || 10);
        const pFth = parseInt(window.combatState.player.fth || 10);

        const strMod = parseFloat(skill?.skill_str || 0) / 100;
        const defMod = parseFloat(skill?.skill_def || 0) / 100;
        const dexMod = parseFloat(skill?.skill_dex || 0) / 100;
        const intMod = parseFloat(skill?.skill_int || 0) / 100;
        const fthMod = parseFloat(skill?.skill_fth || 0) / 100;

        let calculatedDamage = 0;
        if (strMod > 0 || defMod > 0 || dexMod > 0 || intMod > 0 || fthMod > 0) {
            calculatedDamage = (pStr * strMod) + (pDef * defMod) + (pDex * dexMod) + (pInt * intMod) + (pFth * fthMod);
        } else {
            calculatedDamage = pStr;
        }

        const skillDamage = Math.max(1, Math.floor(calculatedDamage - enemy.def));
        enemy.hp -= skillDamage;
        if (enemy.hp <= 0) {
            enemy.hp = 0;
            enemy.alive = false;
        }

        if (skill && skill.effects && skill.effects.length > 0) {
            enemy.active_statuses = enemy.active_statuses || [];
            skill.effects.forEach(effect => {
                enemy.active_statuses.push({
                    status_effect_id: effect.status_effect_id,
                    duration_left: effect.stacks,
                    inflicted_by: 'player'
                });
            });
            renderStatusEffectsUI(enemy);
            updateCombatLog(`💥 ${window.combatState.player.name} inflicts lethal status effects onto ${enemy.name}!`);
        } else {
            updateCombatLog(`${window.combatState.player.name} uses ${skill ? skill.skill_name : 'Attack'} on ${enemy.name} for ${skillDamage} damage!`);
        }

        const container = document.getElementById(`enemy-target-${enemy.id}`);
        if (container) {
            container.classList.add('enemy-jolt');
            setTimeout(() => container.classList.remove('enemy-jolt'), 150);

            const sprite = container.querySelector('.enemy-sprite');
            if (sprite) {
                sprite.classList.add('enemy-hurt');
                setTimeout(() => sprite.classList.remove('enemy-hurt'), 250);
            }
        }

        const hpBar = document.getElementById(`enemy-hp-bar-${enemy.id}`);
        if (hpBar) hpBar.style.width = `${(enemy.hp / enemy.max_hp) * 100}%`;

        const listHp = document.getElementById(`list-hp-${enemy.id}`);
        if (listHp) listHp.innerText = enemy.hp;

        const dmgPop = document.getElementById(`enemy-damage-${enemy.id}`);
        if (dmgPop) {
            dmgPop.innerText = `-${skillDamage}`;
            dmgPop.classList.remove('d-none');
            setTimeout(() => dmgPop.classList.add('d-none'), 1200);
        }

        if (!enemy.alive) {
            const btn = document.getElementById(`list-target-btn-${enemy.id}`);
            if (btn) btn.disabled = true;
            setTimeout(() => {
                const container = document.getElementById(`enemy-target-${enemy.id}`);
                if (container) container.style.opacity = '0.4';
                updateCombatLog(`${enemy.name} has been defeated!`);
            }, 1000);
        }

        setTimeout(() => {
            clearVisualIndicators();
            checkBattleResult();
        }, 1500);
    }

    function executeSkillOnSelf(skill) {
        consumeResourcesAndApplyCooldowns();
        if (skill && skill.skill_heal > 0) {
            window.combatState.player.hp += parseInt(skill.skill_heal);
            if (window.combatState.player.hp > window.combatState.player.maxHp) window.combatState.player.hp = window.combatState.player.maxHp;

            const pBar = document.getElementById('player-hp-bar');
            if (pBar) pBar.style.width = `${(window.combatState.player.hp / window.combatState.player.maxHp) * 100}%`;

            const pText = document.getElementById('player-hp-text');
            if (pText) pText.innerText = `${window.combatState.player.hp} / ${window.combatState.player.maxHp}`;

            updateCombatLog(`${window.combatState.player.name} casts ${skill.skill_name} and recovers ${skill.skill_heal} HP!`);
        }
        setTimeout(() => {
            clearVisualIndicators();
            checkBattleResult();
        }, 1500);
    }

    function clearVisualIndicators() {
        document.querySelectorAll('.enemy-container').forEach(el => {
            el.classList.remove('enemy-jolt', 'active-player-stage-glow');
        });

        document.querySelectorAll('.enemy-sprite').forEach(el => {
            el.classList.remove('enemy-hurt');
        });

        document.querySelectorAll('.skill-btn').forEach(btn => {
            btn.style.opacity = '1';
            btn.style.boxShadow = 'none';
        });
    }

    function updateSkillButtonsCooldownUI() {
        if (!window.combatState || !window.combatState.skillCooldowns) return;

        Object.keys(window.combatState.skillCooldowns).forEach(skillId => {
            const turnsLeft = window.combatState.skillCooldowns[skillId];
            const btn = document.getElementById(`skill-btn-${skillId}`);
            if (btn) {
                const overlay = btn.querySelector('.cd-overlay');
                if (turnsLeft > 0) {
                    if (overlay) {
                        overlay.innerText = turnsLeft;
                        overlay.classList.replace('d-none', 'd-flex');
                    }
                    btn.disabled = true;
                } else {
                    if (overlay) overlay.classList.replace('d-flex', 'd-none');
                    btn.disabled = false;
                }
            }
        });
    }

    function executeEnemyAutomatedTurn(enemyId) {
        if (window.combatState.battleFinished || window.combatState.player.hp <= 0) return;

        const enemy = window.combatState.enemies.find(e => e.id == enemyId);
        if (!enemy || !enemy.alive) {
            advanceTurnPointer();
            return;
        }

        let dotTotal = 0;
        if (enemy.active_statuses && enemy.active_statuses.length > 0) {
            for (let i = enemy.active_statuses.length - 1; i >= 0; i--) {
                let status = enemy.active_statuses[i];
                let effectData = statusEffectMap[status.status_effect_id];

                if (effectData) {
                    dotTotal += effectData.dotDmg;
                }

                status.duration_left--;
                if (status.duration_left <= 0) {
                    enemy.active_statuses.splice(i, 1);
                }
            }
            renderStatusEffectsUI(enemy);
        }

        if (dotTotal > 0) {
            enemy.hp -= dotTotal;
            updateCombatLog(`${enemy.name} takes ${dotTotal} damage from status effects!`);

            const hpBar = document.getElementById(`enemy-hp-bar-${enemy.id}`);
            if (hpBar) hpBar.style.width = `${(Math.max(0, enemy.hp) / enemy.max_hp) * 100}%`;

            const listHp = document.getElementById(`list-hp-${enemy.id}`);
            if (listHp) listHp.innerText = Math.max(0, enemy.hp);

            const dmgPop = document.getElementById(`enemy-damage-${enemy.id}`);
            if (dmgPop) {
                dmgPop.innerText = `-${dotTotal}`;
                dmgPop.style.color = '#9b59b6';
                dmgPop.classList.remove('d-none');
                setTimeout(() => {
                    dmgPop.classList.add('d-none');
                    dmgPop.style.color = '';
                }, 1200);
            }

            if (enemy.hp <= 0) {
                enemy.hp = 0;
                enemy.alive = false;

                const btn = document.getElementById(`list-target-btn-${enemy.id}`);
                if (btn) btn.disabled = true;

                const container = document.getElementById(`enemy-target-${enemy.id}`);
                if (container) {
                    container.style.opacity = '0.4';
                    const sprite = container.querySelector('.enemy-sprite');
                    if (sprite) sprite.classList.add('enemy-hurt');
                }

                updateCombatLog(`${enemy.name} succumbed to status effects!`);

                setTimeout(() => {
                    clearVisualIndicators();
                    checkBattleResult();
                }, 1500);
                return;
            }

            setTimeout(() => { proceedWithEnemyAttack(enemy); }, 1000);
            return;
        }

        proceedWithEnemyAttack(enemy);
    }

    function proceedWithEnemyAttack(enemy) {
        if (window.combatState.battleFinished || window.combatState.player.hp <= 0) return;

        playSFX('enemyAttack');

        const enemyContainer = document.getElementById(`enemy-target-${enemy.id}`);
        if (enemyContainer) {
            enemyContainer.style.transition = 'transform 0.15s cubic-bezier(0.4, 0, 0.2, 1)';
            enemyContainer.style.transform = 'scale(1.15) translateY(20px)';
            setTimeout(() => {
                enemyContainer.style.transition = 'transform 0.3s ease-out';
                enemyContainer.style.transform = 'scale(1) translateY(0)';
            }, 150);
        }

        let playerDef = parseInt(window.combatState.player.def || 0);
        let enemyStr = parseInt(enemy.str || enemy.atk || 5);
        let playerCurrentHp = parseInt(window.combatState.player.hp || 0);
        let playerMaxHp = parseInt(window.combatState.player.maxHp || 100);

        let effectiveDef = window.combatState.player.isDefending ? (playerDef * 2) : playerDef;
        let enemyDmg = Math.max(1, enemyStr - effectiveDef);

        playerCurrentHp = Math.max(0, playerCurrentHp - enemyDmg);
        window.combatState.player.hp = playerCurrentHp;

        const bodyEl = document.body;
        if (bodyEl) {
            bodyEl.classList.add('shake-trigger');
            setTimeout(() => bodyEl.classList.remove('shake-trigger'), 150);
        }

        const pBar = document.getElementById('player-hp-bar');
        if (pBar) {
            let percentage = (playerCurrentHp / playerMaxHp) * 100;
            pBar.style.width = `${percentage}%`;
            pBar.setAttribute('aria-valuenow', playerCurrentHp);
        }

        const pText = document.getElementById('player-hp-text');
        if (pText) pText.innerText = `${playerCurrentHp} / ${playerMaxHp}`;

        const playerDmgPop = document.getElementById('player-damage-pop');
        if (playerDmgPop) {
            playerDmgPop.innerText = `-${enemyDmg}`;
            playerDmgPop.classList.remove('d-none');
            setTimeout(() => playerDmgPop.classList.add('d-none'), 1000);
        }

        updateCombatLog(`${enemy.name} strikes ${window.combatState.player.name} for ${enemyDmg} damage!`);

        if (typeof updateTurnOrderSidebar === 'function') updateTurnOrderSidebar();

        if (playerCurrentHp <= 0) {
            processBattleEndDefeat();
        } else {
            setTimeout(advanceTurnPointer, 1500);
        }
    }

    function checkBattleResult() {
        const anyEnemiesAlive = window.combatState.enemies.some(e => e.alive);
        if (!anyEnemiesAlive) {
            processBattleEndVictory();
        } else {
            advanceTurnPointer();
        }
    }

    function processBattleEndVictory() {
        window.combatState.battleFinished = true;
        fadeOutBattleMusicAndClose(1000);
        calculateRewardsAndDrops();
    }

    function processBattleEndDefeat() {
        window.combatState.battleFinished = true;
        fadeOutBattleMusicAndClose(1000);
        updateCombatLog("Defeat... You have fallen in battle.");
        if (typeof updateTurnOrderSidebar === 'function') updateTurnOrderSidebar();
    }

    function calculateRewardsAndDrops() {
        updateCombatLog("Victory! Calculating drops...");

        let totalExpGained = 0;
        let generatedDrops = [];
        const enemyCount = window.combatState.enemies.length;

        for (let i = 0; i < enemyCount; i++) {
            totalExpGained += Math.floor(randRange(15, 30));
            if (Math.random() <= 0.65) {
                generatedDrops.push({ item_id: 1, name: "Health Potion" });
            }
        }

        let finalHpRemaining = parseInt(window.combatState.player.hp);

        setTimeout(() => {
            saveRewardsToDatabase(totalExpGained, generatedDrops, finalHpRemaining);
        }, 1500);
    }

    function saveRewardsToDatabase(exp, drops, finalHp) {
        const payload = {
            player_id: window.combatState.player.id,
            exp_gained: exp,
            items_dropped: drops,
            current_hp: finalHp
        };

        fetch('process_rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                let summary = `Gained +${payload.exp_gained} EXP! <br>`;
                if (payload.items_dropped.length > 0) summary += `Looted Items! <br>`;
                if (data.leveled_up) summary += `<span style="color: #d9534f; text-shadow: 1px 1px 0 #fff;">✨ LEVEL UP to ${data.new_level}! ✨</span>`;
                showVictoryRedirect(summary);
            })
            .catch(() => showVictoryRedirect(`Gained +${exp} EXP.`));
    }

    function showVictoryRedirect(summaryText) {
        const overlay = document.createElement('div');
        overlay.className = 'victory-screen-overlay';
        overlay.innerHTML = `
            <div class="victory-panel">
                <h1 class="fw-bold mb-3" style="color: #d9534f; text-shadow: 2px 2px 0px #fff, -1px -1px 0px #fff, 0px 4px 10px rgba(0,0,0,0.4); font-size: 3.5rem; letter-spacing: 4px;">VICTORY!</h1>
                <hr style="border-top: 3px solid #8B5A3C; opacity: 1; margin-bottom: 20px;">
                <p class="fs-4 fw-bold text-dark mb-4" style="line-height: 1.6;">${summaryText}</p>
                <a href="../index.php?p=level1&id=${window.combatState.player.id}"
                   class="btn btn-lg fw-bold w-100 shadow"
                   style="background-color: #8B5A3C; color: white; border: 3px solid #5A3A2A; font-size: 1.5rem; transition: transform 0.2s;"
                   onmouseenter="this.style.transform='scale(1.05)'; playSFX('hover');"
                   onmouseleave="this.style.transform='scale(1)';"
                   onclick="playSFX('select');">
                    Return to Map
                </a>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function randRange(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

    function updateCombatLog(message) {
        const logText = document.getElementById('combat-log-text');
        if (logText) logText.innerText = message;
    }

    function uncageAudioAndStartCombat() {
        playSFX('select');

        if (window.battleAudioInstance) {
            window.battleAudioInstance.play().catch(() => {});
        }

        const preloader = document.getElementById('combat-preloader');
        if (preloader) {
            preloader.classList.add('loaded');
            setTimeout(() => preloader.remove(), 400);
        }
        document.body.classList.add('page-ready');
        setTimeout(runNextTurnSequence, 400);
    }

    function initializeCombatSystems() {
        if (window.combatState && window.combatState.skills) {
            window.combatState.skills.forEach(skill => {
                let skillName = String(skill.skill_name || '').toLowerCase();
                let skillCdMax = parseInt(skill.cooldown || 0);
                if (skillName.includes('ultimate') && skillCdMax > 0) {
                    window.combatState.skillCooldowns[skill.skill_id] = skillCdMax;
                }
            });
        }

        syncManaBarInterfaceDisplay();
        updateSkillButtonsCooldownUI();

        const tooltipEl = document.getElementById('rpg-cursor-tooltip');

        document.querySelectorAll('#btn-attack, #btn-defend, #btn-bag, .skill-btn').forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                if (!btn.disabled && window.combatState.isPlayerTurn) {
                    playSFX('hover');
                }
            });
        });

        // Dynamic mouse tooltip tracker loops for the newly integrated deck panels
        document.querySelectorAll('.skill-btn').forEach(btn => {
            btn.addEventListener('mouseenter', (e) => {
                if (btn.disabled) return;
                const tooltipText = btn.getAttribute('data-tooltip');
                if (tooltipText && tooltipEl) {
                    tooltipEl.innerHTML = tooltipText;
                    tooltipEl.style.display = 'block';
                }
            });

            btn.addEventListener('mousemove', (e) => {
                if (tooltipEl && tooltipEl.style.display === 'block') {
                    tooltipEl.style.left = e.clientX + 'px';
                    tooltipEl.style.top = e.clientY + 'px';
                }
            });

            btn.addEventListener('mouseleave', () => {
                if (tooltipEl) {
                    tooltipEl.style.display = 'none';
                    tooltipEl.innerHTML = '';
                }
            });
            
            btn.addEventListener('click', () => {
                if (tooltipEl) tooltipEl.style.display = 'none';
            });
        });
    }

    initializeCombatSystems();
</script>