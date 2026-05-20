<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/../conn.php';

$id = $_GET['id'];

$query = "SELECT * FROM player WHERE player_id = '$id'";
$sql = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($sql);
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/bootstrap.css">
    <title>Document</title>
</head>

<body>
    <nav class="bg-dark text-white position-fixed p-4"
        style="height: 93vh; width: 250px;">

        <h1 class="h5 text-center">Equipments</h1>

        <div class="container-fluid mt-4">

            <div class="row justify-content-center text-center">

                <div class="col-6 d-flex justify-content-center">
                    <div class="card d-flex flex-column justify-content-end"
                        style="height: 80px; width: 90px;">

                        <p class="m-0 text-center text-dark">
                            Helmet
                        </p>

                    </div>
                </div>

                <div class="col-6 d-flex justify-content-center">
                    <div class="card d-flex flex-column justify-content-end"
                        style="height: 80px; width: 90px;">

                        <p class="m-0 text-center text-dark">
                            Armor
                        </p>

                    </div>
                </div>

            </div>

            <div class="row justify-content-center text-center mt-2">

                <div class="col-6 d-flex justify-content-center">
                    <div class="card d-flex flex-column justify-content-end"
                        style="height: 80px; width: 90px;">

                        <p class="m-0 text-center text-dark">
                            Boots
                        </p>

                    </div>
                </div>

                <div class="col-6 d-flex justify-content-center">
                    <div class="card d-flex flex-column justify-content-end"
                        style="height: 80px; width: 90px;">

                        <p class="m-0 text-center text-dark">
                            Accessory
                        </p>

                    </div>
                </div>

            </div>

        </div>

    </nav>
    <div class="container-lg" style="margin-top: 0; margin-left: 360px; max-width: 1537px;">
        <div class="row" style="background-color: blue; max-width: 100%; height: 60px;">
            <h1>Dungeon 1 - Sewer</h1>
            <!-- Placeholder -->
        </div>
        <div class="row">
            <div class="col-12">
                <?php
                if (!empty($content)) {
                    include $content;
                }
                ?>
            </div>
        </div>
    </div>
    </div>
</body>

</html>