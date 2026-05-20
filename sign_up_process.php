<?php
include __DIR__ . '/conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = $_POST['username'];
$password = $_POST['password'];

$check = "SELECT * FROM user WHERE username='$username'";
$result = mysqli_query($conn, $check);

if(mysqli_num_rows($result) > 0){
    echo "Username already exists";
}else{
    $query = "INSERT INTO user (username, password)
              VALUES ('$username', '$password')";

    if(mysqli_query($conn, $query)){
        header("Location: login.php?p=registered");
        exit;
    }else{
        echo "Error: " . mysqli_error($conn);
    }
}