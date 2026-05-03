<?php

require __DIR__ . '/../../vendor/autoload.php';


// Ativa o log de erros
ini_set('log_errors', 1);

// Define o caminho do arquivo (pode ser relativo ao script ou absoluto)
ini_set('error_log', __DIR__ . '/meus_erros.log');

// Opcional: mostra na tela também para não ter dúvida
ini_set('display_errors', 1);
error_reporting(E_ALL);

use Vatts\Vatts;

$project = dirname(__DIR__);
// Exemplo mínimo de inicialização: passa project_path e (opcional) config de DB
$app = Vatts::init([
    'project_path' => $project,
    'db' => [
        'driver'   => "sqlite",
        'host'     => "localhost",
        'database' => "panel.db", // Nome do banco
        'username' => "ender",
        'password' => 'ofdosfdjsdifsudi',
        'charset'  => 'utf8mb4'
    ]
]);

// Inicia o servidor
$app->run();
