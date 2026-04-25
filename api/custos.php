<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $conn = conectarViaprofit();

    switch ($action) {
        case 'listar':
            listarCustos($conn);
            break;

        case 'cadastrar_unico':
            cadastrarCustoUnico($conn);
            break;

        case 'cadastrar_mensal':
            cadastrarCustoMensal($conn);
            break;

        default:
            jsonResponse(false, 'Ação inválida ou não informada.', [], 400);
    }

} catch (Exception $e) {
    jsonResponse(false, 'Erro inesperado.', [
        'erro' => $e->getMessage()
    ], 500);
}

function listarCustos($conn)
{
    $numeroContrato = $_GET['numero_contrato'] ?? '';

    if (empty($numeroContrato)) {
        jsonResponse(false, 'Número do contrato é obrigatório.', [], 400);
    }

    $stmtUnicos = $conn->prepare("
        SELECT 
            id,
            numero_contrato,
            data_custo,
            tipo,
            descricao,
            valor,
            origem,
            origem_id,
            criado_em,
            'unico' AS categoria_custo
        FROM custos_contrato
        WHERE numero_contrato = :numero_contrato
        ORDER BY data_custo DESC, id DESC
    ");

    $stmtUnicos->execute([
        ':numero_contrato' => $numeroContrato
    ]);

    $custosUnicos = $stmtUnicos->fetchAll();

    $stmtMensais = $conn->prepare("
        SELECT
            id,
            numero_contrato,
            tipo,
            descricao,
            valor,
            ativo,
            criado_em,
            'mensal' AS categoria_custo
        FROM custos_mensais_contrato
        WHERE numero_contrato = :numero_contrato
        ORDER BY id DESC
    ");

    $stmtMensais->execute([
        ':numero_contrato' => $numeroContrato
    ]);

    $custosMensais = $stmtMensais->fetchAll();

    jsonResponse(true, 'Custos listados com sucesso.', [
        'custos_unicos' => $custosUnicos,
        'custos_mensais' => $custosMensais
    ]);
}

function cadastrarCustoUnico($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método inválido. Use POST.', [], 405);
    }

    $numeroContrato = $_POST['numero_contrato'] ?? '';
    $dataCusto = $_POST['data_custo'] ?? date('Y-m-d');
    $tipo = $_POST['tipo'] ?? 'outro';
    $descricao = $_POST['descricao'] ?? '';
    $valor = $_POST['valor'] ?? 0;

    if (empty($numeroContrato)) {
        jsonResponse(false, 'Número do contrato é obrigatório.', [], 400);
    }

    if (floatval($valor) <= 0) {
        jsonResponse(false, 'Valor deve ser maior que zero.', [], 400);
    }

    $sql = "INSERT INTO custos_contrato
        (numero_contrato, origem, origem_id, data_custo, tipo, descricao, valor)
        VALUES
        (:numero_contrato, 'manual', NULL, :data_custo, :tipo, :descricao, :valor)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':numero_contrato' => $numeroContrato,
        ':data_custo' => $dataCusto,
        ':tipo' => $tipo,
        ':descricao' => $descricao,
        ':valor' => $valor
    ]);

    jsonResponse(true, 'Custo único cadastrado com sucesso.', [
        'id' => $conn->lastInsertId()
    ]);
}

function cadastrarCustoMensal($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método inválido. Use POST.', [], 405);
    }

    $numeroContrato = $_POST['numero_contrato'] ?? '';
    $tipo = $_POST['tipo'] ?? 'outro';
    $descricao = $_POST['descricao'] ?? '';
    $valor = $_POST['valor'] ?? 0;
    $ativo = $_POST['ativo'] ?? 1;

    if (empty($numeroContrato)) {
        jsonResponse(false, 'Número do contrato é obrigatório.', [], 400);
    }

    if (floatval($valor) <= 0) {
        jsonResponse(false, 'Valor deve ser maior que zero.', [], 400);
    }

    $sql = "INSERT INTO custos_mensais_contrato
        (numero_contrato, tipo, descricao, valor, ativo)
        VALUES
        (:numero_contrato, :tipo, :descricao, :valor, :ativo)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':numero_contrato' => $numeroContrato,
        ':tipo' => $tipo,
        ':descricao' => $descricao,
        ':valor' => $valor,
        ':ativo' => $ativo
    ]);

    jsonResponse(true, 'Custo mensal cadastrado com sucesso.', [
        'id' => $conn->lastInsertId()
    ]);
}