<?php

const SENHA_SISTEMA = '';
const SENHA_VIAPROFIT = '';

function conectarSistema()
{
    return criarConexao(
        'localhost',
        'u308598921_via_ccm',
        'u308598921_via_ccm',
         'Mesmox400#'
    );
}

function conectarViaprofit()
{
    return criarConexao(
        'localhost',
        'u308598921_via_profit',
        'u308598921_via_profit',
        'Mesmox400#'
    );
}

function criarConexao($host, $banco, $usuario, $senha)
{
    try {
        $dsn = "mysql:host={$host};dbname={$banco};charset=utf8";

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
