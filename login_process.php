<?php
session_start();
include __DIR__ . '/conn.php';


$username =$_POST['username'];
$password = $_POST['password'];

$query ="SELECT * FROM user WHERE username = '$username' AND password = '$password'";
$sql = mysqli_query($conn,$query);

if(mysqli_num_rows($sql) > 0){
    $_SESSION['login'] = true;
    $_SESSION['username'] = $username;

    header("Location: index.php");
    exit;
}else{
    echo "Login failed";
}