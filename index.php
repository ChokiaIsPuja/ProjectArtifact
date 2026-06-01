<?php
include __DIR__ . '/conn.php';
session_start();
if ($_SESSION['username'] != true) {
    header("location:login.php?p=notloggedin");
}

$page = isset($_GET['p']) ? $_GET['p'] : 'home';

// 1. ROUTE CONTROL: If JavaScript is checking out, load the page logic and STOP immediately
if (isset($_GET['action']) && $_GET['action'] === 'checkout') {
    // Dynamically include your shop file based on your routing style
    if (file_exists("pages/$page.php")) { 
        include "pages/$page.php";
    }
    exit; // Hard cutoff so NO HTML structure below ever prints!
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="asset/css/preloader.css">
    <title>Ro-Golike</title>
    <style>
        body {
            background-image: url('asset/img/background/Illustration12.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-color: #6D5F5F;
        }

        .card[data-class-id] {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card[data-class-id].selected-card {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.35);
        }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100">
<div id="preloader">
  <img src="asset/img/loading.png" alt="Loading..." class="preloader-image">
</div>

        <div class="container text-center" style="border:none;">
            <h2 style="color: #fff;">Welcome, <?php echo $_SESSION['username']; ?>!</h2>
            <div class="row">
                <div class="col-12">
                    <h1 class="text-white" style="font-size: 50px;">ProjectArtifact</h1>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-3">
                    <button type="button" onfocus="this.style.boxShadow='none';" class="btn btn-primary" id="loadGameButton" data-toggle="modal" data-target="#loadModal" style="width: 400px; background-color:#FAC79B; border: none; height: 50px; color: #000;">
                        Begin / Continue
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-3">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal" style="width: 400px; background-color:#FAC79B; border: none; height: 50px; color: #000;" id="newGameButton">
                        Create Character
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-3">
                    <a href="how_to_play.php"><button class="btn btn-primary" onfocus="this.style.boxShadow='none';" style="width: 400px; background-color:#FAC79B; border: none; height: 50px; color: #000;" id="howToPlayButton">How To Play</button></a>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-3">
                    <a href="https://github.com/ChokiaIsPuja/ProjectArtifact"><button class="btn btn-primary" onfocus="this.style.boxShadow='none';" style="width: 400px; background-color:#FAC79B; border: none; height: 50px; color: #000;" id="creditsButton">Credits</button></a>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-3">
                    <a href="logout.php" class="btn btn-danger" style="width: 400px;">Logout</a>
                </div>
            </div>
        <footer class="mt-auto">
            <div class="row mt-5">
                <div class="col-12">
                    <p style="color: #fff;">
                        &copy; 2026 ProjectArtifact by Chokia.
                        All rights reserved.
                    </p>
                    <p style="color: white;">pre-pre-pre-alpha v0.0.1</p>
                </div>
            </div>
        </footer>
        <?php
        $classQuery = "SELECT * FROM `class`";
        $classResult = mysqli_query($conn, $classQuery);
        if (!$classResult) {
            $classQuery = "SELECT * FROM `classes`";
            $classResult = mysqli_query($conn, $classQuery);
        }
        $classes = [];
        $classIdField = 'id';
        $classNameField = 'name';
        if ($classResult && mysqli_num_rows($classResult) > 0) {
            while ($classRow = mysqli_fetch_assoc($classResult)) {
                if (empty($classes)) {
                    if (isset($classRow['id_class'])) {
                        $classIdField = 'id_class';
                    } elseif (isset($classRow['class_id'])) {
                        $classIdField = 'class_id';
                    } elseif (isset($classRow['id'])) {
                        $classIdField = 'id';
                    }

                    if (isset($classRow['class_name'])) {
                        $classNameField = 'class_name';
                    } elseif (isset($classRow['name'])) {
                        $classNameField = 'name';
                    }
                }
                $classes[] = $classRow;
            }
        }
        $cardColors = ['danger', 'success', 'primary', 'warning', 'info', 'secondary'];

        function getClassImageUrl($classRow, $classNameField)
        {
            $avatar = trim($classRow['avatar'] ?? '');
            if ($avatar !== '') {
                if (preg_match('/^(https?:\/\/|data:)/i', $avatar)) {
                    return $avatar;
                }
                if (strpos($avatar, '/') !== false) {
                    return ltrim($avatar, '/');
                }
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($classRow[$classNameField] ?? '')));
                return 'asset/sprites/classes/' . $slug . '/' . basename($avatar);
            }
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($classRow[$classNameField] ?? '')));
            $dir = __DIR__ . '/asset/sprites/classes/' . $slug;
            if (is_dir($dir)) {
                $images = glob($dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                if (!empty($images)) {
                    return 'asset/sprites/classes/' . $slug . '/' . basename($images[0]);
                }
            }
            return '';
        }
        ?>
        <!-- Modal Start Game -->
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true"
            data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content" style="background-color: #D39670;">
                    <form action="save-character.php" method="post">
                        <input type="hidden" name="aksi" value="add">
                        <?php
                        $query = "SELECT * FROM user WHERE username='" . $_SESSION['username'] . "'";

                        $sql = mysqli_query($conn, $query);
                        $row = mysqli_fetch_assoc($sql);
                        ?>

                        <input type="hidden" name="id_user" value="<?php echo $row['id_user']; ?>">
                        <div class="modal-header d-flex justify-content-center align-items-center position-relative" style="background-color: #b45b5b; border-bottom: none;">
                            <h5 class="modal-title m-0" id="exampleModalLabel" style="color: white;">Select Class</h5>

                            <button type="button" class="close position-absolute" data-dismiss="modal" aria-label="Close" style="right: 15px; top: 50%; transform: translateY(-50%); color: white; opacity: 0.8;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row justify-content-center">
                                <?php if (!empty($classes)) : ?>
                                    <?php foreach ($classes as $index => $classRow) : ?>
                                        <?php
                                        $color = "#FAC79B";
                                        $classImage = getClassImageUrl($classRow, $classNameField);
                                        ?>
                                        <div class="col-12 col-md-4 mb-3">
                                            <div class="card mb-3 mx-auto w-100" data-class-id="<?= htmlspecialchars($classRow[$classIdField]) ?>" role="button" tabindex="0" style="background-color: <?= htmlspecialchars($color) ?>; color: #000;">
                                                <?php if ($classImage) : ?>
                                                    <img src="<?= htmlspecialchars($classImage) ?>" class="card-img-top" alt="<?= htmlspecialchars($classRow[$classNameField]) ?>">
                                                <?php else : ?>
                                                    <div class="card-img-top bg-secondary text-white d-flex align-items-center justify-content-center" style="height: 180px;">
                                                        <span>No image</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="card-header text-center" style="background-color: rgb(226, 73, 73); border-bottom: none; color: #ffffff;">
                                                    <?= htmlspecialchars($classRow[$classNameField]) ?>
                                                </div>
                                                <div class="card-body text-center">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="col-12">
                                        <div class="alert alert-warning">No class data found. Please add classes to the database.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group mt-3 text-center w-75 mx-auto">
                                <label for="characterName" style="color: #ffffff;">Character Name</label>
                                <input type="text" class="form-control" id="characterName" name="name" placeholder="Enter character name">
                                <input type="hidden" id="classId" name="class_id" value="">
                            </div>
                        </div>
                        <div class="modal-footer" style="text-align: center; justify-content: center; background-color: #b45b5b; color: #ffffff; border-top: none;">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Back</button>
                            <button type="submit" class="btn btn-primary" id="beginButton">Begin</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Modal Load Game -->
        <div class="modal fade" id="loadModal" tabindex="-1" aria-labelledby="loadModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header justify-content-center"
                        style="background-color: #b45b5b; color: #ffffff; border-bottom: none;">

                        <h5 class="modal-title w-100 text-center m-0"
                            id="loadModalLabel">
                            Select Character
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true" style="color: #fff;">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body modal-dialog-scrollable" style="background-color: #D39670;">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card mb-3" style="border:none">
                                        <div class="card-body" style="background-color: #D39670; border:none;">
                                            <?php
                                            $query1 = "SELECT * FROM user
                                             WHERE username = '" . $_SESSION['username'] . "'";

                                            $result1 = mysqli_query($conn, $query1);

                                            $row = mysqli_fetch_assoc($result1);

                                            $id_user = $row['id_user'];


                                            // echo "<p class='card-text'>User ID: " . htmlspecialchars($id_user) . "</p>";
                                            $query2 = $query2 = "SELECT player.*, class.class_name, class.avatar
                                            FROM player
                                            LEFT JOIN class
                                            ON player.class_id = class.class_id
                                            WHERE player.id_user = '$id_user'";

                                            $result2 = mysqli_query($conn, $query2);
                                            ?>

                                            <?php if (mysqli_num_rows($result2) > 0): ?>
                                                <?php while ($row1 = mysqli_fetch_assoc($result2)): ?>

                                                <div class="row align-items-center" style="margin-top: 1vh;">
                                                    <div class="col-12">
                                                        <div class="card" style="background-color: #D39670; border: 2px solid #b45b5b; border-radius: 10px;">
                                                            <div class="card-body" style="background-color: #FAC79B;">
                                                                <h5 class="card-title" style="text-align: left;background-color: #b45b5b; padding: 10px; border-radius: 2px; color: #ffffff;">
                                                                    <?= htmlspecialchars($row1['name']) ?>
                                                                </h5>

                                                                <div class="row">
                                                                    <div class="col-3">
                                                                        <div class="card mb-2" style="max-width: 220px; border: 3px solid #b45b5b; border-radius: none;">
                                                                            <img src="asset/sprites/classes/<?= htmlspecialchars($row1['avatar']) ?>" alt="<?= htmlspecialchars($row1['class_name']) ?>" class="img-fluid d-block mx-auto" style="max-height: 180px; width: auto; object-fit: contain;">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-5">
                                                                        <p class="card-text" style="text-align: left;">
                                                                            Class: <?= htmlspecialchars($row1['class_name']) ?><br>
                                                                            Level: <?= htmlspecialchars($row1['level']) ?><br>
                                                                            Gold: <?= htmlspecialchars($row1['gold']) ?><br>
                                                                        </p>
                                                                    </div>
                                                                    <div class="col-4 d-flex align-items-center justify-content-center">
                                                                        <?php if ($row1['level'] > 1): ?>
                                                                            <a href="in-game/index.php?p=level1&id=<?= $row1['player_id'] ?>"
                                                                                class="btn btn-primary d-flex align-items-center justify-content-center w-75"
                                                                                style="height: 50px; line-height: 50px; background-color: #b45b5b; border: none; padding: 0;">
                                                                                <span class="m-0">Continue</span>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <a href="in-game/index.php?p=level1&id=<?= $row1['player_id'] ?>"
                                                                                class="btn btn-primary d-flex align-items-center justify-content-center w-75"
                                                                                style="height: 50px; line-height: 50px; background-color: #b45b5b; border: none; padding: 0;">
                                                                                <span class="m-0">Start</span>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                </div>






                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <div class="row" style="margin-top: 2vh;">
                                                    <div class="col-12">
                                                        <div class="alert alert-info text-center" role="alert" style="background-color: #FAC79B; border: 1px solid #b45b5b; color: #000;">
                                                            No characters found. Use "Create Character" to make your first character.
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center"
                        style="background-color: #b45b5b; color: #ffffff; border-top: none;">

                        <button type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal" style="width: 500px;height: 50px;background-color: #f07a7a; border: none; color: #ffffff;">
                            Cancel
                        </button>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="asset/js/jquery-3.7.1.js"></script>
    <script src="asset/js/bootstrap.bundle.min.js"></script>
    <script>
        $(function() {
            // Card selection
            $('.card[data-class-id]').on('click keypress', function(e) {
                if (e.type === 'keypress' && e.key !== 'Enter' && e.key !== ' ') return;
                $('.card[data-class-id]').removeClass('border-3 border-warning selected-card');
                $(this).addClass('border-3 border-warning selected-card');
                var clsId = $(this).data('class-id');
                $('#classId').val(clsId);
                // enable Begin if name present
                var name = $('#characterName').val().trim();
                $('#beginButton').prop('disabled', !(name && clsId));
            });

            // Enable Begin when name input and class selected
            $('#characterName').on('input', function() {
                var name = $(this).val().trim();
                var clsId = $('#classId').val();
                $('#beginButton').prop('disabled', !(name && clsId));
            });

            // Begin is now a native form submit; validation will prevent submit when missing
            // Keep simple client-side validation on form submit
            $('form').on('submit', function(e) {
                var name = $('#characterName').val().trim();
                var clsId = $('#classId').val();
                if (!clsId) {
                    e.preventDefault();
                    alert('Please select a class.');
                    return false;
                }
                if (!name) {
                    e.preventDefault();
                    alert('Please enter a character name.');
                    return false;
                }
                // allow form to submit normally
            });

            // initialize
            $('#beginButton').prop('disabled', true);
        });
        function startPreloaderExit() {
            $('#preloader').addClass('loaded');
            $('body').addClass('page-ready');
            $('#preloader').one('animationend', function(e) {
                if (e.originalEvent.animationName === 'slideUp') {
                    $(this).remove();
                }
            });
        }

        var preloaderPageReady = false;
        var preloaderDownDone = false;

        function checkPreloaderExit() {
            if (preloaderPageReady && preloaderDownDone) {
                setTimeout(startPreloaderExit, 400);
            }
        }

        setTimeout(function() {
            preloaderDownDone = true;
            checkPreloaderExit();
        }, 700);

        window.addEventListener('load', function() {
            preloaderPageReady = true;
            checkPreloaderExit();
        });

    </script>
    
</body>

</html>