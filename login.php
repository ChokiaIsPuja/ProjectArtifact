<?php include __DIR__ . '/conn.php';


$query = "SELECT * FROM user";
$sql = mysqli_query($conn, $query);
$row = mysqli_fetch_array($sql);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/bootstrap.min.css">
    <title>Login</title>
</head>

<body>
    <div class="container-lg">
        <div class="row justify-content-center">
            <div class="col-6">
                <h1 class="text-center mt-5">Login</h1>
                <form action="login_process.php" method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </div>
        <div class="row justify-content-center mt-3">
            <div class="col-6 text-center">
                <p>Don't have an account? <a href="sign_up.php">Sign Up</a></p>
            </div>
        </div>
</body>

<script src="asset/js/jquery-3.7.1.js"></script>
<script src="asset/js/bootstrap.bundle.min.js"></script>

</html>