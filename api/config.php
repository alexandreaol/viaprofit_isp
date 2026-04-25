<?php

define('DB_HOST', envConfig('DB_HOST', 'localhost'));

define('DB_SISTEMA', envConfig('DB_SISTEMA', 'u308598921_via_ccm'));
define('DB_USER_SISTEMA', envConfig('DB_USER_SISTEMA', 'u308598921_via_ccm'));
define('DB_PASS_SISTEMA', envConfig('DB_PASS_SISTEMA', 'Mesmox400#'));

define('DB_VIAPROFIT', envConfig('DB_VIAPROFIT', 'u308598921_via_profit'));
define('DB_USER_VIAPROFIT', envConfig('DB_USER_VIAPROFIT', 'u308598921_via_profit'));
define('DB_PASS_VIAPROFIT', envConfig('DB_PASS_VIAPROFIT', 'Mesmox400#'));

function envConfig($chave, $padrao)
{
    $valor = getenv($chave);

    if ($valor === false || $valor === '') {
        return $padrao;
    }

    return $valor;
}

function conectarSistema()
{
    return criarConexao(
        DB_HOST,
        DB_SISTEMA,
        DB_USER_SISTEMA,
        DB_PASS_SISTEMA
    );
}

function conectarViaprofit()
{
    return criarConexao(
        DB_HOST,
        DB_VIAPROFIT,
        DB_USER_VIAPROFIT,
        DB_PASS_VIAPROFIT
    );
}

function criarConexao($host, $banco, $usuario, $senha)
{
    try {
        $dsn = "mysql:host={$host};dbname={$banco};charset=utf8mb4";

        return new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

    } catch (PDOException $erro) {
        jsonResponse(false, 'Erro ao conectar ao banco de dados.', [
            'erro' => $erro->getMessage(),
        ], 500);
    }
}

function jsonResponse($success, $message = '', $data = [], $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);

    exit;
}
