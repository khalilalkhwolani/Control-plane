<?php

$pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
$pdo->exec('CREATE DATABASE IF NOT EXISTS pro_one_control_center CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "ok\n";
