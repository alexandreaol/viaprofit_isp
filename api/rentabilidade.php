<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

try {
    $connSistema = conectarSistema();
    $connViaprofit = conectarViaprofit();

    switch ($action) {

        case 'contrato':
            calcularRentabilidadeContrato($connSistema, $connViaprofit);
            break;

        default:
            jsonResponse(false, 'Ação inválida', [], 400);
    }

} catch (Exception $e) {
    jsonResponse(false, 'Erro inesperado', [
        'erro' => $e->getMessage()
    ], 500);
}


// ==========================
// RENTABILIDADE DO CONTRATO
// ==========================
function calcularRentabilidadeContrato($connSistema, $connViaprofit)
{
    $numero = $_GET['numero'] ?? '';

    if (empty($numero)) {
        jsonResponse(false, 'Número do contrato obrigatório', [], 400);
    }

    // ==========================
    // BUSCA CONTRATO
    // ==========================
    $sqlContrato = "SELECT 
                        c.numero,
                        c.valor,
                        c.status_contrato,
                        cli.nome
                    FROM contratos c
                    LEFT JOIN clientes cli ON cli.id = c.id_cliente
                    WHERE c.numero = :numero
                    LIMIT 1";

    $stmt = $connSistema->prepare($sqlContrato);
    $stmt->execute([':numero' => $numero]);
    $contrato = $stmt->fetch();

    if (!$contrato) {
        jsonResponse(false, 'Contrato não encontrado', [], 404);
    }

    // ==========================
    // RECEITA TOTAL
    // ==========================
    $sqlReceita = "SELECT 
                        SUM(valor_pago) AS total_receita,
                        COUNT(*) AS meses_pagamento
                    FROM recebimentos
                    WHERE numero_contrato = :numero
                    AND status = 'pago'";

    $stmt = $connSistema->prepare($sqlReceita);
    $stmt->execute([':numero' => $numero]);
    $receita = $stmt->fetch();

    $totalReceita = floatval($receita['total_receita'] ?? 0);
    $meses = intval($receita['meses_pagamento'] ?? 0);

    // ==========================
    // CUSTOS
    // ==========================
    $sqlCustos = "SELECT 
                    SUM(valor) AS total_custo
                  FROM custos_contrato
                  WHERE numero_contrato = :numero";

    $stmt = $connViaprofit->prepare($sqlCustos);
    $stmt->execute([':numero' => $numero]);
    $custos = $stmt->fetch();

    $totalCusto = floatval($custos['total_custo'] ?? 0);

    // ==========================
    // LUCRO
    // ==========================
    $lucro = $totalReceita - $totalCusto;

    // ==========================
    // LUCRO MENSAL
    // ==========================
    $lucroMensal = $meses > 0 ? ($lucro / $meses) : 0;

    // ==========================
    // PAYBACK
    // ==========================
    $valorMensal = floatval($contrato['valor']);
    $payback = $valorMensal > 0 ? ($totalCusto / $valorMensal) : 0;

    // ==========================
    // STATUS
    // ==========================
    $status = 'empate';

    if ($lucro > 0) $status = 'lucro';
    if ($lucro < 0) $status = 'prejuizo';

    jsonResponse(true, 'Rentabilidade calculada', [
        'contrato' => $contrato['numero'],
        'cliente' => $contrato['nome'],

        'receita_total' => $totalReceita,
        'custo_total' => $totalCusto,
        'lucro_total' => $lucro,

        'meses' => $meses,
        'lucro_mensal' => $lucroMensal,

        'valor_mensal' => $valorMensal,
        'payback_meses' => $payback,

        'status' => $status
    ]);
}