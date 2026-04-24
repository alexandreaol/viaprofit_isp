<?php

require_once __DIR__ . '/config.php';

$resultado = [
    'sistema' => false,
    'viaprofit' => false,
];

try {
    $conexaoSistema = conectarSistema();
    $resultado['sistema'] = $conexaoSistema instanceof PDO;

    $conexaoViaprofit = conectarViaprofit();
    $resultado['viaprofit'] = $conexaoViaprofit instanceof PDO;

    jsonResponse(true, 'Conexoes testadas com sucesso.', $resultado);
} catch (Throwable $erro) {
    jsonResponse(false, 'Erro ao testar conexoes.', [
        'erro' => $erro->getMessage(),
        'resultado' => $resultado,
    ], 500);
}
