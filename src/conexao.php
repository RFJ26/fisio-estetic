<?php

$env = parse_ini_file(__DIR__ . '/../aiven.env');

define('DB_HOST', $env['DB_HOST']);
define('DB_USER', $env['DB_USER']);
define('DB_PORT', $env['DB_PORT']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);

$conn = new mysqli(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT
) or die("Erro na conexão à base de dados");