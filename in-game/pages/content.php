<?php include '../conn.php';

if(empty($_GET['p'])){
    header("location:content.php?p=level1");
} else if($_GET['p'] == "level1"){
    $content =  __DIR__ . '/level1.php';
} else if($_GET['p'] == "level2"){
    $content =  __DIR__ . '/level2.php';
} else if($_GET['p'] == "level3"){
    $content =  __DIR__ . '/level3.php';
} else if($_GET['p'] == "shop1"){
    $content =  __DIR__ . '/shop1.php';
} else if($_GET['p'] == "maplv1"){
    $content =  __DIR__ . '/maplv1.php';
} else {
    header("location:content.php?p=level1");
}