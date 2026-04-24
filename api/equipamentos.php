<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $conn = conectarViaprofit();

    switch ($action) {
        case 'listar':
            listarEquipamentos($conn);
            break;

        case 'cadastrar':
            cadastrarEquipamento($conn);
            break;

        case 'vincular':
            vincularEquipamento($conn);
            break;

        case 'listar_instalados':
            listarInstalados($conn);
            break;

        case 'remover_vinculo':
            removerVinculo($conn);
            break;

        default:
            jsonResponse(false, 'Ação inválida ou não informada.', [], 400);
    }

} catch (Exception $e) {
    jsonResponse(false, 'Erro inesperado.', [
        'erro' => $e->getMessage()
    ], 500);
}

function listarEquipamentos($conn)
{
    $sql = "SELECT * FROM equipamentos ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    jsonResponse(true, 'Equipamentos listados com sucesso.', $stmt->fetchAll());
}

function cadastrarEquipamento($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método inválido. Use POST.', [], 405);
    }

    $sql = "INSERT INTO equipamentos 
        (tipo, marca, modelo, serial, mac, patrimonio, valor_compra, data_compra, status, observacao)
        VALUES
        (:tipo, :marca, :modelo, :serial, :mac, :patrimonio, :valor_compra, :data_compra, :status, :observacao)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':tipo' => $_POST['tipo'] ?? '',
        ':marca' => $_POST['marca'] ?? null,
        ':modelo' => $_POST['modelo'] ?? null,
        ':serial' => $_POST['serial'] ?? null,
        ':mac' => $_POST['mac'] ?? null,
        ':patrimonio' => $_POST['patrimonio'] ?? null,
        ':valor_compra' => $_POST['valor_compra'] ?? 0,
        ':data_compra' => !empty($_POST['data_compra']) ? $_POST['data_compra'] : null,
        ':status' => $_POST['status'] ?? 'estoque',
        ':observacao' => $_POST['observacao'] ?? null
    ]);

    jsonResponse(true, 'Equipamento cadastrado com sucesso.', [
        'id' => $conn->lastInsertId()
    ]);
}

function vincularEquipamento($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método inválido. Use POST.', [], 405);
    }

    $numeroContrato = $_POST['numero_contrato'] ?? '';
    $equipamentoId = $_POST['equipamento_id'] ?? '';
    $dataInstalacao = $_POST['data_instalacao'] ?? date('Y-m-d');
    $valorCalculo = $_POST['valor_usado_no_calculo'] ?? 0;
    $custoInstalacao = $_POST['custo_instalacao'] ?? 0;
    $status = $_POST['status'] ?? 'instalado';
    $observacao = $_POST['observacao'] ?? null;

    if (empty($numeroContrato) || empty($equipamentoId)) {
        jsonResponse(false, 'Número do contrato e equipamento são obrigatórios.', [], 400);
    }

    $conn->beginTransaction();

    $sql = "INSERT INTO equipamentos_instalados
        (numero_contrato, equipamento_id, data_instalacao, valor_usado_no_calculo, custo_instalacao, status, observacao)
        VALUES
        (:numero_contrato, :equipamento_id, :data_instalacao, :valor_usado_no_calculo, :custo_instalacao, :status, :observacao)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':numero_contrato' => $numeroContrato,
        ':equipamento_id' => $equipamentoId,
        ':data_instalacao' => $dataInstalacao,
        ':valor_usado_no_calculo' => $valorCalculo,
        ':custo_instalacao' => $custoInstalacao,
        ':status' => $status,
        ':observacao' => $observacao
    ]);

    $vinculoId = $conn->lastInsertId();

    $stmt = $conn->prepare("UPDATE equipamentos SET status = 'instalado' WHERE id = :id");
    $stmt->execute([':id' => $equipamentoId]);

    if ($valorCalculo > 0) {
        $sqlCusto = "INSERT INTO custos_contrato
            (numero_contrato, origem, origem_id, data_custo, tipo, descricao, valor)
            VALUES
            (:numero_contrato, 'equipamento', :origem_id, :data_custo, 'equipamento', :descricao, :valor)";

        $stmtCusto = $conn->prepare($sqlCusto);
        $stmtCusto->execute([
            ':numero_contrato' => $numeroContrato,
            ':origem_id' => $vinculoId,
            ':data_custo' => $dataInstalacao,
            ':descricao' => 'Equipamento vinculado ao contrato',
            ':valor' => $valorCalculo
        ]);
    }

    if ($custoInstalacao > 0) {
        $sqlCustoInstalacao = "INSERT INTO custos_contrato
            (numero_contrato, origem, origem_id, data_custo, tipo, descricao, valor)
            VALUES
            (:numero_contrato, 'instalacao', :origem_id, :data_custo, 'instalacao', :descricao, :valor)";

        $stmtCustoInstalacao = $conn->prepare($sqlCustoInstalacao);
        $stmtCustoInstalacao->execute([
            ':numero_contrato' => $numeroContrato,
            ':origem_id' => $vinculoId,
            ':data_custo' => $dataInstalacao,
            ':descricao' => 'Custo de instalação do equipamento',
            ':valor' => $custoInstalacao
        ]);
    }

    $conn->commit();

    jsonResponse(true, 'Equipamento vinculado ao contrato com sucesso.', [
        'vinculo_id' => $vinculoId
    ]);
}

function listarInstalados($conn)
{
    $numeroContrato = $_GET['numero_contrato'] ?? '';

    $sql = "SELECT 
                ei.id AS vinculo_id,
                ei.numero_contrato,
                ei.data_instalacao,
                ei.valor_usado_no_calculo,
                ei.custo_instalacao,
                ei.status AS status_vinculo,
                ei.observacao AS observacao_vinculo,
                e.id AS equipamento_id,
                e.tipo,
                e.marca,
                e.modelo,
                e.serial,
                e.mac,
                e.patrimonio,
                e.valor_compra,
                e.status AS status_equipamento
            FROM equipamentos_instalados ei
            INNER JOIN equipamentos e ON e.id = ei.equipamento_id";

    $params = [];

    if (!empty($numeroContrato)) {
        $sql .= " WHERE ei.numero_contrato = :numero_contrato";
        $params[':numero_contrato'] = $numeroContrato;
    }

    $sql .= " ORDER BY ei.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, 'Equipamentos instalados listados com sucesso.', $stmt->fetchAll());
}

function removerVinculo($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método inválido. Use POST.', [], 405);
    }

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        jsonResponse(false, 'ID do vínculo é obrigatório.', [], 400);
    }

    $stmt = $conn->prepare("SELECT equipamento_id FROM equipamentos_instalados WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $vinculo = $stmt->fetch();

    if (!$vinculo) {
        jsonResponse(false, 'Vínculo não encontrado.', [], 404);
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE equipamentos_instalados SET status = 'retirado' WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $stmt = $conn->prepare("UPDATE equipamentos SET status = 'retirado' WHERE id = :id");
    $stmt->execute([':id' => $vinculo['equipamento_id']]);

    $conn->commit();

    jsonResponse(true, 'Vínculo removido com sucesso.', [
        'id' => $id
    ]);
}