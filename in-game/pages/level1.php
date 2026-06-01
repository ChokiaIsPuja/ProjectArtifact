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

    // 2. Fetch active run tracking position
    $runQuery = "SELECT current_node FROM runs WHERE player_id = ? ORDER BY started_at DESC LIMIT 1";
    $runStmt = mysqli_prepare($conn, $runQuery);
    if ($runStmt) {
        mysqli_stmt_bind_param($runStmt, "i", $id);
        mysqli_stmt_execute($runStmt);
        $runResult = mysqli_stmt_get_result($runStmt);
        if ($runData = mysqli_fetch_assoc($runResult)) {
            $currentNode = $runData['current_node'];
        }
        mysqli_stmt_close($runStmt);
    }
}
?>

<div class="map-area">
    <div class="row" style="position:relative; z-index:10;">
        <div class="col-12">
            <h2 class="text-dark" style="margin:20px; position:absolute;">
                Dungeon I - Plains
            </h2>
            <hr style="
                position:absolute; top:70px; left:38px; width:1488px; height:2px;
                background-color:white; border:none; margin:0;
            ">
        </div>
    </div>

    <div class="viewport">
        <div class="map-controls">
            <button id="zoomIn">+</button>
            <button id="zoomOut">-</button>
            <button id="centerMap">Center</button>
        </div>

        <div class="map" id="map" style="position: relative; width: 5000px; height: 2500px;">
            <svg id="links" class="links"></svg>

            <a class="rpg-node rpg-node-start" style="top:1000px; left:800px;" id="node1">Start</a>
            <a class="rpg-node rpg-node-combat" style="top:1100px; left:1050px;" id="node2">Combat</a>
            <a class="rpg-node rpg-node-event" style="top:900px; left:1050px;" id="node3">Event</a>
            <a class="rpg-node rpg-node-combat" style="top:1000px; left:1250px;" id="node4">Combat</a>
            <a class="rpg-node rpg-node-shop" style="top:800px; left:1450px;" id="node5">Shop</a>
            <a class="rpg-node rpg-node-combat" style="top:1150px; left:1450px;" id="node6">Combat</a>

            <a class="rpg-node rpg-node-event" style="top:700px; left:1650px;" id="node7">Event</a>
            <a class="rpg-node rpg-node-combat" style="top:900px; left:1650px;" id="node8">Combat</a>
            <a class="rpg-node rpg-node-combat" style="top:1100px; left:1650px;" id="node9">Combat</a>

            <a class="rpg-node rpg-node-shop" style="top:750px; left:1900px;" id="node10">Shop</a>
            <a class="rpg-node rpg-node-event" style="top:1000px; left:1900px;" id="node11">Event</a>
            <a class="rpg-node rpg-node-combat" style="top:1250px; left:1900px;" id="node12">Combat</a>

            <a class="rpg-node rpg-node-combat" style="top:800px; left:2150px;" id="node13">Combat</a>
            <a class="rpg-node rpg-node-event" style="top:1050px; left:2150px;" id="node14">Event</a>
            <a class="rpg-node rpg-node-combat" style="top:1300px; left:2150px;" id="node15">Combat</a>

            <a class="rpg-node rpg-node-combat" style="top:900px; left:2400px;" id="node16">Elite Prep</a>
            <a class="rpg-node rpg-node-shop" style="top:1100px; left:2400px;" id="node17">Shop</a>

            <a class="rpg-node rpg-node-event" style="top:800px; left:2650px;" id="node18">Event</a>
            <a class="rpg-node rpg-node-combat" style="top:1000px; left:2650px;" id="node19">Combat</a>
            <a class="rpg-node rpg-node-combat" style="top:1200px; left:2650px;" id="node20">Combat</a>

            <a class="rpg-node rpg-node-combat" style="top:850px; left:3000px;" id="node23">Elite+</a>
            <a class="rpg-node rpg-node-event" style="top:1150px; left:3000px;" id="node24">Event</a>
            <a class="rpg-node rpg-node-shop" style="top:1000px; left:3050px;" id="node25">Final Shop</a>

            <a class="rpg-node rpg-node-event" style="top:1000px; left:3300px;" id="node28">Memory</a>
            <a class="rpg-node rpg-node-elite" style="top:850px; left:3400px;" id="node29">Final Elite</a>
            <a class="rpg-node rpg-node-combat" style="top:1150px; left:3400px;" id="node30">Execution</a>
            <a class="rpg-node rpg-node-event" style="top:1000px; left:3500px;" id="node31">Last Event</a>
            <a class="rpg-node rpg-node-boss" style="top:1000px; left:3700px;" id="node32">BOSS</a>

            <img src="../asset/sprites/nodes/decorations/alteria_shop.png" alt="" style="position: absolute; top:700px; left:1200px">

            <script>
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Map Engine Initializing...");

        const mapContainer = document.getElementById('map');
        if (!mapContainer) return;

        // Parse Backend States
        const playerId = <?= json_encode((int)$id) ?>;
        const currentPlayerNodeId = <?= json_encode($currentNode) ?>;
        const characterClass = <?= json_encode($playerJob) ?>;

        console.log(`State Loaded -> Player: ${playerId}, ActiveNode: ${currentPlayerNodeId}, Class: ${characterClass}`);

        const nodes = document.querySelectorAll('.rpg-node');

        // ==========================================================================
        // 🧠 SVG LINE-BASED ADJACENCY CALCULATOR (Bug Free & Layout Accurate!)
        // ==========================================================================
        let accessibleTargets = [];

        if (currentPlayerNodeId) {
            // Find all SVG lines or path links drawn on the canvas
            const mapLines = document.querySelectorAll('#links line, #links path, .links line, svg line');

            mapLines.forEach(line => {
                // Method 1: Check for explicit connection data attributes
                const sourceNode = line.getAttribute('data-from') || line.id?.split('-')[0];
                const targetNode = line.getAttribute('data-to') || line.id?.split('-')[1];

                if (sourceNode === currentPlayerNodeId && targetNode) {
                    accessibleTargets.push(targetNode);
                }
            });

            // --- 💡 CRITICAL FALLBACK ---
            // If your lines are drawn with raw coordinates (x1, y1) without data tags,
            // we calculate proximity to the center of your nodes (assuming 120x120px node size).
            if (accessibleTargets.length === 0) {
                const currentNodeElement = document.getElementById(currentPlayerNodeId);
                if (currentNodeElement) {
                    const currentLeft = parseInt(currentNodeElement.style.left) + 60; // Node center X
                    const currentTop = parseInt(currentNodeElement.style.top) + 60; // Node center Y

                    mapLines.forEach(line => {
                        const x1 = parseInt(line.getAttribute('x1'));
                        const y1 = parseInt(line.getAttribute('y1'));
                        const x2 = parseInt(line.getAttribute('x2'));
                        const y2 = parseInt(line.getAttribute('y2'));

                        // Does this line start close to our current node center?
                        const startsAtCurrent = Math.abs(x1 - currentLeft) < 15 && Math.abs(y1 - currentTop) < 15;

                        if (startsAtCurrent) {
                            // Find which node sits near the destination tip of this line (x2, y2)
                            nodes.forEach(targetNode => {
                                const tLeft = parseInt(targetNode.style.left) + 60;
                                const tTop = parseInt(targetNode.style.top) + 60;

                                if (Math.abs(x2 - tLeft) < 15 && Math.abs(y2 - tTop) < 15) {
                                    accessibleTargets.push(targetNode.id);
                                }
                            });
                        }
                    });
                }
            }
        } else {
            // Safe fallback: If no active database run is detected, open the Start node.
            accessibleTargets = ["node1"];
        }

        console.log("Calculated Accessible Targets:", accessibleTargets);

        // ==========================================================================
        // ⚙️ NODE ROUTING AND CLICK HANDLER ASSIGNMENT
        // ==========================================================================
        const nodeRoutes = {
            "rpg-node-combat": `in-combat/index.php?p=combat_level1&id=${playerId}`,
            "rpg-node-shop": `index.php?p=shop1&id=${playerId}`,
            "rpg-node-event": `index.php?p=event1&id=${playerId}`,
            "rpg-node-elite": `in-combat/index.php?p=elite1&id=${playerId}`,
            "rpg-node-boss": `in-combat/index.php?p=boss1&id=${playerId}`
        };

        nodes.forEach(node => {
            node.setAttribute("draggable", "false");
            node.removeAttribute('href'); // Intercept standard anchor jumps

            // CASE A: Current Location
            if (node.id === currentPlayerNodeId) {
                node.classList.add("rpg-node-current");
                return;
            }

            // CASE B: Unlocked / Accessible Path Option
            if (accessibleTargets.includes(node.id)) {
                node.classList.add("rpg-node-next");

                node.addEventListener("click", function(e) {
                    e.preventDefault();

                    // Identify routing layout target
                    let destinationUrl = "#";
                    for (const [nodeClass, url] of Object.entries(nodeRoutes)) {
                        if (node.classList.contains(nodeClass)) {
                            destinationUrl = url;
                            break;
                        }
                    }

                    // Special case: Unlocking the initial start node run
                    if (node.id === "node1" && !currentPlayerNodeId) {
                        console.log("Initializing active session run via fetch payload...");
                        const formData = new FormData();
                        formData.append("player_id", playerId);

                        fetch("pages/processes/start_run.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    window.location.reload();
                                } else {
                                    alert("System Error: " + data.message);
                                }
                            });
                        return;
                    }

                    // Standard Node Progression: Update Database position tracking, then forward player route
                    if (destinationUrl !== "#") {
                        console.log(`Synchronizing state. Saving movement to: ${node.id}`);

                        // Package data to send over to PHP
                        const nodeUpdateData = new FormData();
                        nodeUpdateData.append("player_id", playerId);
                        nodeUpdateData.append("node_id", node.id); 

                        // Send async post request to database handler script
                        fetch("pages/processes/update_node.php", {
                                method: "POST",
                                body: nodeUpdateData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    console.log("Position saved cleanly. Moving player scene window...");
                                    window.location.href = destinationUrl;
                                } else {
                                    console.error("Database rejected save configuration: ", data.message);
                                    alert("Save Failure: " + data.message);
                                }
                            })
                            .catch(err => {
                                console.error("Network communication loss error context: ", err);
                                alert("Failed to connect to server. Game progress could not be saved.");
                            });
                    } else {
                        console.warn(`Routing class mismatch for node: ${node.id}. Classes: ${node.className}`);
                    }
                });
                return;
            }

            // CASE C: Locked behind Fog of War (Unreachable)
            if (node.id !== "node1" || currentPlayerNodeId !== null) {
                node.classList.add("rpg-node-locked");
            }
        });

        // ==========================================================================
        // 📍 CHARACTER CLASS SELECTOR COURIER
        // ==========================================================================
        if (currentPlayerNodeId) {
            const activeNodeElement = document.getElementById(currentPlayerNodeId);
            if (activeNodeElement) {
                activeNodeElement.classList.add("player-pointer");
                activeNodeElement.setAttribute("data-class", characterClass.toLowerCase());
            }
        }

        // ==========================================================================
        // ☁️ BACKGROUND ENVIRONMENTAL GENERATION DECORATIONS
        // ==========================================================================
        const assets = {
            clouds: ['../asset/sprites/nodes/decorations/cloud_small.png'],
            foliage: [
                '../asset/sprites/nodes/decorations/big_tree.png',
                '../asset/sprites/nodes/decorations/bush.png',
                '../asset/sprites/nodes/decorations/tall_grass.png'
            ]
        };

        function getRandom(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }

        const nodePositions = Array.from(nodes).map(n => ({
            x: parseInt(n.style.left) || 0,
            y: parseInt(n.style.top) || 0
        }));

        function isTooCloseToNodes(x, y, minDistance) {
            for (let n of nodePositions) {
                const dx = x - n.x;
                const dy = y - n.y;
                if (Math.sqrt(dx * dx + dy * dy) < minDistance) return true;
            }
            return false;
        }

        // Spawn Ambient Clouds
        for (let c = 0; c < 30; c++) {
            const left = getRandom(50, 4850);
            const top = getRandom(50, 700);
            const cloud = document.createElement('img');
            cloud.src = assets.clouds[0];
            cloud.className = 'decoration generated-cloud';
            const size = getRandom(160, 320);
            const opacity = getRandom(60, 85) / 100;
            cloud.style.cssText = `position: absolute; left: ${left}px; top: ${top}px; width: ${size}px; height: auto; opacity: ${opacity}; z-index: 1; image-rendering: pixelated; pointer-events: none;`;
            mapContainer.appendChild(cloud);
        }

        // Spawn Ambient Ground Decoration Items
        let foliageSpawned = 0;
        let safetyCount = 0;
        while (foliageSpawned < 150 && safetyCount < 1000) {
            safetyCount++;
            const left = getRandom(50, 4900);
            const top = getRandom(650, 2200);

            if (isTooCloseToNodes(left, top, 140)) continue;

            const item = document.createElement('img');
            const res = assets.foliage[Math.floor(Math.random() * assets.foliage.length)];
            item.src = res;
            item.className = 'decoration generated-foliage';

            let maxS = 60;
            if (res.includes('big_tree')) maxS = getRandom(160, 240);
            else if (res.includes('bush')) maxS = getRandom(65, 90);
            else if (res.includes('tall_grass')) maxS = getRandom(40, 55);

            const zIndex = Math.floor(top / 10);
            item.style.cssText = `position: absolute; left: ${left}px; top: ${top}px; width: 100%; max-width: ${maxS}px; height: auto; display: block; z-index: ${zIndex}; image-rendering: pixelated; pointer-events: none;`;
            mapContainer.appendChild(item);
            foliageSpawned++;
        }
    });
</script>
        </div>
    </div>
</div>