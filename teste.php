<?php
require_once __DIR__ . '/config.php';

$conexaoSistema = conectarSistema();
$conexaoViaprofit = conectarViaprofit();

jsonResponse(true, 'Conexões realizadas com sucesso.', [
    'sistema' => 'conectado',
    'viaprofit' => 'conectado'
]);