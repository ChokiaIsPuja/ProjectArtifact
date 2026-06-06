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
    $player_data['dex'] = intval(($player_data['curr_dex'] ?? $player_data['dex']) ?: 10);
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

    $skill_query = "SELECT ob.skill_id, ob.skill_name, ob.skill_desc, ob.lvl_required, ob.cooldown, ob.skill_area,
                           sa.skill_str, sa.skill_def, sa.skill_heal, sa.skill_int
                    FROM obtainable_skill ob
                    LEFT JOIN skill_attributes sa ON ob.id_skill_attributes = sa.id_skill_attributes
                    WHERE ob.class_id = ? AND ob.lvl_required <= ?
                    ORDER BY ob.skill_id ASC";

    $stmt_skills = mysqli_prepare($conn, $skill_query);
    if ($stmt_skills) {
        mysqli_stmt_bind_param($stmt_skills, "ii", $player_class, $player_level);
        mysqli_stmt_execute($stmt_skills);
        $skill_result = mysqli_stmt_get_result($stmt_skills);
        while ($skill_row = mysqli_fetch_assoc($skill_result)) {
            $skill_row['mana_cost'] = ($skill_row['skill_str'] > 20 || $skill_row['skill_heal'] > 20) ? 2 : 1;
            $player_skills[] = $skill_row;
        }
        mysqli_stmt_close($stmt_skills);
    }
}
?>

<style>
    @keyframes battleFloat {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0px); }
    }
    .enemy-hover-float {
        animation: battleFloat 3.2s ease-in-out infinite;
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
    /* Smooth visual transitions for UI elements */
    #btn-attack, .skill-btn {
        transition: all 0.15s ease;
    }
</style>

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
                        onclick="selectEnemyTarget(<?= $enemyUniqueId ?>)"
                        style="cursor: pointer; min-width: 120px; transition: all 0.2s ease; <?= !$enemy['alive'] ? 'opacity: 0.4;' : '' ?>">

                        <div class="damage-popup text-warning fw-bold position-absolute top-0 start-50 translate-middle fs-2 d-none" id="enemy-damage-<?= $enemyUniqueId ?>" style="z-index: 99; text-shadow: 2px 2px 0px #000;">-0</div>

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
                                    style="max-height: 100%; max-width: 100%; object-fit: contain; filter: drop-shadow(0px 8px 4px rgba(0,0,0,0.2)); transition: transform 0.2s ease; animation-delay: <?= $index * 0.45 ?>s;"
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
                        style="width: 10%; transition: width 0.4s ease;"
                        aria-valuenow="1"
                        aria-valuemin="0"
                        aria-valuemax="10"></div>
                </div>
            </div>
            
            <div class="p-3 d-flex flex-column gap-2 justify-content-center" style="background-color: #FAC79B; min-height: 100px; height: 180px; border-radius: 12px; border: 2px solid #8B5A3C; overflow-y: auto;">
                <div id="skills-panel">
                    <?php if (!empty($player_skills)): ?>
                        <div class="row g-2">
                            <?php foreach ($player_skills as $skill): ?>
                                <div class="col-6">
                                    <button class="btn w-100 skill-btn fw-bold py-2 position-relative d-flex flex-column align-items-center justify-content-center"
                                        id="skill-btn-<?= htmlspecialchars($skill['skill_id']) ?>"
                                        style="background-color: #8B5A3C; color: white; border: 2px solid #5A3A2A; border-radius: 8px;"
                                        onclick="executePlayerSkill(<?= htmlspecialchars(json_encode($skill)) ?>, event)"
                                        data-skill-id="<?= htmlspecialchars($skill['skill_id']) ?>"
                                        data-skill-name="<?= htmlspecialchars($skill['skill_name']) ?>"
                                        title="<?= htmlspecialchars($skill['skill_desc']) ?>">
                                        <div><?= htmlspecialchars($skill['skill_name']) ?></div>
                                        <small class="text-warning" style="font-size:0.75rem;">🔮 Cost: <?= $skill['mana_cost'] ?> | ⏳ CD: <?= $skill['cooldown'] ?>t</small>
                                        <div class="position-absolute top-50 start-50 translate-middle w-100 h-100 d-none align-items-center justify-content-center rounded bg-dark bg-opacity-75 cd-overlay" style="z-index: 5; color: #fff; font-size: 1.2rem;">0</div>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-2">
                            <small>No skills learned yet</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="targets-panel" style="display: none;">
                    <div class="d-flex flex-column gap-1">
                        <div class="text-dark fw-bold mb-1" style="font-size: 0.9rem;">Select Encounter Target:</div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach ($active_enemies as $enemy): ?>
                                <button class="btn flex-grow-1 enemy-target-btn fw-bold py-2"
                                    id="list-target-btn-<?= $enemy['id'] ?>"
                                    style="background-color: #B46940; color: white; border: 2px solid #8B4513; border-radius: 8px; text-align: left; font-size: 0.85rem;"
                                    onclick="selectEnemyTarget(<?= intval($enemy['id'] ?? 0) ?>)">
                                    🎯 <?= htmlspecialchars($enemy['name']) ?> <small style="opacity: 0.85;">(HP: <span id="list-hp-<?= $enemy['id'] ?>"><?= intval($enemy['hp'] ?? 0) ?></span>/<?= intval($enemy['max_hp'] ?? 0) ?>)</small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm fw-bold py-1 mt-2 mx-auto text-white" style="background-color: #555; border: none; border-radius: 6px; width: 100px;" onclick="cancelSkillSelection()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-3">
            <div class="p-2 d-flex flex-column gap-2 justify-content-center h-100" style="background-color: #FAC79B; border-radius: 12px; border: 2px solid #8B5A3C; min-height: 180px;">
                <button type="button" id="btn-attack" onclick="executePlayerAction('attack')" class="btn btn-lg text-white w-100 py-2 fw-bold combat-btn shadow-sm" style="background-color: #B46940; border-radius: 8px; border:3px #ff9d41 solid; height:80px; text-shadow: 1px 1px 2px #000;">Attack!</button>
                <div class="row g-2">
                    <div class="col-6 mt-2">
                        <button class="btn btn-dark w-100 fw-bold shadow-sm" id="btn-bag" style="background-color: #8B5A3C; border: 2px solid #5A3A2A; color:#fff; height:60px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalInventory">Bag</button>
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
                <p class="m-0" id="combat-log-text">Mimi's turn! Choose an action to strike down your foes.</p>
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================================================
    // 🌐 BATTLE STATE MANAGEMENT ENGINE & LIFECYCLE OBJECT MATRIX
    // ==========================================================================
    if (typeof window.combatState === 'undefined') {
        window.combatState = {
            player: {
                id: <?= json_encode($player_id); ?>,
                name: <?= json_encode($player_data['name']); ?>,
                hp: <?= json_encode(intval($player_data['curr_hp'] ?? $player_data['curr_max_hp'] ?? 100)); ?>,
                maxHp: <?= json_encode(intval($player_data['curr_max_hp'] ?? 100)); ?>,
                atk: <?= json_encode(intval($player_data['str'] ?? 10)); ?>,
                def: <?= json_encode(intval($player_data['def'] ?? 5)); ?>,
                dex: <?= json_encode($player_dex); ?>,
                int: <?= json_encode($player_data['int'] ?? 10); ?>,
                mana: 1,
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
        window.combatState.player.id = <?= json_encode($player_id); ?>;
        window.combatState.player.hp = <?= json_encode(intval($player_data['curr_hp'] ?? $player_data['curr_max_hp'] ?? 100)); ?>;
        window.combatState.player.mana = 1;
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
        window.battleAudioInstance = new Audio('../asset/audio/bgm/battle_theme.mp3'); 
        window.battleAudioInstance.loop = true;
        window.battleAudioInstance.volume = 0.45;
    }

    function tryUncageBattleMusic() {
        if (window.battleAudioInstance && window.battleAudioInstance.paused && !window.combatState.battleFinished) {
            window.battleAudioInstance.play().catch(err => {
                console.log("Audio Matrix: Browser input constraints pending target action...", err);
            });
        }
    }

    // ==========================================================================
    // 👑 FIXED FRONTEND TURN-ORDER SIDEBAR RE-RENDER ENGINE (NO AJAX REQ)
    // ==========================================================================
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

    // ==========================================================================
    // 🧭 TRUE TURN-BASED SYSTEM SEQUENCER
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

        if (combatant.type === 'enemy') {
            const enemyUnit = window.combatState.enemies.find(e => e.id === combatant.id);
            if (!enemyUnit || !enemyUnit.alive) {
                advanceTurnPointer();
                return;
            }
        }

        window.combatState.activeUnit = combatant;
        updateTurnOrderSidebar();

        if (combatant.type === 'player') {
            window.combatState.isPlayerTurn = true;
            toggleHUDControls(true);
            updateCombatLog(`${window.combatState.player.name}'s turn! Choose an action to strike down your foes.`);
        } else {
            window.combatState.isPlayerTurn = false;
            toggleHUDControls(false);
            setTimeout(() => {
                executeEnemyAutomatedTurn(combatant.id);
            }, 1200);
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

    // 👑 REFACTORED: Completely removed error-prone pointerEvent toggles, using safe HTML disabled property logic
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
        if (bagBtn) {
            bagBtn.disabled = !enable;
        }
    }

    function selectEnemyTarget(enemyId) {
        if (window.combatState.battleFinished || !window.combatState.isPlayerTurn) return;
        
        tryUncageBattleMusic();

        const targetEnemy = window.combatState.enemies.find(e => e.id === enemyId);
        if (!targetEnemy || !targetEnemy.alive) return;
        
        if (window.combatState.selectedSkill === null) {
            const basicAttack = {
                skill_name: 'Attack',
                skill_atk: window.combatState.player.atk,
                skill_area: 'enemy',
                mana_cost: 0
            };
            window.combatState.selectedSkill = basicAttack;
        }

        window.combatState.isPlayerTurn = false;
        toggleHUDControls(false);

        const area = window.combatState.selectedSkill.skill_area ? window.combatState.selectedSkill.skill_area.toLowerCase() : 'enemy';
        if (area === 'all' || area === 'enemy_all' || area === 'aoe') {
            executeAoESkill(window.combatState.selectedSkill);
        } else {
            executeSkillOnTarget(window.combatState.selectedSkill, targetEnemy);
        }
    }

    function executePlayerAction(actionType) {
        if (!window.combatState.isPlayerTurn || window.combatState.battleFinished) return;

        tryUncageBattleMusic();

        if (actionType === 'attack') {
            const basicAttack = {
                skill_name: 'Attack',
                skill_atk: window.combatState.player.atk,
                skill_area: 'enemy',
                mana_cost: 0
            };

            // Toggle attack off if clicked again 
            if (window.combatState.selectedSkill && window.combatState.selectedSkill.skill_name === 'Attack') {
                cancelSkillSelection();
                return;
            }

            window.combatState.selectedSkill = basicAttack;
            
            // 👑 REFACTORED: Visual scale lock ONLY instead of breaking pointerEvents properties 
            const atkBtn = document.getElementById('btn-attack');
            if (atkBtn) {
                atkBtn.style.boxShadow = '0 0 15px #ff9d41';
                atkBtn.style.transform = 'scale(0.96)';
            }
            
            document.getElementById('skills-panel').style.display = 'none';
            document.getElementById('targets-panel').style.display = 'block';
            updateCombatLog("Attack selected. Choose your target from the list or click cards directly!");
        }

        if (actionType === 'defend') {
            window.combatState.player.isDefending = true;
            updateCombatLog(`${window.combatState.player.name} takes a defensive stance! Defense is doubled.`);
            consumeResourcesAndApplyCooldowns();
            setTimeout(advanceTurnPointer, 1500);
        }
    }

    function executePlayerSkill(skill, event) {
        if (!window.combatState.isPlayerTurn || window.combatState.battleFinished) return;

        tryUncageBattleMusic();

        if (window.combatState.player.mana < skill.mana_cost) {
            updateCombatLog(`🔮 Not enough mana! Requires ${skill.mana_cost} Mana (Current: ${window.combatState.player.mana}).`);
            return;
        }

        if (window.combatState.skillCooldowns[skill.skill_id] > 0) {
            updateCombatLog(`⏳ Skill is on cooldown for ${window.combatState.skillCooldowns[skill.skill_id]} more turn(s)!`);
            return;
        }

        if (window.combatState.selectedSkill && window.combatState.selectedSkill.skill_id === skill.skill_id) {
            cancelSkillSelection();
            return;
        }

        window.combatState.selectedSkill = skill;
        document.querySelectorAll('.skill-btn').forEach(btn => btn.style.opacity = '0.5');
        
        // Emphasize selected skill visually
        if (event && event.currentTarget) {
            event.currentTarget.style.opacity = '1';
            event.currentTarget.style.boxShadow = '0 0 10px #ff9d41';
        }

        if (skill.skill_area === 'self') {
            window.combatState.isPlayerTurn = false;
            executeSkillOnSelf(skill);
        } else {
            document.getElementById('skills-panel').style.display = 'none';
            document.getElementById('targets-panel').style.display = 'block';
            updateCombatLog(`${skill.skill_name} selected. Choose your target context below.`);
        }
    }

    function cancelSkillSelection() {
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
        updateCombatLog(`${window.combatState.player.name} unleashes ${skill.skill_name} across the enemy line!`);
        consumeResourcesAndApplyCooldowns();

        window.combatState.enemies.forEach(enemy => {
            if (!enemy.alive) return;

            const basePower = parseInt(skill.skill_atk) || parseInt(skill.skill_str) || window.combatState.player.atk;
            const skillDamage = Math.max(1, basePower - enemy.def);

            enemy.hp -= skillDamage;
            if (enemy.hp <= 0) {
                enemy.hp = 0;
                enemy.alive = false;
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
                }, 1000);
            }
        });

        clearVisualIndicators();
        setTimeout(checkBattleResult, 1500);
    }

    function executeSkillOnTarget(skill, enemy) {
        consumeResourcesAndApplyCooldowns();
        const basePower = parseInt(skill.skill_atk) || parseInt(skill.skill_str) || window.combatState.player.atk;
        const skillDamage = Math.max(1, basePower - enemy.def);

        enemy.hp -= skillDamage;
        if (enemy.hp <= 0) {
            enemy.hp = 0;
            enemy.alive = false;
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

        updateCombatLog(`${window.combatState.player.name} uses ${skill.skill_name} on ${enemy.name} for ${skillDamage} damage!`);

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

            const pBar = document.getElementById('player-hp-bar');
            if (pBar) pBar.style.width = `${(window.combatState.player.hp / window.combatState.player.maxHp) * 100}%`;

            const pText = document.getElementById('player-hp-text');
            if (pText) pText.innerText = `${window.combatState.player.hp} / ${window.combatState.player.maxHp}`;

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
        updateTurnOrderSidebar(); 
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
                    btn.disabled = true; // Use disabled, not pointerEvents
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

        let effectiveDef = window.combatState.player.def;
        if (window.combatState.player.isDefending) effectiveDef = window.combatState.player.def * 2;

        let enemyDmg = Math.max(1, enemy.str - effectiveDef);
        window.combatState.player.hp -= enemyDmg;
        if (window.combatState.player.hp < 0) window.combatState.player.hp = 0;

        const pBar = document.getElementById('player-hp-bar');
        if (pBar) {
            pBar.style.width = `${(window.combatState.player.hp / window.combatState.player.maxHp) * 100}%`;
            pBar.setAttribute('aria-valuenow', window.combatState.player.hp);
        }

        const pText = document.getElementById('player-hp-text');
        if (pText) pText.innerText = `${window.combatState.player.hp} / ${window.combatState.player.maxHp}`;

        const playerDmgPop = document.getElementById('player-damage-pop');
        if (playerDmgPop) {
            playerDmgPop.innerText = `-${enemyDmg}`;
            playerDmgPop.classList.remove('d-none');
            setTimeout(() => playerDmgPop.classList.add('d-none'), 1000);
        }

        updateCombatLog(`${enemy.name} strikes ${window.combatState.player.name} for ${enemyDmg} damage!`);
        updateTurnOrderSidebar(); 

        if (window.combatState.player.hp <= 0) {
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
        if (window.battleAudioInstance) {
            window.battleAudioInstance.pause();
            window.battleAudioInstance.currentTime = 0;
        }
        calculateRewardsAndDrops();
    }

    function processBattleEndDefeat() {
        window.combatState.battleFinished = true;
        if (window.battleAudioInstance) {
            window.battleAudioInstance.pause();
            window.battleAudioInstance.currentTime = 0;
        }
        updateCombatLog("Defeat... You have fallen in battle.");
        updateTurnOrderSidebar();
    }

    function calculateRewardsAndDrops() {
        updateCombatLog("Victory! Calculating drops...");

        let totalExpGained = 0;
        let generatedDrops = [];
        const enemyCount = window.combatState.enemies.length;

        for (let i = 0; i < enemyCount; i++) {
            totalExpGained += Math.floor(randRange(15, 30));
            if (Math.random() <= 0.65) {
                generatedDrops.push({
                    item_id: 1,
                    name: "Health Potion"
                });
            }
        }

        setTimeout(() => {
            saveRewardsToDatabase(totalExpGained, generatedDrops);
        }, 1500);
    }

    function saveRewardsToDatabase(exp, drops) {
        const payload = {
            player_id: window.combatState.player.id,
            exp_gained: exp,
            items_dropped: drops
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
                if (payload.items_dropped.length > 0) summary += `Looted Items!`;
                if (data.leveled_up) summary += ` ✨ LEVEL UP to ${data.new_level}!`;
                showVictoryRedirect(summary);
            })
            .catch(() => showVictoryRedirect(`Victory! Gained +${exp} EXP.`));
    }

    function showVictoryRedirect(summaryText) {
        const dialogBox = document.getElementById('combat-dialogue-box');
        if (dialogBox) {
            dialogBox.style.maxHeight = "none";
            dialogBox.style.height = "auto";
            dialogBox.innerHTML = `
        <div class="d-flex justify-content-between align-items-center w-100 py-1">
            <p class="m-0 fw-bold text-white">${summaryText}</p>
            <a href="../index.php?p=level1&id=${window.combatState.player.id}" class="btn btn-sm text-dark fw-bold" style="background-color: #FAC79B; border: 2px solid #5A3A2A;">Return to Map</a>
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

    // 👑 REFACTORED: Immediately Invoked Function safely replaces $(document).ready
    // This guarantees the combat flow triggers even if this layout is injected mid-session via AJAX where the "document" was already historically marked as fully "ready".
    (function bootCombatEngine() {
        syncManaBarInterfaceDisplay();
        updateSkillButtonsCooldownUI();
        
        setTimeout(runNextTurnSequence, 400);
    })();
</script>