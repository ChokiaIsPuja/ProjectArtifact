<?php
include_once __DIR__ . '/../../conn.php';

$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

$player_data = [];

if ($player_id > 0) {
    $query = "SELECT p.*, c.class_name, c.avatar, ps.curr_max_hp, ps.curr_atk, ps.curr_def, ps.curr_spd, p.level
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
    $player_data['spd'] = intval(($player_data['curr_spd'] ?? $player_data['spd']) ?: 10);
    $player_data['curr_spd'] = $player_data['spd'];

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
        'spd' => 10,
        'curr_spd' => 10,
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

// --- FIXED MULTI-ENEMY POPULATION ENGINE ---
$active_enemies = [];
$turn_order_stack = [];
$encounter_limit = rand(2, 4);

$stmt2 = mysqli_prepare($conn, "SELECT e.enemy_id, e.enemy_name, e.sprite, es.hp, es.atk, es.def, es.spd 
                                FROM enemy e 
                                JOIN enemy_stats es ON e.enemy_id = es.enemy_id");
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);

$enemy_template_pool = [];
while ($template = mysqli_fetch_assoc($res2)) {
    $enemy_template_pool[] = $template;
}
mysqli_stmt_close($stmt2);

$player_speed = intval($player_data['spd'] ?? 10);
$turn_order_stack[] = [
    'id'       => null,
    'name'     => $player_data['name'],
    'type'     => 'player',
    'sprite'   => $player_data['avatar'] ?? 'player_avatar.png',
    'spd'      => $player_speed,
    'curr_spd' => $player_speed
];

if (!empty($enemy_template_pool)) {
    for ($i = 0; $i < $encounter_limit; $i++) {
        $base = $enemy_template_pool[array_rand($enemy_template_pool)];
        $enemySpd = intval(($base['spd'] ?? $base['speed']) ?: 8);

        $enemy_instance = [
            'id'         => $i,
            'enemy_id'   => $base['enemy_id'],
            'name'       => $base['enemy_name'] . ' ' . ($i + 1),
            'enemy_name' => $base['enemy_name'],
            'sprite'     => $base['sprite'],
            'hp'         => intval($base['hp']),
            'max_hp'     => intval($base['hp']),
            'atk'        => intval($base['atk']),
            'def'         => intval($base['def']),
            'spd'        => $enemySpd,
            'curr_spd'   => $enemySpd,
            'alive'      => true
        ];

        $active_enemies[] = $enemy_instance;

        $turn_order_stack[] = [
            'id'       => $i,
            'name'     => $enemy_instance['name'],
            'type'     => 'enemy',
            'sprite'   => $enemy_instance['sprite'],
            'spd'      => $enemy_instance['spd'],
            'curr_spd' => $enemy_instance['curr_spd']
        ];
    }
}

usort($turn_order_stack, function ($a, $b) {
    return $b['spd'] <=> $a['spd'];
});

$enemies     = $active_enemies;
$enemy_party = $active_enemies;
$combatants  = $turn_order_stack;

$player_inventory = [];
if ($player_id > 0) {
    $stmt3 = mysqli_prepare($conn, "SELECT b.qty, i.item_id, i.item_name, i.item_type, ia.att_atk, ia.att_hp 
                                    FROM bag b 
                                    JOIN item i ON b.item_id = i.item_id 
                                    JOIN item_attributes ia ON i.id_item_attributes = ia.id_item_attributes 
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
                           sa.skill_atk, sa.skill_def, sa.skill_heal
                    FROM obtainable_skill ob
                    JOIN skill_attributes sa ON ob.id_skill_attributes = sa.id_skill_attributes
                    WHERE ob.class_id = ? AND ob.lvl_required <= ?
                    ORDER BY ob.skill_id ASC";

    $stmt_skills = mysqli_prepare($conn, $skill_query);
    if ($stmt_skills) {
        mysqli_stmt_bind_param($stmt_skills, "ii", $player_class, $player_level);
        mysqli_stmt_execute($stmt_skills);
        $skill_result = mysqli_stmt_get_result($stmt_skills);
        while ($skill_row = mysqli_fetch_assoc($skill_result)) {
            $player_skills[] = $skill_row;
        }
        mysqli_stmt_close($stmt_skills);
    }
}
?>

<div class="d-flex flex-column justify-content-between h-100 p-3">

    <div class="damage-popup text-danger fw-bold position-absolute d-none" id="player-damage-pop" style="z-index: 99; top: 20%; left: 10%;"> -0 </div>

    <div class="battle-stage d-flex align-items-end justify-content-around flex-grow-1 mb-3" style="min-height: 350px; position: relative;">

        <div class="enemy-party d-flex gap-4 align-items-end justify-content-center w-100" style="padding-bottom: 60px;">
            <?php if (!empty($active_enemies)): ?>
                <?php foreach ($active_enemies as $index => $enemy):
                    $enemyUniqueId = $enemy['id'];
                ?>
                    <div class="enemy-container d-flex flex-column align-items-center justify-content-end text-center position-relative"
                        id="enemy-target-<?= $enemyUniqueId ?>"
                        onclick="selectEnemyTarget(<?= $enemyUniqueId ?>)"
                        style="cursor: pointer; min-width: 120px; <?= !$enemy['alive'] ? 'opacity: 0.4;' : '' ?>">

                        <div class="damage-popup text-warning fw-bold position-absolute top-0 start-50 translate-middle fs-2 d-none" id="enemy-damage-<?= $enemyUniqueId ?>" style="z-index: 99;">-0</div>

                        <div class="progress mb-2 w-100" style="max-width: 100px; height: 10px; background-color: rgba(0,0,0,0.3); border-radius: 4px;">
                            <div id="enemy-hp-bar-<?= $enemyUniqueId ?>"
                                class="progress-bar bg-danger"
                                role="progressbar"
                                style="width: <?= ($enemy['hp'] / $enemy['max_hp']) * 100 ?>%;"
                                aria-valuenow="<?= $enemy['hp'] ?>"
                                aria-valuemin="0"
                                aria-valuemax="<?= $enemy['max_hp'] ?>"></div>
                        </div>

                        <?php if (!empty($enemy['sprite'])): ?>
                            <div class="d-flex align-items-end justify-content-center" style="height: 180px; width: 100%;">
                                <img src="../../asset/sprites/enemies/lv1/<?= htmlspecialchars($enemy['sprite']) ?>"
                                    class="enemy-sprite item-rendering-pixelated"
                                    style="max-height: 100%; max-width: 100%; object-fit: contain; filter: drop-shadow(0px 8px 4px rgba(0,0,0,0.2));"
                                    alt="<?= htmlspecialchars($enemy['name']) ?>"
                                    onerror="this.src='https://placehold.co/120x180?text=Enemy'">
                            </div>
                        <?php endif; ?>

                        <span class="badge mt-2" style="background-color: #B46940; font-size: 0.85rem; width: fit-content;">
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
            <div class="p-3 d-flex flex-column gap-2 justify-content-center h-100" style="background-color: #FAC79B; border-radius: 12px; min-height: 140px;">

                <div id="skills-panel">
                    <?php if (!empty($player_skills)): ?>
                        <div class="row g-2">
                            <?php foreach ($player_skills as $skill): ?>
                                <div class="col-6">
                                    <button class="btn w-100 skill-btn fw-bold py-2"
                                        style="background-color: #8B5A3C; color: white; border: 2px solid #5A3A2A; border-radius: 8px;"
                                        onclick="executePlayerSkill(<?= htmlspecialchars(json_encode($skill)) ?>, event)"
                                        data-skill-id="<?= htmlspecialchars($skill['skill_id']) ?>"
                                        data-skill-name="<?= htmlspecialchars($skill['skill_name']) ?>"
                                        title="<?= htmlspecialchars($skill['skill_desc']) ?>">
                                        <?= htmlspecialchars($skill['skill_name']) ?>
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
                    <div class="d-flex flex-column gap-2">
                        <div class="text-white fw-bold mb-2" style="font-size: 0.9rem;">Select Target:</div>
                        <?php foreach ($active_enemies as $enemy): ?>
                            <button class="btn w-100 enemy-target-btn fw-bold py-2"
                                id="list-target-btn-<?= $enemy['id'] ?>"
                                style="background-color: #B46940; color: white; border: 2px solid #8B4513; border-radius: 8px; text-align: left;"
                                onclick="selectEnemyTarget(<?= intval($enemy['id'] ?? 0) ?>)">
                                <?= htmlspecialchars($enemy['name']) ?> <small style="opacity: 0.7;">(HP: <span id="list-hp-<?= $enemy['id'] ?>"><?= intval($enemy['hp'] ?? 0) ?></span>/<?= intval($enemy['max_hp'] ?? 0) ?>)</small>
                            </button>
                        <?php endforeach; ?>
                        <button class="btn w-100 fw-bold py-2 mt-2" style="background-color: #555; color: white; border: none; border-radius: 8px;" onclick="cancelSkillSelection()">Cancel</button>
                    </div>
                </div>

            </div>
        </div>
        <div class="col-3">
            <div class="p-2 d-flex flex-column gap-2 justify-content-center h-100" style="background-color: #FAC79B; border-radius: 12px; min-height: 180px;">
                <button type="button" id="btn-attack" onclick="executePlayerAction('attack')" class="btn btn-lg text-white w-100 py-2 fw-bold combat-btn" style="background-color: #B46940; border-radius: 8px; border:3px #ff9d41 solid; height:80px">Attack!</button>
                <div class="row g-2">
                    <div class="col-6 pr-1 mt-2">
                        <button class="btn btn-dark w-100" style="background-color: #B46940; border:none; color:#fff; height:60px" data-bs-toggle="modal" data-bs-target="#modalInventory">Inventory</button>
                    </div>
                    <div class="col-6 pl-1 mt-2">
                        <button type="button" id="btn-run" onclick="executePlayerAction('defend')" class="btn text-white w-100 py-2 fw-bold combat-btn" style="background-color: #B46940; border-radius: 8px; border: none; height:60px">Defend</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="p-3 text-white d-flex align-items-center" id="combat-dialogue-box" style="background-color: #D39670; border-radius: 12px; max-height: 50px; font-size: 1.1rem; border: 3px solid #FAC79B; overflow-y: auto;">
                <p class="m-0" id="combat-log-text">Mimi's turn! Choose an action to strike down your foes.</p>
            </div>
        </div>
    </div>
</div>

<script>
    const combatState = {
        player: {
            id: <?= json_encode($player_id); ?>,
            name: <?= json_encode($player_data['name']); ?>,
            hp: <?= json_encode(intval($player_data['curr_hp'] ?? $player_data['curr_max_hp'] ?? 100)); ?>,
            maxHp: <?= json_encode(intval($player_data['curr_max_hp'] ?? 100)); ?>,
            atk: <?= json_encode(intval($player_data['curr_atk'] ?? 10)); ?>,
            def: <?= json_encode(intval($player_data['curr_def'] ?? 5)); ?>,
            spd: <?= json_encode($player_speed); ?>,
            isDefending: false
        },
        enemies: <?= json_encode($active_enemies); ?>,
        turnOrder: <?= json_encode($turn_order_stack); ?>,
        inventory: <?= json_encode($player_inventory); ?>,
        skills: <?= json_encode($player_skills); ?>,
        selectedSkill: null,
        isPlayerTurn: true,
        battleFinished: false
    };

    function selectEnemyTarget(enemyId) {
        if (combatState.battleFinished || !combatState.isPlayerTurn) return;
        const targetEnemy = combatState.enemies.find(e => e.id === enemyId);

        if (!targetEnemy || !targetEnemy.alive) return;
        if (combatState.selectedSkill === null) {
            updateCombatLog("Select Attack or a Skill first!");
            return;
        }

        combatState.isPlayerTurn = false;

        if (combatState.selectedSkill.skill_area === 'all' || combatState.selectedSkill.skill_area === 'enemy_all' || combatState.selectedSkill.skill_area === 'aoe') {
            executeAoESkill(combatState.selectedSkill);
        } else {
            executeSkillOnTarget(combatState.selectedSkill, targetEnemy);
        }
    }

    function executePlayerAction(actionType) {
        if (!combatState.isPlayerTurn || combatState.battleFinished) return;

        if (actionType === 'attack') {
            const basicAttack = {
                skill_name: 'Attack',
                skill_atk: combatState.player.atk,
                skill_area: 'enemy'
            };

            if (combatState.selectedSkill && combatState.selectedSkill.skill_name === 'Attack') {
                cancelSkillSelection();
                return;
            }

            combatState.selectedSkill = basicAttack;
            document.getElementById('btn-attack').style.opacity = '0.5';
            document.getElementById('skills-panel').style.display = 'none';
            document.getElementById('targets-panel').style.display = 'block';
            updateCombatLog("Attack selected. Choose your target from the lists or click cards directly!");
        }

        if (actionType === 'defend') {
            combatState.player.isDefending = true;
            updateCombatLog(`${combatState.player.name} takes a defensive stance! Defense is doubled.`);
            cancelSkillSelection();
            combatState.isPlayerTurn = false;
            setTimeout(triggerEnemyTurn, 1500);
        }
    }

    function executePlayerSkill(skill, event) {
        if (!combatState.isPlayerTurn || combatState.battleFinished) return;

        if (combatState.selectedSkill && combatState.selectedSkill.skill_id === skill.skill_id) {
            cancelSkillSelection();
            return;
        }

        combatState.selectedSkill = skill;
        document.querySelectorAll('.skill-btn').forEach(btn => btn.style.opacity = '0.5');

        if (skill.skill_area === 'self') {
            combatState.isPlayerTurn = false;
            executeSkillOnSelf(skill);
        } else {
            document.getElementById('skills-panel').style.display = 'none';
            document.getElementById('targets-panel').style.display = 'block';
            updateCombatLog(`${skill.skill_name} selected. Choose your target context below.`);
        }
    }

    function cancelSkillSelection() {
        combatState.selectedSkill = null;
        document.getElementById('btn-attack').style.opacity = '1';
        document.querySelectorAll('.skill-btn').forEach(btn => btn.style.opacity = '1');
        document.getElementById('targets-panel').style.display = 'none';
        document.getElementById('skills-panel').style.display = 'block';
        updateCombatLog("Action cancelled. Choose an action.");
    }

    function executeAoESkill(skill) {
        updateCombatLog(`${combatState.player.name} unleashes ${skill.skill_name} across the enemy line!`);

        combatState.enemies.forEach(enemy => {
            if (!enemy.alive) return;

            const basePower = parseInt(skill.skill_atk) || combatState.player.atk;
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
        setTimeout(checkBattleResult, 1800);
    }

    function executeSkillOnTarget(skill, enemy) {
        const basePower = parseInt(skill.skill_atk) || combatState.player.atk;
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

        updateCombatLog(`${combatState.player.name} uses ${skill.skill_name} on ${enemy.name} for ${skillDamage} damage!`);

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
        setTimeout(checkBattleResult, 1800);
    }

    function executeSkillOnSelf(skill) {
        if (skill.skill_heal > 0) {
            combatState.player.hp += parseInt(skill.skill_heal);
            if (combatState.player.hp > combatState.player.maxHp) combatState.player.hp = combatState.player.maxHp;

            // Update visual health bar width percentage in sidebar
            const pBar = document.getElementById('player-hp-bar');
            if (pBar) pBar.style.width = `${(combatState.player.hp / combatState.player.maxHp) * 100}%`;

            // Update numeric text element in sidebar
            const pText = document.getElementById('player-hp-text');
            if (pText) pText.innerText = `${combatState.player.hp} / ${combatState.player.maxHp}`;

            updateCombatLog(`${combatState.player.name} casts ${skill.skill_name} and recovers ${skill.skill_heal} HP!`);
        }
        clearVisualIndicators();
        setTimeout(checkBattleResult, 1800);
    }

    function clearVisualIndicators() {
        combatState.selectedSkill = null;
        document.getElementById('btn-attack').style.opacity = '1';
        document.querySelectorAll('.skill-btn').forEach(btn => btn.style.opacity = '1');
        document.getElementById('targets-panel').style.display = 'none';
        document.getElementById('skills-panel').style.display = 'block';
    }

    function triggerEnemyTurn() {
        if (combatState.battleFinished) return;
        updateCombatLog("Enemies are counter-attacking...");

        const livingEnemies = combatState.enemies.filter(e => e.alive);
        let sequenceDelay = 1000;

        if (livingEnemies.length === 0) {
            checkBattleResult();
            return;
        }

        livingEnemies.forEach((enemy) => {
            setTimeout(() => {
                if (combatState.player.hp <= 0 || combatState.battleFinished) return;

                let effectiveDef = combatState.player.def;
                if (combatState.player.isDefending) effectiveDef = combatState.player.def * 2;

                let enemyDmg = Math.max(1, enemy.atk - effectiveDef);
                combatState.player.hp -= enemyDmg;
                if (combatState.player.hp < 0) combatState.player.hp = 0;

                // 1. DYNAMICALLY UPDATE VISUAL SIDEBAR HP BAR
                const pBar = document.getElementById('player-hp-bar');
                if (pBar) {
                    pBar.style.width = `${(combatState.player.hp / combatState.player.maxHp) * 100}%`;
                    pBar.setAttribute('aria-valuenow', combatState.player.hp);
                }

                // 2. DYNAMICALLY UPDATE NUMERIC SIDEBAR HP TEXT
                const pText = document.getElementById('player-hp-text');
                if (pText) {
                    pText.innerText = `${combatState.player.hp} / ${combatState.player.maxHp}`;
                }

                const playerDmgPop = document.getElementById('player-damage-pop');
                if (playerDmgPop) {
                    playerDmgPop.innerText = `-${enemyDmg}`;
                    playerDmgPop.classList.remove('d-none');
                    setTimeout(() => playerDmgPop.classList.add('d-none'), 1000);
                }

                updateCombatLog(`${enemy.name} strikes ${combatState.player.name} for ${enemyDmg} damage!`);

                if (combatState.player.hp <= 0) {
                    combatState.battleFinished = true;
                    updateCombatLog("Defeat... You have fallen in battle.");
                }
            }, sequenceDelay);

            sequenceDelay += 1500;
        });

        setTimeout(() => {
            if (combatState.player.hp > 0 && !combatState.battleFinished) {
                combatState.player.isDefending = false;
                combatState.isPlayerTurn = true;
                updateCombatLog(`${combatState.player.name}'s turn! Choose an action.`);
            }
        }, sequenceDelay);
    }

    function checkBattleResult() {
        const anyEnemiesAlive = combatState.enemies.some(e => e.alive);
        if (!anyEnemiesAlive) {
            combatState.battleFinished = true;
            calculateRewardsAndDrops();
        } else if (!combatState.isPlayerTurn) {
            triggerEnemyTurn();
        }
    }

    function calculateRewardsAndDrops() {
        updateCombatLog("Victory! Calculating drops...");

        let totalExpGained = 0;
        let generatedDrops = [];

        // FIX: Get a count based on the ORIGINAL number of enemies generated
        // Or just use the array length before it was mutated
        const enemyCount = combatState.enemies.length;

        for (let i = 0; i < enemyCount; i++) {
            totalExpGained += Math.floor(randRange(15, 30));
            // Drop rate calculation
            if (Math.random() <= 0.65) {
                generatedDrops.push({
                    item_id: 1,
                    name: "Health Potion"
                });
            }
        }

        // Add a slight delay to ensure the UI logs the victory message first
        setTimeout(() => {
            saveRewardsToDatabase(totalExpGained, generatedDrops);
        }, 1500);
    }

    function saveRewardsToDatabase(exp, drops) {
        const payload = {
            player_id: combatState.player.id,
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
        // FIX: Properly select the element
        const dialogBox = document.getElementById('combat-dialogue-box');

        // Check if the element actually exists before trying to modify it
        if (dialogBox) {
            dialogBox.style.maxHeight = "none";
            dialogBox.style.height = "auto";
            dialogBox.innerHTML = `
            <div class="d-flex justify-content-between align-items-center w-100 py-1">
                <p class="m-0 fw-bold">${summaryText}</p>
                <a href="../index.php?p=level1&id=${combatState.player.id}" class="btn btn-sm text-white fw-bold" style="background-color: #8B5A3C; border: 2px solid #5A3A2A;">Return to Map</a>
            </div>`;
        } else {
            // Fallback in case the element was removed from the DOM
            console.error("Combat dialogue box not found!");
            window.location.href = `../index.php?p=level1&id=${combatState.player.id}`;
        }
    }

    function randRange(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function updateCombatLog(message) {
        const logText = document.getElementById('combat-log-text');
        if (logText) logText.innerText = message;
    }
</script>