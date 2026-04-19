<?php

$dbcon = mysqli_connect('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');

mysqli_set_charset($dbcon, "utf8");
if (!$dbcon) {
    die("SEM acesso ao servidor: ");
    exit();
}

$dbs = mysqli_select_db($dbcon, 'db_a90b7e_baseh');
if (!$dbs) {
    die("SEM acesso a base de dados: " . mysqli_error($dbcon));
    exit();
}
