<?php
include __DIR__ . '/conn.php';

session_start();
session_destroy();
header("location:login.php?p=loggedout");
exit;
