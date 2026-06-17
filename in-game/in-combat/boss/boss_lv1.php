<?php
// content_boss_combat.php
// Harmonized matching layout template for ProjectArtifact Boss Stage

// Secure our inherited connection context safely (kept at legacy lookup path)
if (!isset($conn)) {
    include_once __DIR__ . '/../../../conn.php';
}

// Player ID must come from URL parameters
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Boss ID passed from the map navigation context nodelink string 
$boss_id = isset($_GET['boss_id']) ? intval($_GET['boss_id']) : 1;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

$player_data = [];

if ($player_id > 0) {
    $query = "SELECT p.*, c.class_name, c.avatar, ps.curr_hp, ps.curr_max_hp, ps.curr_str, ps.curr_def, ps.curr_dex, ps.curr_int, ps.curr_fth, p.level
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

    if (!isset($max_hp) || $max_hp === null) {
        $max_hp = intval($player_data['curr_max_hp'] ?? 100);
    }
    if (!isset($current_hp) || $current_hp === null) {
        $current_hp = (isset($player_data['curr_hp']) && $player_data['curr_hp'] !== '') ? intval($player_data['curr_hp']) : $max_hp;
    }

    $player_data['str'] = isset($total_str) ? $total_str : intval($player_data['curr_str'] ?? 10);
    $player_data['def'] = isset($total_def) ? $total_def : intval($player_data['curr_def'] ?? 5);
    $player_data['num_dex'] = intval(($player_data['curr_dex'] ?? $player_data['dex']) ?: 10);
    $player_data['dex'] = isset($total_dex) ? $total_dex : $player_data['num_dex'];
    $player_data['curr_dex'] = $player_data['text_dex'] = $player_data['dex'];
    $player_data['int'] = isset($total_int) ? $total_int : intval(($player_data['curr_int'] ?? $player_data['int']) ?: 10);
    $player_data['curr_int'] = $player_data['int'];
    $player_data['fth'] = isset($total_fth) ? $total_fth : intval(($player_data['curr_fth'] ?? $player_data['fth']) ?: 10);
    $player_data['curr_fth'] = $player_data['fth'];

    $row = $player_data;
    $row['name'] = $player_data['name'];
} else {
    if (!isset($max_hp) || $max_hp === null) {
        $max_hp = 100;
    }
    if (!isset($current_hp) || $current_hp === null) {
        $current_hp = 100;
    }

    $player_data = [
        'name' => 'Mimi',
        'avatar' => 'player_avatar.png',
        'curr_max_hp' => $max_hp,
        'curr_hp' => $current_hp,
        'str' => 10,
        'def' => 5,
        'dex' => 10,
        'text_dex' => 10,
        'curr_dex' => 10,
        'int' => 10,
        'curr_int' => 10,
        'fth' => 10,
        'curr_fth' => 10,
        'level' => 1
    ];
    $row = $player_data;
}

// --- SINGLE BOSS ENCOUNTER INJECTOR ---
$active_enemies = [];
$turn_order_stack = [];

$boss_stmt = mysqli_prepare($conn, "SELECT boss_id, boss_name, boss_sprite, boss_max_hp, boss_str, boss_def, boss_dex, boss_int, boss_fth 
                                    FROM boss 
                                    WHERE boss_id = ? LIMIT 1");
mysqli_stmt_bind_param($boss_stmt, "i", $boss_id);
mysqli_stmt_execute($boss_stmt);
$boss_res = mysqli_stmt_get_result($boss_stmt);
$boss_row = mysqli_fetch_assoc($boss_res);
mysqli_stmt_close($boss_stmt);

if (!$boss_row) {
    die("Error: Selected boss context config profile #$boss_id not found in table databases.");
}

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

$bossDex = intval($boss_row['boss_dex'] ?? 8);
$enemy_instance = [
    'id'         => 0, // Singular target tracking element index node 0
    'enemy_id'   => $boss_row['boss_id'],
    'name'       => $boss_row['boss_name'],
    'enemy_name' => $boss_row['boss_name'],
    'boss_sprite' => $boss_row['boss_sprite'],
    'hp'         => intval($boss_row['boss_max_hp']),
    'max_hp'     => intval($boss_row['boss_max_hp']),
    'str'        => intval($boss_row['boss_str']),
    'def'        => intval($boss_row['boss_def']),
    'dex'        => $bossDex,
    'curr_dex'   => $bossDex,
    'int'        => intval($boss_row['boss_int'] ?? 0),
    'fth'        => intval($boss_row['boss_fth'] ?? 0),
    'alive'      => true
];
$active_enemies[] = $enemy_instance;

$turn_order_stack[] = [
    'id'            => 0,
    'name'          => $enemy_instance['name'],
    'type'          => 'boss',
    'boss_sprite'   => $enemy_instance['boss_sprite'],
    'text_dex'      => $bossDex,
    'dex'           => $enemy_instance['dex'],
    'curr_dex'      => $enemy_instance['curr_dex']
];

usort($turn_order_stack, function ($a, $b) {
    return $b['dex'] <=> $a['dex'];
});

$enemies     = $active_enemies;
$enemy_party = $active_enemies;
$combatants  = $turn_order_stack;

// Load Player Bag Items Framework
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

// Load Player Obtained Class Abilities Framework
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
        0% {
            box-shadow: 0 0 0 0 rgba(255, 157, 65, 0.7);
        }

        70% {
            box-shadow: 0 0 0 15px rgba(255, 157, 65, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(255, 157, 65, 0);
        }
    }

    @keyframes slideDown {
        from {
            transform: translateY(-100%);
        }

        to {
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(0);
        }

        to {
            transform: translateY(-100%);
        }
    }

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
       ⚔️ BATTLE ANIMATIONS, FX & CUT-INS
       ========================================================================== */
    @keyframes battleFloat {
        0% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-10px);
        }

        100% {
            transform: translateY(0px);
        }
    }

    @keyframes snappyJolt {
        0% {
            transform: translate(0, 0);
        }

        15% {
            transform: translate(-4px, 2px);
        }

        30% {
            transform: translate(4px, -2px);
        }

        45% {
            transform: translate(-3px, -1px);
        }

        60% {
            transform: translate(3px, 2px);
        }

        75% {
            transform: translate(-1px, -1px);
        }

        90% {
            transform: translate(1px, 1px);
        }

        100% {
            transform: translate(0, 0);
        }
    }

    @keyframes cutInSlice {
        0% {
            transform: translate(-50%, -50%) scaleX(0);
            opacity: 0;
        }

        15% {
            transform: translate(-50%, -50%) scaleX(1.1);
            opacity: 1;
        }

        20% {
            transform: translate(-50%, -50%) scaleX(1);
        }

        80% {
            transform: translate(-50%, -50%) scaleX(1);
            opacity: 1;
            filter: brightness(1);
        }

        95% {
            transform: translate(-50%, -50%) scaleX(1.05);
            opacity: 0.5;
        }

        100% {
            transform: translate(-50%, -50%) scaleX(0);
            opacity: 0;
            filter: brightness(2);
        }
    }

    @keyframes eyeGlance {
        0% {
            transform: scale(1.3) translate(-10px, 0);
            filter: saturate(0.5);
        }

        20% {
            transform: scale(1.1) translate(0, 0);
            filter: saturate(1.2);
        }

        80% {
            transform: scale(1.1) translate(5px, 0);
        }

        100% {
            transform: scale(1.4) translate(20px, 0);
        }
    }

    .enemy-jolt,
    .shake-trigger {
        animation: snappyJolt 0.15s ease-in-out both;
    }

    .enemy-hover-float {
        animation: battleFloat 3.2s ease-in-out infinite;
    }

    .enemy-sprite {
        transition: filter 0.15s ease-in-out;
    }

    .enemy-hurt {
        filter: brightness(0.6) sepia(1) hue-rotate(-50deg) saturate(6) opacity(0.6) !important;
    }

    .turn-card {
        border-radius: 6px;
        border: 2px solid #5A3A2A;
        transition: all 0.25s ease;
    }

    .turn-card.active-unit-highlight {
        border-color: #ff9d41 !important;
        box-shadow: 0 0 10px rgba(255, 157, 65, 0.6);
        transform: scale(1.02);
    }

    .active-player-stage-glow {
        outline: 4px solid #ff9d41 !important;
        outline-offset: 2px;
        box-shadow: 0 0 15px 4px rgba(255, 157, 65, 0.6) !important;
        border-radius: 4px;
    }

    .active-turn-scale {
        transform: scale(1.04) !important;
        z-index: 10;
    }

    /* --- 🎭 PERSONA STYLE CUTIN SYSTEM --- */
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

    .persona-cutin-overlay.active {
        opacity: 1;
    }

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

    #btn-attack,
    .skill-btn {
        margin: 4px 0;
        transition: transform 0.15s cubic-bezier(0.25, 1, 0.5, 1), background-color 0.15s ease, box-shadow 0.15s ease;
    }

    #btn-attack:hover:not(:disabled),
    .skill-btn:hover:not(:disabled) {
        transform: scale(1.03);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), 0 0 8px rgba(255, 157, 65, 0.4);
    }

    #btn-attack:active:not(:disabled),
    .skill-btn:active:not(:disabled) {
        transform: scale(0.98);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    /* ==========================================================================
       🔮 RPG-THEMED DYNAMIC MOUSE CURSOR TOOLTIP
       ========================================================================== */
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
    <h3 class="text-white mb-2 fw-bold" style="letter-spacing: 2px; text-shadow: 2px 2px 0px #000;">BOSS ENCOUNTER IMMINENT</h3>
    <button class="preloader-ready-btn" onclick="uncageAudioAndStartCombat()">READY</button>
</div>

<div class="persona-cutin-overlay" id="cutin-dimmer"></div>
<div class="persona-banner-line" id="ultimate-cutin-line">
    <img src="../../../asset/sprites/classes/<?= htmlspecialchars($player_data['avatar'] ?? 'player_avatar.png') ?>" class="persona-eyes-img" id="cutin-eyes-asset" alt="Eyes View">
</div>

<div id="rpg-cursor-tooltip"></div>

<main>
    <div class="d-flex flex-column justify-content-between h-100 p-3" style="user-select: none;">
        <div class="damage-popup text-danger fw-bold position-absolute d-none fs-2" id="player-damage-pop" style="z-index: 99; top: 25%; left: 15%; text-shadow: 2px 2px 0px #000;"> -0 </div>

        <!-- FIELD REFACTOR: Large healthbar is now structurally placed directly inside the field's top confines -->
        <!-- FIELD REFACTOR: Large healthbar is now structurally placed directly inside the field's top confines -->
        <div class="battle-stage d-flex flex-column align-items-center justify-content-start mb-3 p-3 position-relative"
            style="height: 480px; min-height: 480px; background-image: radial-gradient(circle, #b4694000 60%, #b4694065 90%), url('../../../asset/img/background/plains1.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-color:#FAC79B; border-radius: 12px; border: 4px solid #8B5A3C;">

            <!-- IMMERSIVE INSIDE-FIELD STATIC BOSS HP HUD PANEL WITH INTEGRATED STATUS EFFECTS -->
            <?php if (!empty($active_enemies)): 
                $topEnemy = $active_enemies[0]; 
            ?>
            <div id="boss-field-hud" class="w-100 p-2 rounded mb-auto transition-all" style="max-width: 600px; z-index: 10;">
                
                <div class="d-flex flex-column align-items-center mb-1 w-100">
                    <span class="fw-bold text-white uppercase tracking-wider mb-1" style="font-size: 0.95rem; text-shadow: 1px 1px 2px #000;">
                        💀 <?= htmlspecialchars($topEnemy['name']) ?>
                    </span>
                    <span id="global-boss-hp-text" class="fw-bold text-warning mb-1" style="font-size: 0.85rem; text-shadow: 1px 1px 2px #000;">
                        <?= intval($topEnemy['hp']) ?> / <?= intval($topEnemy['max_hp']) ?>
                    </span>
                </div>

                <div class="progress w-100 mb-2" style="height: 14px; background-color: rgba(0,0,0,0.5); border-radius: 4px; border: 1px solid #4A1A00;">
                    <div id="global-boss-hp-bar"
                        class="progress-bar bg-danger progress-bar-striped animated"
                        role="progressbar"
                        style="width: <?= ($topEnemy['hp'] / $topEnemy['max_hp']) * 100 ?>%; transition: width 0.3s ease;"
                        aria-valuenow="<?= $topEnemy['hp'] ?>"
                        aria-valuemin="0"
                        aria-valuemax="<?= $topEnemy['max_hp'] ?>"></div>
                </div>
                <div id="enemy-statuses-<?= $topEnemy['id'] ?>" class="d-flex gap-2 justify-content-center flex-wrap" style="min-height: 24px; width:100%;"></div>
            </div>
            <?php endif; ?>

            <!-- FIELD ACTORS ROW CONTAINER -->
            <div class="enemy-party d-flex gap-4 align-items-end justify-content-center w-100 mt-auto" style="height: 320px;">
                <?php if (!empty($active_enemies)): ?>
                    <?php foreach ($active_enemies as $index => $enemy):
                        $enemyUniqueId = $enemy['id'];
                    ?>
                        <div class="enemy-container d-flex flex-column align-items-center justify-content-end text-center position-relative"
                            id="enemy-target-<?= $enemyUniqueId ?>"
                            style="min-width: 180px; height: 100%; transition: opacity 0.2s ease, outline 0.2s ease; <?= !$enemy['alive'] ? 'opacity: 0.4;' : '' ?>">

                            <div class="damage-popup text-warning fw-bold position-absolute top-0 start-50 translate-middle fs-2 d-none" id="enemy-damage-<?= $enemyUniqueId ?>" style="z-index: 99; text-shadow: 2px 2px 0px #000;">-0</div>

                            <?php if (!empty($enemy['boss_sprite'])): ?>
                                <div class="d-flex align-items-end justify-content-center w-100 h-100" style="max-height: 260px; overflow: visible;">
                                    <img src="../../../asset/sprites/bosses/<?= htmlspecialchars($enemy['boss_sprite']) ?>"
                                        class="enemy-sprite enemy-hover-float item-rendering-pixelated"
                                        style="max-height: 100%; max-width: 100%; object-fit: contain; filter: drop-shadow(0px 8px 4px rgba(0,0,0,0.25)); transition: transform 0.2s ease;"
                                        alt="<?= htmlspecialchars($enemy['name']) ?>"
                                        onerror="this.src='https://placehold.co/180x220?text=Boss+Encounter'">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-2 pb-2">
            <div class="col-9">
                <div class="col-12 p-2 rounded mb-2 d-flex align-items-center gap-3" style="background-color: #FAC79B; border: 2px solid #8B5A3C;">
                    <div class="fw-bold text-dark style-font" style="font-size: 0.85rem; white-space: nowrap;">
                        🔮 MANA: <span id="player-mana-display">10</span> / 10
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

                <div class="p-3 d-flex flex-column gap-2 justify-content-center" id="hud-interaction-deck" style="background-color: #FAC79B; min-height: 100px; height: 180px; border-radius: 12px; border: 2px solid #8B5A3C; overflow-y: auto; transition: outline 0.2s ease;">
                    <div class="skills-panel" id="skills-panel" style="display: block;">
                        <?php if (!empty($player_skills)): ?>
                            <div class="row g-2">
                                <?php foreach ($player_skills as $skill): ?>
                                    <div class="col-6">
                                        <button type="button" class="btn w-100 skill-btn fw-bold py-2 position-relative d-flex flex-column align-items-center justify-content-center"
                                            id="skill-btn-<?= htmlspecialchars($skill['skill_id']) ?>"
                                            style="background-color: #8B5A3C; color: white; border: 2px solid #5A3A2A; border-radius: 8px;"
                                            onclick="triggerSkillSelection(<?= intval($skill['skill_id']) ?>, this)"
                                            data-skill-id="<?= htmlspecialchars($skill['skill_id']) ?>"
                                            data-tooltip="📝 <?= htmlspecialchars($skill['skill_desc'] ?? 'No extra data descriptions assigned to this index artifact.') ?>">
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
                            <div class="text-center text-muted py-2"><small>No actions learned yet</small></div>
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
    // ==========================================================================
    // 🌐 BATTLE STATE MANAGEMENT ENGINE & LIFECYCLE MATRIX
    // ==========================================================================
    if (typeof window.combatState === 'undefined') {
        window.combatState = {
            player: {
                id: parseInt(<?= json_encode($player_id); ?>),
                name: <?= json_encode($player_data['name']); ?>,
                hp: parseInt(<?= json_encode($current_hp); ?>) || 100,
                maxHp: parseInt(<?= json_encode($max_hp); ?>) || 100,
                atk: parseInt(<?= json_encode($player_data['str']); ?>) || 10,
                def: parseInt(<?= json_encode($player_data['def']); ?>) || 5,
                text_dex: parseInt(<?= json_encode($player_dex); ?>) || 10,
                dex: parseInt(<?= json_encode($player_dex); ?>) || 10,
                int: parseInt(<?= json_encode($player_data['int'] ?? 10); ?>) || 10,
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
        window.combatState.player.mana = 10;
        window.combatState.player.isDefending = false;
        window.combatState.enemies = <?= json_encode($active_enemies); ?>;
        window.combatState.turnOrder = <?= json_encode($turn_order_stack); ?>;
        window.combatState.skillCooldowns = {};
        window.combatState.selectedSkill = null;
        window.combatState.currentTurnIndex = 0;
        window.combatState.isPlayerTurn = false;
        window.combatState.battleFinished = false;
    }

    if (typeof window.battleAudioInstance === 'undefined') {
        window.battleAudioInstance = new Audio('../../../asset/bgm/battle_bgm.mp3');
        window.battleAudioInstance.loop = true;
        window.battleAudioInstance.volume = 0.10;
    }

    if (typeof window.sfx === 'undefined') {
        window.sfx = {
            hover: new Audio('../../../asset/sfx/hover.wav'),
            select: new Audio('../../../asset/sfx/select.wav'),
            cancel: new Audio('../../../asset/sfx/cancel.wav'),
            ultimate: new Audio('../../../asset/sfx/ultimate.wav'),
            ultimateUnleash: new Audio('../../../asset/sfx/ultimateunleash.wav'),
            enemyAttack: new Audio('../../../asset/sfx/enemyattack.wav'),
            playerAttack: new Audio('../../../asset/sfx/playerattack.wav')
        };
        window.sfx.hover.volume = 0.20;
        window.sfx.select.volume = 0.15;
        window.sfx.cancel.volume = 0.35;
        window.sfx.ultimate.volume = 0.60;
        window.sfx.ultimateUnleash.volume = 0.55;
        window.sfx.enemyAttack.volume = 0.65;
        window.sfx.playerAttack.volume = 0.55;
    }

    function playSFX(soundName) {
        if (window.sfx && window.sfx[soundName]) {
            window.sfx[soundName].currentTime = 0;
            window.sfx[soundName].play().catch(err => {
                console.log("Audio limitations pending interaction block", err);
            });
        }
    }

    function tryUncageBattleMusic() {
        if (window.battleAudioInstance && window.battleAudioInstance.paused && !window.combatState.battleFinished) {
            window.battleAudioInstance.play().catch(err => {
                console.log("Audio Matrix block pending click actions...", err);
            });
        }
    }

    function syncBossHealthUIElements(enemy) {
        const hpBar = document.getElementById('global-boss-hp-bar');
        const hpText = document.getElementById('global-boss-hp-text');
        if (hpBar && enemy) {
            const percentage = (enemy.hp / enemy.max_hp) * 100;
            hpBar.style.width = percentage + '%';
            hpBar.setAttribute('aria-valuenow', enemy.hp);
        }
        if (hpText && enemy) {
            hpText.innerText = `${enemy.hp} / ${enemy.max_hp}`;
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

    const statusEffectMap = {
        1: {
            name: 'Poison',
            icon: 'poison.png',
            dotDmg: 5
        },
        2: {
            name: 'Burn',
            icon: 'burn.png',
            dotDmg: 8
        },
        3: {
            name: 'Bleed',
            icon: 'bleed.png',
            dotDmg: 10
        },
        4: {
            name: 'Freezerburn',
            icon: 'freezerburn.png',
            dotDmg: 12
        }
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
                        <img src="../../../asset/status_effects/${effectData.icon}" style="width: 20px; height: 20px; filter: drop-shadow(0 2px 2px #000);">
                        <span class="position-absolute bg-dark badge rounded-pill" style="font-size: 0.52rem; padding: 0.15em 0.3em; border: 1px solid #fff; bottom: -4px; right: -4px;">
                            ${status.duration_left}
                        </span>
                    </div>
                `;
            }
        });
    }

    // ==========================================================================
    // 👑 CONTEXT-AWARE TURN ORDER SIDEBAR PIPELINE
    // ==========================================================================
    function updateTurnOrderSidebar() {
        const listContainer = document.getElementById('turn-sidebar-list') || document.getElementById('turn-order-list');
        if (!listContainer) return;

        let htmlContent = '';
        const activeUnit = window.combatState.activeUnit;

        window.combatState.turnOrder.forEach(combatant => {
            let isAlive = true;
            let currentHp = 1;
            let maxHp = 1;

            if (combatant.type === 'boss') {
                const enemyUnit = window.combatState.enemies.find(e => e.id === combatant.id);
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

            const isActive = (activeUnit && combatant.type === activeUnit.type && combatant.id === activeUnit.id);
            const bgColor = combatant.type === 'player' ? '#8B5A3C' : '#721C24';

            let spriteFolder = 'enemies/lv1/';
            if (combatant.type === 'player') {
                spriteFolder = 'classes/';
            } else if (combatant.type === 'boss') {
                spriteFolder = 'bosses/';
            }
            const currentImg = combatant.type === 'boss' ? (combatant.boss_sprite ?? combatant.sprite) : combatant.sprite;

            htmlContent += `
                <div class="turn-card p-2 d-flex align-items-center gap-2 ${isActive ? 'active-unit-highlight' : ''}" 
                     style="background-color: ${bgColor}; color: white;">
                    <div style="width: 35px; height: 35px; border-radius: 4px; overflow: hidden; background-color: rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.25);">
                        <img src="../../../asset/sprites/${spriteFolder}${currentImg}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.src='https://placehold.co/35x35?text=?';">
                    </div>
                    <div class="flex-grow-1" style="line-height: 1.1;">
                        <div class="fw-bold" style="font-size: 0.85rem;">${combatant.name} ${isActive ? '👑' : ''}</div>
                        <small style="font-size: 0.72rem; opacity: 0.85;">HP: ${currentHp}/${maxHp} | ⚡ DEX: ${combatant.dex}</small>
                    </div>
                </div>
            `;
        });

        listContainer.innerHTML = htmlContent || '<div class="text-center text-muted py-2"><small>No actions left</small></div>';

        let liveHp = window.combatState.player.hp;
        let liveMaxHp = window.combatState.player.maxHp;
        let percentage = (liveMaxHp > 0) ? (liveHp / liveMaxHp) * 100 : 0;

        $('#player-hp-bar, .modal-hp-bar-sync').css('width', percentage + '%').attr('aria-valuenow', liveHp);
        $('#player-hp-text, .modal-hp-text-sync').text(liveHp + ' / ' + liveMaxHp);
    }

    // ==========================================================================
    // 🧭 ENCOUNTER TURN TIMELINE ENGINE
    // ==========================================================================
    function runNextTurnSequence() {
        if (window.combatState.battleFinished) return;

        const livingEnemies = window.combatState.enemies.filter(e => e.alive);
        if (livingEnemies.length === 0) {
            processBattleEndVictory();
            return;
        }
        if (window.combatState.player.hp <= 0) {
            processBattleEndDefeat();
            return;
        }

        let index = window.combatState.currentTurnIndex;
        let combatant = window.combatState.turnOrder[index];

        if (combatant.type === 'boss') {
            const enemyUnit = window.combatState.enemies.find(e => e.id === combatant.id);
            if (!enemyUnit || !enemyUnit.alive) {
                advanceTurnPointer();
                return;
            }
        }

        window.combatState.activeUnit = combatant;
        if (typeof updateTurnOrderSidebar === 'function') updateTurnOrderSidebar();
        manageVisualStageHighlights(combatant);

        if (combatant.type === 'player') {
            window.combatState.isPlayerTurn = true;
            toggleHUDControls(true);
            updateCombatLog(`${window.combatState.player.name}'s turn! Choose an action to challenge the boss.`);
        } else {
            window.combatState.isPlayerTurn = false;
            toggleHUDControls(false);

            document.getElementById('targets-panel').style.display = 'none';
            document.getElementById('skills-panel').style.display = 'block';

            setTimeout(() => {
                executeEnemyAutomatedTurn(combatant.id);
            }, 1200);
        }
    }

    function manageVisualStageHighlights(activeUnit) {
        document.querySelectorAll('.enemy-container').forEach(el => el.classList.remove('active-player-stage-glow', 'active-turn-scale'));
        const deck = document.getElementById('hud-interaction-deck');
        const bossHud = document.getElementById('boss-field-hud');

        if (deck) deck.classList.remove('active-player-stage-glow');
        if (bossHud) {
            bossHud.style.outline = 'none';
        }

        if (activeUnit.type === 'boss') {
            const targetEl = document.getElementById(`enemy-target-${activeUnit.id}`);
            if (targetEl) targetEl.classList.add('active-turn-scale');
            if (bossHud) {
                bossHud.style.outline = '3px solid #ff9d41';
                bossHud.style.boxShadow = '0 0 15px rgba(255, 157, 65, 0.6)';

            }
        } else {
            if (deck) deck.classList.add('active-player-stage-glow');
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

        let contentHtml = '';
        window.combatState.enemies.forEach(enemy => {
            contentHtml += `
                <button type="button" class="btn flex-grow-1 enemy-target-btn fw-bold py-2"
                    id="list-target-btn-${enemy.id}"
                    style="background-color: #B46940; color: white; border: 2px solid #8B4513; border-radius: 8px; text-align: center; font-size: 0.85rem;"
                    ${!enemy.alive ? 'disabled' : ''}
                    onmouseenter="if(!this.disabled) playSFX('hover')"
                    onclick="selectEnemyTarget(${parseInt(enemy.id)})">
                    🎯 ${enemy.name} <small style="opacity: 0.85;">(HP: <span id="list-hp-${enemy.id}">${parseInt(enemy.hp)}</span>/${parseInt(enemy.max_hp)})</small>
                </button>
            `;
        });
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

        const targetEnemy = window.combatState.enemies.find(e => e.id === enemyId);
        if (!targetEnemy || !targetEnemy.alive) return;

        window.combatState.isPlayerTurn = false;
        toggleHUDControls(false);

        let skillName = String(window.combatState.selectedSkill?.skill_name || '').toLowerCase();
        const area = String(window.combatState.selectedSkill.skill_area || 'enemy').toLowerCase();

        if (skillName.includes('ultimate')) {
            triggerPersonaCutInSequence(() => {
                playSFX('playerAttack');
                if (area === 'all' || area === 'enemy_all' || area === 'aoe') {
                    executeAoESkill(window.combatState.selectedSkill);
                } else {
                    executeSkillOnTarget(window.combatState.selectedSkill, targetEnemy);
                }
            });
        } else {
            playSFX('playerAttack');
            if (area === 'all' || area === 'enemy_all' || area === 'aoe') {
                executeAoESkill(window.combatState.selectedSkill);
            } else {
                executeSkillOnTarget(window.combatState.selectedSkill, targetEnemy);
            }
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
            updateCombatLog(`🔮 Not enough mana! Requires ${skill.mana_cost} Mana (Current: ${window.combatState.player.mana}).`);
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

        const area = String(skill.skill_area || 'enemy').toLowerCase();

        if (area === 'self') {
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
            document.getElementById('target-instructions-label').innerText = `Select target for [${skill.skill_name}]:`;
            document.getElementById('skills-panel').style.display = 'none';
            document.getElementById('targets-panel').style.display = 'block';
            updateCombatLog(`${skill.skill_name} selected. Confirm your target from the active layout listing below.`);
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
        document.getElementById('player-mana-display').innerText = manaVal;
        const manaBar = document.getElementById('player-mana-bar');
        if (manaBar) {
            const percentage = Math.min(100, Math.max(0, (manaVal / 10) * 100));
            manaBar.style.width = `${percentage}%`;
            manaBar.setAttribute('aria-valuenow', manaVal);
        }
    }

    function executeAoESkill(skill) {
        updateCombatLog(`${window.combatState.player.name} unleashes ${skill.skill_name} across the boss stage!`);
        consumeResourcesAndApplyCooldowns();

        let skillName = String(skill?.skill_name || '').toLowerCase();
        if (skillName.includes('ultimate')) {
            playSFX('ultimateUnleash');
        }

        window.combatState.enemies.forEach(enemy => {
            if (!enemy.alive) return;

            const basePower = parseInt(skill.skill_atk) || parseInt(skill.skill_str) || window.combatState.player.atk;
            const skillDamage = Math.max(1, basePower - enemy.def);

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

            syncBossHealthUIElements(enemy);

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
                }, 1000);
            }
        });

        clearVisualIndicators();
        setTimeout(checkBattleResult, 1500);
    }

    function executeSkillOnTarget(skill, enemy) {
        consumeResourcesAndApplyCooldowns();

        let skillName = String(skill?.skill_name || '').toLowerCase();
        if (skillName.includes('ultimate')) {
            playSFX('ultimateUnleash');
        }

        const basePower = parseInt(skill.skill_atk) || parseInt(skill.skill_str) || window.combatState.player.atk;
        const skillDamage = Math.max(1, basePower - enemy.def);

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
            updateCombatLog(`${window.combatState.player.name} uses ${skill.skill_name} on ${enemy.name} for ${skillDamage} damage!`);
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

        syncBossHealthUIElements(enemy);

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

        clearVisualIndicators();
        setTimeout(checkBattleResult, 1500);
    }

    function executeSkillOnSelf(skill) {
        consumeResourcesAndApplyCooldowns();
        if (skill.skill_heal > 0) {
            window.combatState.player.hp += parseInt(skill.skill_heal);
            if (window.combatState.player.hp > window.combatState.player.maxHp) window.combatState.player.hp = window.combatState.player.maxHp;
            updateCombatLog(`${window.combatState.player.name} casts ${skill.skill_name} and recovers ${skill.skill_heal} HP!`);
        }
        clearVisualIndicators();
        setTimeout(checkBattleResult, 1500);
    }

    function clearVisualIndicators() {
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

        updateSkillButtonsCooldownUI();
        if (typeof updateTurnOrderSidebar === 'function') updateTurnOrderSidebar();
    }

    function updateSkillButtonsCooldownUI() {
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

        const enemy = window.combatState.enemies.find(e => e.id === enemyId);
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

            syncBossHealthUIElements(enemy);

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
                    checkBattleResult();
                }, 1500);
                return;
            }
            setTimeout(() => {
                proceedWithEnemyAttack(enemy);
            }, 1000);
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
            enemyContainer.style.transform = 'scale(1.03) translateY(10px)';
            setTimeout(() => {
                enemyContainer.style.transition = 'transform 0.3s ease-out';
                enemyContainer.style.transform = 'scale(1) translateY(0)';
            }, 150);
        }

        let playerDef = parseInt(window.combatState.player.def || 0);
        let enemyStr = parseInt(enemy.str || 0);
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

        updateCombatLog(`💥 ${enemy.name} strikes ${window.combatState.player.name} for ${enemyDmg} damage!`);
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
        updateCombatLog("Defeat... You have fallen before the boss.");
        if (typeof updateTurnOrderSidebar === 'function') updateTurnOrderSidebar();
    }

    function calculateRewardsAndDrops() {
        updateCombatLog("Boss Defeated! Calculating epic drops...");
        let totalExpGained = Math.floor(randRange(120, 250));
        let generatedDrops = [];

        if (Math.random() <= 1.00) {
            generatedDrops.push({
                item_id: 99,
                name: "Ancient Shattered Artifact"
            });
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
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                let summary = `Gained +${payload.exp_gained} EXP! `;
                if (payload.items_dropped.length > 0) summary += `Looted ${payload.items_dropped[0].name}!`;
                if (data.leveled_up) summary += ` ✨ LEVEL UP to ${data.new_level}!`;
                showVictoryRedirect(summary);
            })
            .catch(() => showVictoryRedirect(`Victory! Gained +${exp} Boss Encounter EXP.`));
    }

    function showVictoryRedirect(summaryText) {
        const dialogBox = document.getElementById('combat-dialogue-box');
        if (dialogBox) {
            dialogBox.style.maxHeight = "none";
            dialogBox.style.height = "auto";
            dialogBox.innerHTML = `
        <div class="d-flex justify-content-between align-items-center w-100 py-1">
            <p class="m-0 fw-bold text-white">${summaryText}</p>
            <a href="../index.php?p=level1&id=${window.combatState.player.id}" class="btn btn-sm text-dark fw-bold" style="background-color: #FAC79B; border: 2px solid #5A3A2A;">Complete Quest</a>
        </div>`;
        } else {
            window.location.href = `../index.php?p=level1&id=${window.combatState.player.id}`;
        }
    }

    function randRange(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function updateCombatLog(message) {
        const logText = document.getElementById('combat-log-text');
        if (logText) logText.innerText = message;
    }

    function uncageAudioAndStartCombat() {
        playSFX('select');
        tryUncageBattleMusic();

        const preloader = document.getElementById('combat-preloader');
        if (preloader) {
            preloader.classList.add('loaded');
            setTimeout(() => preloader.remove(), 400);
        }
        document.body.classList.add('page-ready');
        setTimeout(runNextTurnSequence, 400);
    }

    // ==========================================================================
    // ⚙️ INITIALIZATION SYSTEM CARD DESK LISTENERS
    // ==========================================================================
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