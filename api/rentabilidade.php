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

function calcularRentabilidadeContrato($connSistema, $connViaprofit)
{
    $numero = $_GET['numero'] ?? '';

    if (empty($numero)) {
        jsonResponse(false, 'Número do contrato obrigatório', [], 400);
    }

    // Busca contrato no banco via_ccm
    $sqlContrato = "SELECT 
                        c.id,
                        c.numero,
                        c.valor,
                        c.valor_plano,
                        c.status_contrato,
                        cli.nome
                    FROM contratos c
                    LEFT JOIN clientes cli ON cli.id = c.id_cliente
                    WHERE c.numero = :numero
                    LIMIT 1";

    $stmt = $connSistema->prepare($sqlContrato);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $contrato = $stmt->fetch();

    if (!$contrato) {
        jsonResponse(false, 'Contrato não encontrado', [], 404);
    }

    $idContrato = $contrato['id'];
    $valorMensal = floatval($contrato['valor'] ?? $contrato['valor_plano'] ?? 0);

    // Receita recebida do contrato
    // IMPORTANTE: usa id_contrato e campo valor, pois sua tabela não tem valor_pago
    $sqlReceita = "SELECT 
                        COALESCE(SUM(valor), 0) AS total_receita,
                        COUNT(*) AS meses_pagamento
                    FROM recebimentos
                    WHERE id_contrato = :id_contrato
                    AND status = 'pago'";

    $stmt = $connSistema->prepare($sqlReceita);
    $stmt->execute([
        ':id_contrato' => $idContrato
    ]);

    $receita = $stmt->fetch();

    $totalReceita = floatval($receita['total_receita'] ?? 0);
    $meses = intval($receita['meses_pagamento'] ?? 0);

    // Custos lançados no ViaProfit
    $sqlCustos = "SELECT 
                    COALESCE(SUM(valor), 0) AS total_custo
                  FROM custos_contrato
                  WHERE numero_contrato = :numero";

    $stmt = $connViaprofit->prepare($sqlCustos);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $custos = $stmt->fetch();

    $totalCusto = floatval($custos['total_custo'] ?? 0);

    // Equipamentos vinculados
    $sqlEquipamentos = "SELECT 
                            ei.id AS vinculo_id,
                            ei.numero_contrato,
                            ei.data_instalacao,
                            ei.valor_usado_no_calculo,
                            ei.custo_instalacao,
                            ei.status AS status_vinculo,
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
                        INNER JOIN equipamentos e ON e.id = ei.equipamento_id
                        WHERE ei.numero_contrato = :numero
                        ORDER BY ei.id DESC";

    $stmt = $connViaprofit->prepare($sqlEquipamentos);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $equipamentos = $stmt->fetchAll();

    // Manutenções vinculadas
    $sqlManutencoes = "SELECT 
                            id,
                            data_manutencao,
                            tipo_manutencao,
                            tecnico,
                            descricao,
                            custo_tecnico,
                            custo_material,
                            valor_cobrado_cliente,
                            status
                       FROM manutencoes_contrato
                       WHERE numero_contrato = :numero
                       ORDER BY data_manutencao DESC, id DESC";

    $stmt = $connViaprofit->prepare($sqlManutencoes);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $manutencoes = $stmt->fetchAll();

    // Cálculos principais
    $lucro = $totalReceita - $totalCusto;
    $lucroMensal = $meses > 0 ? ($lucro / $meses) : 0;
    $payback = $valorMensal > 0 ? ($totalCusto / $valorMensal) : 0;

    $statusRentabilidade = 'empate';

    if ($lucro > 0) {
        $statusRentabilidade = 'lucro';
    } elseif ($lucro < 0) {
        $statusRentabilidade = 'prejuizo';
    }

    jsonResponse(true, 'Rentabilidade calculada', [
        'contrato' => [
            'id' => $contrato['id'],
            'numero' => $contrato['numero'],
            'cliente' => $contrato['nome'],
            'valor_mensal' => $valorMensal,
            'status_contrato' => $contrato['status_contrato']
        ],
        'resumo' => [
            'receita_total' => $totalReceita,
            'custo_total' => $totalCusto,
            'lucro_total' => $lucro,
            'meses_pagos' => $meses,
            'lucro_mensal_medio' => $lucroMensal,
            'payback_meses' => $payback,
            'status_rentabilidade' => $statusRentabilidade
        ],
        'equipamentos' => $equipamentos,
        'manutencoes' => $manutencoes
    ]);
}