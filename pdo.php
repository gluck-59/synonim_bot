<?php
    
// мануал http://phpfaq.ru/pdo
// константы http://fi2.php.net/manual/ru/pdo.constants.php
// транзакции http://fi2.php.net/manual/ru/pdo.transactions.php

error_reporting(E_ERROR);
ini_set('display_errors','On');
ini_set('default_charset', 'utf-8');

$host = 'localhost';
$db = 'synonim';
$charset = 'utf8';
$user = 'root'; // юзер с ограниченными правами для уменьшения checking_permissions
$pass = 'NhbUdjplz';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
);

$pdo = new PDO($dsn, $user, $pass, $opt);
    
?>