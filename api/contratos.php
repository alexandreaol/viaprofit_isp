<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

try {
    $conn = conectarSistema();

    switch ($action) {

        case 'listar':
            listarContratos($conn);
            break;

        case 'buscar':
            buscarContratos($conn);
            break;

        case 'detalhe':
            detalheContrato($conn);
            break;

        default:
            jsonResponse(false, 'Ação inválida', [], 400);
    }

} catch (Exception $e) {
    jsonResponse(false, 'Erro inesperado', [
        'erro' => $e->getMessage()
    ], 500);
}


// ============================
// LISTAR TODOS
// ============================
function listarContratos($conn)
{
    $sql = "SELECT 
                c.id,
                c.numero,
                c.valor,
                c.status_contrato,
                c.dia_vencimento,
                cli.nome,
                cli.cpf
            FROM contratos c
            LEFT JOIN clientes cli ON cli.id = c.id_cliente
            ORDER BY c.id DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    jsonResponse(true, 'Contratos listados', $stmt->fetchAll());
}


// ============================
// BUSCAR POR NOME / CPF / NUMERO
// ============================
function buscarContratos($conn)
{
    $busca = $_GET['busca'] ?? '';

    if (empty($busca)) {
        jsonResponse(false, 'Informe um termo de busca', [], 400);
    }

    $sql = "SELECT 
                c.id,
                c.numero,
                c.valor,
                c.valor_plano,
                c.status_contrato,
                c.dia_vencimento,
                cli.nome,
                cli.cpf
            FROM contratos c
            LEFT JOIN clientes cli ON cli.id = c.id_cliente
            WHERE 
                c.numero LIKE :busca_numero OR
                cli.nome LIKE :busca_nome OR
                cli.cpf LIKE :busca_cpf
            ORDER BY c.id DESC
            LIMIT 50";

    $stmt = $conn->prepare($sql);

    $termo = '%' . $busca . '%';

    $stmt->execute([
        ':busca_numero' => $termo,
        ':busca_nome' => $termo,
        ':busca_cpf' => $termo
    ]);

    jsonResponse(true, 'Resultado da busca', $stmt->fetchAll());
}


// ============================
// DETALHE DO CONTRATO
// ============================
function detalheContrato($conn)
{
    $numero = $_GET['numero'] ?? '';

    if (empty($numero)) {
        jsonResponse(false, 'Número do contrato é obrigatório', [], 400);
    }

    $sql = "SELECT 
                c.*,
                cli.nome,
                cli.cpf,
                cli.rua,
                cli.numero AS numero_endereco,
                cli.bairro,
                cli.cidade,
                cli.estado
            FROM contratos c
            LEFT JOIN clientes cli ON cli.id = c.id_cliente
            WHERE c.numero = :numero
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $contrato = $stmt->fetch();

    if (!$contrato) {
        jsonResponse(false, 'Contrato não encontrado', [], 404);
    }

    jsonResponse(true, 'Contrato encontrado', $contrato);
}