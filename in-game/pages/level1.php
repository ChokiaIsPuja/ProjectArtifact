<?php
require '../conn.php';
global $conn;

// Player ID must come from URL parameter (user can have multiple characters)
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

$currentNode = null;
$playerJob = 'knight';
$runSeed = 0; // Initialize our procedural layout anchor seed

// Initialize player stat defaults for DSS strategy weighing systems
$current_hp = 100;
$max_hp = 100;

if ($id > 0) {
    // 1. Fetch player job info
    $playerQuery = "SELECT player.*, class.class_name 
                    FROM player 
                    LEFT JOIN class ON player.class_id = class.class_id 
                    WHERE player.player_id = ? 
                    LIMIT 1";

    $playerStmt = mysqli_prepare($conn, $playerQuery);
    if ($playerStmt) {
        mysqli_stmt_bind_param($playerStmt, "i", $id);
        mysqli_stmt_execute($playerStmt);
        $playerResult = mysqli_stmt_get_result($playerStmt);
        if ($playerData = mysqli_fetch_assoc($playerResult)) {
            $playerJob = !empty($playerData['class_name']) ? $playerData['class_name'] : 'knight';
        }
        mysqli_stmt_close($playerStmt);
    }

    // 1b. FETCH LIVE VITALS FOR DYNAMIC STRATEGY WEIGHT EVALUATION
    $vitalsQuery = "SELECT curr_hp, curr_max_hp FROM player_stats WHERE player_id = ? LIMIT 1";
    $vitalsStmt = mysqli_prepare($conn, $vitalsQuery);
    if ($vitalsStmt) {
        mysqli_stmt_bind_param($vitalsStmt, "i", $id);
        mysqli_stmt_execute($vitalsStmt);
        $vitalsResult = mysqli_stmt_get_result($vitalsStmt);
        if ($vitalsData = mysqli_fetch_assoc($vitalsResult)) {
            $current_hp = intval($vitalsData['curr_hp']);
            $max_hp = intval($vitalsData['curr_max_hp'] ?: 100);
        }
        mysqli_stmt_close($vitalsStmt);
    }

    // 2. Fetch active run tracking position AND our structural map seed identity blueprint
    $runQuery = "SELECT current_node, run_seed FROM runs WHERE player_id = ? ORDER BY started_at DESC LIMIT 1";
    $runStmt = mysqli_prepare($conn, $runQuery);
    if ($runStmt) {
        mysqli_stmt_bind_param($runStmt, "i", $id);
        mysqli_stmt_execute($runStmt);
        $runResult = mysqli_stmt_get_result($runStmt);
        if ($runData = mysqli_fetch_assoc($runResult)) {
            $currentNode = $runData['current_node'];
            $runSeed = intval($runData['run_seed']); // Securely capture the run seed!
        }
        mysqli_stmt_close($runStmt);
    }
}

// Fallback safety check if no run was initialized for this player
if ($runSeed === 0) {
    die("Error: No active run session found for this character. Please restart from the main menu.");
}
?>
<style>
    .dss-choice-btn {
        transition: all 0.2s ease-in-out;
    }

    .dss-choice-btn:hover {
        transform: translateX(6px);
        background-color: #502810 !important;
        color: #fff !important;
    }

    /* ==========================================================================
       ✨ DSS GLOWING PATH ANIMATION LAYER
       ========================================================================== */
    .dss-highlight-safe {
        border: 4px solid #2ecc71 !important;
        box-shadow: 0 0 25px #2ecc71, inset 0 0 10px #2ecc71 !important;
        animation: dssPulseSafe 2s infinite ease-in-out;
    }
    .dss-highlight-greed {
        border: 4px solid #f1c40f !important;
        box-shadow: 0 0 25px #f1c40f, inset 0 0 10px #f1c40f !important;
        animation: dssPulseGreed 2s infinite ease-in-out;
    }
    .dss-highlight-optimal {
        border: 4px solid #9b59b6 !important;
        box-shadow: 0 0 25px #9b59b6, inset 0 0 10px #9b59b6 !important;
        animation: dssPulseOptimal 2s infinite ease-in-out;
    }

    @keyframes dssPulseSafe {
        0%, 100% { transform: scale(1); box-shadow: 0 0 15px #2ecc71; }
        50% { transform: scale(1.08); box-shadow: 0 0 25px #2ecc71; }
    }
    @keyframes dssPulseGreed {
        0%, 100% { transform: scale(1); box-shadow: 0 0 15px #f1c40f; }
        50% { transform: scale(1.08); box-shadow: 0 0 25px #f1c40f; }
    }
    @keyframes dssPulseOptimal {
        0%, 100% { transform: scale(1); box-shadow: 0 0 15px #9b59b6; }
        50% { transform: scale(1.08); box-shadow: 0 0 25px #9b59b6; }
    }
</style>
<div class="map-area">

    <div class="viewport">
        <div class="row" style="position: relative; z-index: 10; width: 100%; overflow: hidden;">
            <div class="col-12 text-center position-relative" style="min-height: 90px; padding: 20px 38px 0 38px;">

                <h2 class="text-dark m-0" style="position: relative; display: inline-block; z-index: 2;">
                    Dungeon I - Plains
                </h2>

                <hr style="
            position: absolute; 
            top: 70px; 
            left: 38px; 
            right: 38px; 
            height: 2px; 
            background-color: #B46940; 
            border: none; 
            margin: 0; 
            opacity: 1; 
            z-index: 1;
            height: 4px;
            border-radius: 3px;
        ">

            </div>
        </div>
        <div class="map-controls">
            <button id="zoomIn" style="border: 3px solid #B46940; background-color: #FAC79B; border-radius: 6px; color: #B46940; width: 40px; height: 40px; font-size: 24px;">+</button>
            <button id="zoomOut" style="border: 3px solid #B46940; background-color: #FAC79B; border-radius: 6px; color: #B46940; width: 40px; height: 40px; font-size: 24px;">-</button>
            <button id="centerMap" style="border: 3px solid #B46940; background-color: #FAC79B; border-radius: 6px; color: #B46940; width: 80px; height: 40px;">Center</button>
        </div>

        <div class="map" id="map" style="position: relative; width: 5000px; height: 2500px;">
            <svg id="links" class="links"></svg>

        </div>
    </div>
</div>
<div class="position-absolute" style="bottom: 35px; right: 50px; z-index: 1001;">
    <button id="mimiTriggerBtn" class="btn fw-bold d-flex flex-column align-items-center justify-content-center p-3 text-center"
        style="background-color: #FAC79B; color: #B46940; border: 4px solid #B46940; font-size: 20px; height: 50px; width: 150px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); cursor: pointer;"> <h2 style="margin: 0; font-size: 24px;">Hey, Mimi!</h2>
    </button>
</div>

<div id="mimiCharacterPopup" class="d-none" style="position: absolute; bottom: 180px; right: 60px; z-index: 1011; pointer-events: none; background-color:#FAC79B; border: 4px solid #B46940; border-radius: 12px; padding: 0; width: 240px; height: auto; align-items: center; justify-content: center;">
    <img src="../asset/sprites/mimi/mimi.png" alt="Mimi Full" class="pixelated mimi-floating-anim" style="width: 240px; height: auto; object-fit: contain;">
</div>

<div id="mimiDialogueOverlay" class="d-none" style="position: absolute; bottom: 25px; left: 30px; right: 30px; height: 140px; background-color: #FAC79B; border: 4px solid #B46940; border-radius: 12px; z-index: 1010; padding: 14px 24px; font-family: 'Jaro', sans-serif;">
    <div class="d-flex flex-column h-100 justify-content-between">
        
        <div style="color: #502810; border-bottom: 2px dashed rgba(180, 105, 64, 0.3); padding-bottom: 4px;">
            <span style="font-size: 18px; color: #B46940; font-weight: bold; margin-right: 8px;">Mimi:</span>
            <span style="font-size: 15px; font-family: sans-serif; font-weight: bold;">"I've scanned the roadmap layout ahead. What objective are we focusing on for the rest of this run?"</span>
        </div>
        
        <div class="d-flex gap-3 justify-content-between align-items-center" style="margin-top: 6px;">
            <button class="dss-choice-btn btn btn-sm text-start fw-bold flex-grow-1 py-2 px-3" onclick="executeMapDSSRouteCalculation('safe')" style="background-color: #FFF2E6; border: 2px solid #2ecc71; color: #27ae60; font-size: 14px; border-radius: 6px;">
                🛡️ Safest Path (Least Danger)
            </button>
            <button class="dss-choice-btn btn btn-sm text-start fw-bold flex-grow-1 py-2 px-3" onclick="executeMapDSSRouteCalculation('greed')" style="background-color: #FFF2E6; border: 2px solid #f1c40f; color: #b78a00; font-size: 14px; border-radius: 6px;">
                💰 Greed Path (Maximize Gold)
            </button>
            <button class="dss-choice-btn btn btn-sm text-start fw-bold flex-grow-1 py-2 px-3" onclick="executeMapDSSRouteCalculation('optimal')" style="background-color: #FFF2E6; border: 2px solid #9b59b6; color: #7d3c98; font-size: 14px; border-radius: 6px;">
                ⚖️ Optimal Path (Calculated Risk)
            </button>
        </div>

    </div>
    <button onclick="toggleMimiDialogueDisplay(false)" style="position: absolute; top: 6px; right: 12px; border: none; background: none; color: #B46940; font-size: 16px; font-weight: bold; cursor: pointer;">✕</button>
</div>
<script>
    // ==========================================================================
    // 💬 RPG INTERACTIVE NPC DIALOGUE SYSTEM ACTIONS
    // ==========================================================================
    function toggleMimiDialogueDisplay(show) {
        const mimiSpritePopup = document.getElementById('mimiCharacterPopup');
        const mimiBoxOverlay = document.getElementById('mimiDialogueOverlay');

        if (!mimiSpritePopup || !mimiBoxOverlay) return;

        if (show) {
            mimiSpritePopup.classList.remove('d-none');
            mimiSpritePopup.classList.add('d-flex');
            mimiBoxOverlay.classList.remove('d-none');
        } else {
            mimiSpritePopup.classList.add('d-none');
            mimiSpritePopup.classList.remove('d-flex');
            mimiBoxOverlay.classList.add('d-none');
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const triggerBtn = document.getElementById('mimiTriggerBtn');
        if (triggerBtn) {
            triggerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const mimiBoxOverlay = document.getElementById('mimiDialogueOverlay');
                const isCurrentlyHidden = mimiBoxOverlay ? mimiBoxOverlay.classList.contains('d-none') : true;

                toggleMimiDialogueDisplay(isCurrentlyHidden);
            });
        }
    });

    function executeMapDSSRouteCalculation(mode) {
        if (typeof window.calculateDSSRoute === 'function') {
            window.calculateDSSRoute(mode);
        }
        toggleMimiDialogueDisplay(false);
    }

    // ==========================================================================
    // 🌐 GLOBAL SHARED COORDINATE SPACE & SINGLE RUNTIME INITIALIZATION GUARD
    // ==========================================================================
    if (typeof window.mapIsEngineInitialized === 'undefined') {
        window.mapIsEngineInitialized = false;
    }

    window.mapScale = 1;
    window.mapTranslateX = -700;
    window.mapTranslateY = -640;
    let isDragging = false;
    let dragStartX;
    let dragStartY;

    let mapDataStructure = [];
    let globalEdges = [];

    // --- 🎮 NEW: REGISTER GLOBAL AUDIO SHUTTLE MATRICES ON THE MAP RUNTIME ---
    if (typeof window.battleAudioInstance === 'undefined') {
        window.battleAudioInstance = new Audio('../asset/bgm/battle_bgm.mp3');
        window.battleAudioInstance.loop = true;
        window.battleAudioInstance.volume = 0.45;
    }
    if (typeof window.mapSFX === 'undefined') {
        window.mapSFX = {
            select: new Audio('../asset/sfx/select.wav')
        };
        window.mapSFX.select.volume = 0.35;
    }

    document.addEventListener("DOMContentLoaded", function() {
        if (window.mapIsEngineInitialized) {
            console.log("Map Engine: Dual execution blocked via defensive initialization runtime guard.");
            return;
        }
        window.mapIsEngineInitialized = true;

        console.log("Map Engine: Initializing Nearest-Neighbor Web Graph...");

        const mapContainer = document.getElementById('map');
        const svgContainer = document.getElementById('links');
        const viewport = document.querySelector('.viewport');
        if (!mapContainer || !svgContainer) return;

        // ==========================================================================
        // 💾 PARSE BACKEND STATES AND INITIALIZE SEEDED GENERATOR
        // ==========================================================================
        const playerId = <?= json_encode((int)$id) ?>;
        const currentPlayerNodeId = <?= json_encode($currentNode) ?>;
        const characterClass = <?= json_encode($playerJob) ?>;
        const mapSeed = <?= json_encode($runSeed) ?>;

        console.log(`State Loaded -> Seed: ${mapSeed}, ActiveNode: ${currentPlayerNodeId}, Class: ${characterClass}`);

        function seededRandom(seed) {
            let m = 0x80000000;
            let a = 1103515245;
            let c = 12345;
            let s = seed;
            return function() {
                s = (a * s + c) % m;
                return s / (m - 1);
            };
        }
        const myRandom = seededRandom(mapSeed);

        // ==========================================================================
        // 🗺️ EVENLY DISTRIBUTED LAYER GENERATION
        // ==========================================================================
        const totalColumns = 14;
        const canvasWidth = 5000;
        const canvasHeight = 2500;

        let nodeSequenceCounter = 1;
        let combatStreak = 0;

        for (let col = 0; col <= totalColumns; col++) {
            mapDataStructure[col] = [];
            const columnX = 400 + (col * ((canvasWidth - 800) / totalColumns));

            let nodeCount = 0;

            if (col === 0 || col === totalColumns) {
                nodeCount = 1;
            } else if (col === 1 || col === totalColumns - 1) {
                nodeCount = Math.floor(myRandom() * 2) + 3;
            } else {
                nodeCount = Math.floor(myRandom() * 3) + 4;
            }

            const segmentHeight = (canvasHeight - 600) / nodeCount;

            for (let i = 0; i < nodeCount; i++) {
                let chosenType = 'combat';
                let roll = myRandom();

                if (combatStreak < 2) {
                    chosenType = (roll < 0.75) ? 'combat' : 'event';
                } else {
                    if (roll < 0.55) chosenType = 'combat';
                    else if (roll < 0.80) chosenType = 'event';
                    else chosenType = 'shop';
                }

                if (chosenType === 'combat') combatStreak++;
                if (chosenType === 'shop') combatStreak = 0;

                if (col === 0) chosenType = 'start';
                if (col === totalColumns) chosenType = 'boss';

                let finalY = 300 + (i * segmentHeight) + (segmentHeight / 2) + (myRandom() * 40 - 20);
                if (col === 0 || col === totalColumns) finalY = canvasHeight / 2;

                let finalX = columnX + (col === 0 || col === totalColumns ? 0 : (myRandom() * 40 - 20));

                let label = chosenType.charAt(0).toUpperCase() + chosenType.slice(1);
                if (col === totalColumns) label = "BOSS";

                mapDataStructure[col].push({
                    id: `node${nodeSequenceCounter++}`,
                    col: col,
                    nodeIdx: i,
                    x: Math.floor(finalX),
                    y: Math.floor(finalY),
                    type: chosenType,
                    label: label
                });
            }
        }

        // ==========================================================================
        // 🔗 NEAREST-NEIGHBOR EDGE CONNECTOR
        // ==========================================================================
        for (let col = 0; col < totalColumns; col++) {
            let currentLayer = mapDataStructure[col];
            let nextLayer = mapDataStructure[col + 1];
            let localEdges = [];

            currentLayer.forEach(u => {
                let closestV = nextLayer[0];
                let minDistance = Infinity;

                nextLayer.forEach(v => {
                    let dist = Math.abs(u.y - v.y);
                    if (dist < minDistance) {
                        minDistance = dist;
                        closestV = v;
                    }
                });
                localEdges.push({
                    from: u.id,
                    to: closestV.id,
                    fromIdx: u.nodeIdx,
                    toIdx: closestV.nodeIdx
                });
            });

            nextLayer.forEach(v => {
                let closestU = currentLayer[0];
                let minDistance = Infinity;

                currentLayer.forEach(u => {
                    let dist = Math.abs(u.y - v.y);
                    if (dist < minDistance) {
                        minDistance = dist;
                        closestU = u;
                    }
                });

                if (!localEdges.some(e => e.from === closestU.id && e.to === v.id)) {
                    localEdges.push({
                        from: closestU.id,
                        to: v.id,
                        fromIdx: closestU.nodeIdx,
                        toIdx: v.nodeIdx
                    });
                }
            });

            currentLayer.forEach(u => {
                let connectedIndices = localEdges.filter(e => e.from === u.id).map(e => e.toIdx);
                let minTargetIdx = Math.min(...connectedIndices);
                let maxTargetIdx = Math.max(...connectedIndices);

                let candidateTargets = [];
                if (minTargetIdx > 0) candidateTargets.push(minTargetIdx - 1);
                if (maxTargetIdx < nextLayer.length - 1) candidateTargets.push(maxTargetIdx + 1);

                candidateTargets.forEach(targetIdx => {
                    if (myRandom() < 0.35) {
                        let v = nextLayer[targetIdx];

                        let crosses = localEdges.some(existingEdge => {
                            return (u.nodeIdx < existingEdge.fromIdx && targetIdx > existingEdge.toIdx) ||
                                (u.nodeIdx > existingEdge.fromIdx && targetIdx < existingEdge.toIdx);
                        });

                        if (!crosses) {
                            localEdges.push({
                                from: u.id,
                                to: v.id,
                                fromIdx: u.nodeIdx,
                                toIdx: v.nodeIdx
                            });
                        }
                    }
                });
            });

            localEdges.forEach(e => globalEdges.push(e));
        }

        // ==========================================================================
        // 🧠 DIJKSTRA-BASED DSS PATHFINDING AND AUTOMATED HIGHLIGHT ENGINE
        // ==========================================================================
        window.calculateDSSRoute = function(strategyType) {
            document.querySelectorAll('.rpg-node').forEach(el => {
                el.classList.remove('dss-highlight-safe', 'dss-highlight-greed', 'dss-highlight-optimal');
            });
            document.querySelectorAll('#links line').forEach(line => {
                line.setAttribute("stroke", "white");
                line.setAttribute("stroke-width", "4");
                line.setAttribute("stroke-dasharray", "10 12");
                line.removeAttribute("style");
            });

            console.log(`DSS Engine: Solving path parameters for [${strategyType}] strategy...`);

            let startNodeId = currentPlayerNodeId;
            if (!startNodeId && mapDataStructure[0] && mapDataStructure[0][0]) {
                startNodeId = mapDataStructure[0][0].id;
            }
            if (!startNodeId) return;

            let weights = {};
            if (strategyType === 'safe') {
                weights = { 'combat': 15, 'elite': 50, 'event': 2, 'shop': 1, 'start': 0, 'boss': 0 };
            } else if (strategyType === 'greed') {
                weights = { 'combat': 2, 'elite': 1, 'event': 25, 'shop': 8, 'start': 0, 'boss': 0 };
            } else if (strategyType === 'optimal') {
                const currentHP = <?= json_encode($current_hp) ?>;
                const maxHP = <?= json_encode($max_hp) ?>;
                const hpPercentage = currentHP / maxHP;

                if (hpPercentage < 0.40) {
                    console.log("DSS Warning: Low vital signs detected! Forcing defensive safe path.");
                    weights = { 'combat': 15, 'elite': 50, 'event': 2, 'shop': 1, 'start': 0, 'boss': 0 };
                } else {
                    console.log("DSS Health Factor: Robust state. Maximizing map yield density.");
                    weights = { 'combat': 5, 'elite': 2, 'event': 5, 'shop': 3, 'start': 0, 'boss': 0 };
                }
            }

            let allNodes = {};
            let targetBossNodeId = null;

            mapDataStructure.forEach(column => {
                column.forEach(node => {
                    allNodes[node.id] = {
                        id: node.id,
                        type: node.type,
                        weight: weights[node.type] ?? 5,
                        distance: Infinity,
                        parent: null
                    };
                    if (node.type === 'boss') {
                        targetBossNodeId = node.id;
                    }
                });
            });

            let queue = [];
            allNodes[startNodeId].distance = 0;
            queue.push(allNodes[startNodeId]);

            while (queue.length > 0) {
                queue.sort((a, b) => a.distance - b.distance);
                let current = queue.shift();

                if (current.id === targetBossNodeId) break;

                let neighbors = globalEdges.filter(edge => edge.from === current.id);
                neighbors.forEach(edge => {
                    let neighborNode = allNodes[edge.to];
                    if (neighborNode) {
                        let altDistance = current.distance + neighborNode.weight;
                        if (altDistance < neighborNode.distance) {
                            neighborNode.distance = altDistance;
                            neighborNode.parent = current.id;
                            if (!queue.some(q => q.id === neighborNode.id)) {
                                queue.push(neighborNode);
                            }
                        }
                    }
                });
            }

            let finalPathNodeIds = [];
            let currentTraceId = targetBossNodeId;

            while (currentTraceId !== null) {
                finalPathNodeIds.unshift(currentTraceId);
                currentTraceId = allNodes[currentTraceId] ? allNodes[currentTraceId].parent : null;
            }

            if (finalPathNodeIds[0] !== startNodeId) {
                console.error("DSS Error: No connected roadmap pathway resolved.");
                return;
            }

            finalPathNodeIds.forEach(nodeId => {
                const nodeElement = document.getElementById(nodeId);
                if (nodeElement) {
                    nodeElement.classList.add(`dss-highlight-${strategyType}`);
                }
            });

            for (let i = 0; i < finalPathNodeIds.length - 1; i++) {
                const src = finalPathNodeIds[i];
                const dest = finalPathNodeIds[i + 1];
                const line = document.querySelector(`#links line[data-from="${src}"][data-to="${dest}"]`);
                if (line) {
                    let strokeColor = strategyType === 'safe' ? '#2ecc71' : (strategyType === 'greed' ? '#f1c40f' : '#9b59b6');
                    line.setAttribute("stroke", strokeColor);
                    line.setAttribute("stroke-width", "8");
                    line.setAttribute("stroke-dasharray", "none");
                    line.style.filter = `drop-shadow(0px 0px 8px ${strokeColor})`;
                }
            }
        };

        // ==========================================================================
        // 🎨 RENDER MAP GRAPHICS WITH DECORATIONS (RUNS EXACTLY ONCE)
        // ==========================================================================
        const decorationConfig = {
            'bush.png': { scale: 0.2, opacity: 1.0, zIndex: 1, weight: 0.40, isGround: true },
            'tall_grass.png': { scale: 0.5, opacity: 0.85, zIndex: 1, weight: 0.35, isGround: true },
            'big_tree.png': { scale: 0.8, opacity: 1.0, zIndex: 1, weight: 0.18, isGround: true },
            'cloud_small.png': { scale: 0.8, opacity: 0.40, zIndex: 12, weight: 0.07, isGround: false }
        };

        const decorationTypes = Object.keys(decorationConfig);
        const totalDecorationsCount = 220;

        function getSeededAsset(randomRoll) {
            let cumulativeWeight = 0;
            for (const asset of decorationTypes) {
                cumulativeWeight += decorationConfig[asset].weight;
                if (randomRoll <= cumulativeWeight) return asset;
            }
            return decorationTypes[0];
        }

        for (let d = 0; d < totalDecorationsCount; d++) {
            const assetName = getSeededAsset(myRandom());
            const config = decorationConfig[assetName];

            const decX = Math.floor(myRandom() * (canvasWidth - 300)) + 150;
            const decY = Math.floor(myRandom() * (canvasHeight - 300)) + 150;

            if (config.isGround) {
                let overlapsActualNode = false;
                const nodeSafetyRadius = 130;

                for (let col = 0; col <= totalColumns; col++) {
                    if (!mapDataStructure[col]) continue;

                    for (let i = 0; i < mapDataStructure[col].length; i++) {
                        const targetNode = mapDataStructure[col][i];

                        const distX = decX - targetNode.x;
                        const distY = decY - targetNode.y;
                        const distance = Math.sqrt(distX * distX + distY * distY);

                        if (distance < nodeSafetyRadius) {
                            overlapsActualNode = true;
                            break;
                        }
                    }
                    if (overlapsActualNode) break;
                }

                if (overlapsActualNode) continue;
            }

            const decElement = document.createElement('img');
            decElement.src = `../asset/sprites/nodes/decorations/${assetName}`;
            decElement.className = 'map-decoration-prop';
            decElement.setAttribute("draggable", "false");

            decElement.style.cssText = `
                position: absolute;
                left: ${decX}px;
                top: ${decY}px;
                z-index: ${config.zIndex};
                opacity: ${config.opacity};
                pointer-events: none;
                user-select: none;
                image-rendering: pixelated;
                transform: scale(${config.scale});
                transform-origin: bottom center;
            `;

            mapContainer.appendChild(decElement);
        }

        for (let col = 0; col <= totalColumns; col++) {
            mapDataStructure[col].forEach(node => {
                const nodeAnchor = document.createElement('a');
                nodeAnchor.id = node.id;
                nodeAnchor.className = `rpg-node rpg-node-${node.type}`;
                nodeAnchor.style.position = 'absolute';
                nodeAnchor.style.left = `${node.x}px`;
                nodeAnchor.style.top = `${node.y}px`;
                nodeAnchor.style.zIndex = "10";

                const labelSpan = document.createElement('span');
                labelSpan.className = 'node-title-label';
                labelSpan.textContent = node.label;
                labelSpan.style.cssText = "position: absolute; bottom: -35px; left: 50%; transform: translateX(-50%); white-space: nowrap; color: #ffffff; text-shadow: 2px 2px 0px #000; font-weight: bold; font-size: 14px; pointer-events: none; z-index: 100;";

                nodeAnchor.appendChild(labelSpan);
                mapContainer.appendChild(nodeAnchor);
            });
        }

        let accessibleTargets = [];
        if (currentPlayerNodeId) {
            globalEdges.forEach(edge => {
                if (edge.from === currentPlayerNodeId) accessibleTargets.push(edge.to);
            });
        } else {
            accessibleTargets = [mapDataStructure[0][0].id];
        }

        const nodeRoutes = {
            "rpg-node-combat": `in-combat/index.php?p=combat_level1&id=${playerId}`,
            "rpg-node-shop": `index.php?p=shop1&id=${playerId}`,
            "rpg-node-event": `index.php?p=event1&id=${playerId}`,
            "rpg-node-elite": `in-combat/index.php?p=elite1&id=${playerId}`,
            "rpg-node-boss": `in-combat/boss/boss_index.php?p=boss_level1&id=${playerId}&boss_id=1`
        };

        const domNodes = document.querySelectorAll('.rpg-node');
        domNodes.forEach(node => {
            node.setAttribute("draggable", "false");

            if (node.id === currentPlayerNodeId) {
                node.classList.add("rpg-node-current");
                return;
            }

            if (accessibleTargets.includes(node.id)) {
                node.classList.add("rpg-node-next");
                node.addEventListener("click", function(e) {
                    e.preventDefault();
                    
                    // --- 🎵 NEW: INTERCEPT NODE CLICK TO UNCAGE BATTLE BGM INSTANTLY ---
                    if (node.classList.contains('rpg-node-combat') || node.classList.contains('rpg-node-elite') || node.classList.contains('rpg-node-boss')) {
                        if (window.mapSFX && window.mapSFX.select) window.mapSFX.select.play();
                        if (window.battleAudioInstance) {
                            window.battleAudioInstance.play().catch(err => {
                                console.log("Map Audio Matrix: Stream uncage failed", err);
                            });
                        }
                    }

                    let destinationUrl = "#";
                    for (const [nodeClass, url] of Object.entries(nodeRoutes)) {
                        if (node.classList.contains(nodeClass)) {
                            destinationUrl = url;
                            break;
                        }
                    }

                    if (node.id === mapDataStructure[0][0].id && !currentPlayerNodeId) {
                        const formData = new FormData();
                        formData.append("player_id", playerId);
                        fetch("pages/processes/start_run.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) window.location.reload();
                            });
                        return;
                    }

                    if (destinationUrl !== "#") {
                        const nodeUpdateData = new FormData();
                        nodeUpdateData.append("player_id", playerId);
                        nodeUpdateData.append("node_id", node.id);
                        fetch("pages/processes/update_node.php", {
                                method: "POST",
                                body: nodeUpdateData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) window.location.href = destinationUrl;
                            });
                    }
                });
                return;
            }

            if (node.id !== mapDataStructure[0][0].id || currentPlayerNodeId !== null) {
                node.classList.add("rpg-node-locked");
            }
        });

        if (currentPlayerNodeId) {
            const activeNodeElement = document.getElementById(currentPlayerNodeId);
            if (activeNodeElement) {
                activeNodeElement.classList.add("player-pointer");
                activeNodeElement.setAttribute("data-class", characterClass.toLowerCase());
            }
        }

        svgContainer.innerHTML = "";
        svgContainer.setAttribute("width", canvasWidth);
        svgContainer.setAttribute("height", canvasHeight);

        globalEdges.forEach(edge => {
            const fromNodeEl = document.getElementById(edge.from);
            const toNodeEl = document.getElementById(edge.to);

            if (fromNodeEl && toNodeEl) {
                const startX = parseInt(fromNodeEl.style.left) + (fromNodeEl.offsetWidth / 2 || 60);
                const startY = parseInt(fromNodeEl.style.top) + (fromNodeEl.offsetHeight / 2 || 60);
                const endX = parseInt(toNodeEl.style.left) + (toNodeEl.offsetWidth / 2 || 60);
                const endY = parseInt(toNodeEl.style.top) + (toNodeEl.offsetHeight / 2 || 60);

                const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                line.setAttribute("x1", startX);
                line.setAttribute("y1", startY);
                line.setAttribute("x2", endX);
                line.setAttribute("y2", endY);
                line.setAttribute("stroke", "white");
                line.setAttribute("stroke-width", "4");
                line.setAttribute("stroke-dasharray", "10 12");
                line.setAttribute("data-from", edge.from);
                line.setAttribute("data-to", edge.to);
                svgContainer.appendChild(line);
            }
        });

        // ==========================================================================
        // 🎥 SYNCED CAMERA TRANSFORM ENGINE
        // ==========================================================================
        window.autoCenterCameraOnActiveNode = function() {
            let targetNodeElement = null;

            if (currentPlayerNodeId) {
                targetNodeElement = document.getElementById(currentPlayerNodeId);
            } else if (mapDataStructure[0] && mapDataStructure[0][0]) {
                targetNodeElement = document.getElementById(mapDataStructure[0][0].id);
            }

            if (!viewport || !mapContainer || !targetNodeElement) return;

            const nodeX = parseInt(targetNodeElement.style.left) || 0;
            const nodeY = parseInt(targetNodeElement.style.top) || 0;

            const viewportWidth = viewport.clientWidth;
            const viewportHeight = viewport.clientHeight;

            const nodeWidth = targetNodeElement.offsetWidth || 60;
            const nodeHeight = targetNodeElement.offsetHeight || 60;

            window.mapTranslateX = (viewportWidth / 2) - (nodeX + (nodeWidth / 2)) * window.mapScale;
            window.mapTranslateY = (viewportHeight / 2) - (nodeY + (nodeHeight / 2)) * window.mapScale;

            mapContainer.style.transition = "transform 0.5s ease-out";
            mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;

            setTimeout(() => {
                if (mapContainer) mapContainer.style.transition = "none";
            }, 500);
        };

        setTimeout(window.autoCenterCameraOnActiveNode, 200);

        // ==========================================================================
        // 🫳 UNIFIED CANVAS INTERACTIVE DRAG MATRIX
        // ==========================================================================
        if (viewport && mapContainer) {
            viewport.addEventListener('mousedown', (e) => {
                isDragging = true;
                mapContainer.style.transition = "none";

                dragStartX = e.clientX - window.mapTranslateX;
                dragStartY = e.clientY - window.mapTranslateY;
                mapContainer.style.cursor = 'grabbing';
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                window.mapTranslateX = e.clientX - dragStartX;
                window.mapTranslateY = e.clientY - dragStartY;
                mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;
            });

            document.addEventListener('mouseup', () => {
                isDragging = false;
                mapContainer.style.cursor = 'grab';
            });
        }

        const zoomInBtn = document.getElementById('zoomIn');
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', () => {
                window.mapScale += 0.1;
                mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;
            });
        }

        const zoomOutBtn = document.getElementById('zoomOut');
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', () => {
                window.mapScale -= 0.1;
                if (window.mapScale < 0.2) window.mapScale = 0.2;
                mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;
            });
        }

        const centerMapBtn = document.getElementById('centerMap');
        if (centerMapBtn) {
            centerMapBtn.addEventListener('click', () => {
                if (typeof window.autoCenterCameraOnActiveNode === 'function') {
                    window.autoCenterCameraOnActiveNode();
                }
            });
        }
    });
</script>