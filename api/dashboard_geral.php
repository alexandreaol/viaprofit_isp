<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'resumo';

try {
    $connSistema = conectarSistema();
    $connViaprofit = conectarViaprofit();

    switch ($action) {
        case 'resumo':
            gerarResumoGeral($connSistema, $connViaprofit);
            break;

        default:
            jsonResponse(false, 'Ação inválida', [], 400);
    }

} catch (Exception $e) {
    jsonResponse(false, 'Erro inesperado', [
        'erro' => $e->getMessage()
    ], 500);
}

function gerarResumoGeral($connSistema, $connViaprofit)
{
    $referencia = $_GET['referencia'] ?? date('Y-m');
    $inicioMes = $referencia . '-01';
    $fimMes = date('Y-m-t', strtotime($inicioMes));

    // ==========================
    // CONTRATOS ATIVOS
    // ==========================
    $stmt = $connSistema->prepare("
        SELECT 
            COUNT(*) AS total_contratos,
            COALESCE(SUM(GREATEST(COALESCE(valor, valor_plano, 0) - COALESCE(desconto, 0), 0)), 0) AS receita_mensal_prevista,
            COALESCE(AVG(GREATEST(COALESCE(valor, valor_plano, 0) - COALESCE(desconto, 0), 0)), 0) AS ticket_medio
        FROM contratos
        WHERE status_contrato = 'ativo'
    ");
    $stmt->execute();
    $contratosAtivos = $stmt->fetch();

    $totalContratosAtivos = intval($contratosAtivos['total_contratos'] ?? 0);
    $receitaMensalPrevista = floatval($contratosAtivos['receita_mensal_prevista'] ?? 0);
    $ticketMedio = floatval($contratosAtivos['ticket_medio'] ?? 0);

    // ==========================
    // RECEBIMENTOS DO MÊS
    // ==========================
    $stmt = $connSistema->prepare("
        SELECT
            COALESCE(SUM(valor_final), 0) AS previsto_mes,
            COALESCE(SUM(CASE WHEN status = 'quitado' THEN valor_recebido ELSE 0 END), 0) AS recebido_mes,
            COALESCE(SUM(CASE WHEN status IN ('aberto','gerado','parcial') THEN saldo_restante ELSE 0 END), 0) AS aberto_mes,
            COALESCE(SUM(CASE WHEN status = 'vencido' THEN saldo_restante ELSE 0 END), 0) AS vencido_mes,
            COUNT(*) AS total_recebimentos,
            SUM(CASE WHEN status = 'quitado' THEN 1 ELSE 0 END) AS recebimentos_quitados
        FROM recebimentos
        WHERE data_vencimento BETWEEN :inicio AND :fim
        AND status <> 'cancelado'
        AND status <> 'excluido'
    ");
    $stmt->execute([
        ':inicio' => $inicioMes,
        ':fim' => $fimMes
    ]);
    $recebimentosMes = $stmt->fetch();

    $previstoMes = floatval($recebimentosMes['previsto_mes'] ?? 0);
    $recebidoMes = floatval($recebimentosMes['recebido_mes'] ?? 0);
    $abertoMes = floatval($recebimentosMes['aberto_mes'] ?? 0);
    $vencidoMes = floatval($recebimentosMes['vencido_mes'] ?? 0);
    $totalRecebimentos = intval($recebimentosMes['total_recebimentos'] ?? 0);
    $recebimentosQuitados = intval($recebimentosMes['recebimentos_quitados'] ?? 0);

    // ==========================
    // IMPOSTOS E TAXAS DO MÊS
    // ==========================
    $stmt = $connSistema->prepare("
        SELECT 
            r.id,
            r.valor_recebido,
            br.forma_pagamento AS forma_pagamento_baixa,
            cg.forma_pagamento AS forma_pagamento_gateway
        FROM recebimentos r
        LEFT JOIN baixas_recebimentos br ON br.id_recebimento = r.id
        LEFT JOIN cobrancas_gateway cg ON cg.id_recebimento = r.id
        WHERE r.status = 'quitado'
        AND r.data_vencimento BETWEEN :inicio AND :fim
    ");
    $stmt->execute([
        ':inicio' => $inicioMes,
        ':fim' => $fimMes
    ]);

    $linhasRecebidas = $stmt->fetchAll();
    $recebimentosAgrupados = [];

    foreach ($linhasRecebidas as $linha) {
        $id = $linha['id'];

        if (!isset($recebimentosAgrupados[$id])) {
            $recebimentosAgrupados[$id] = $linha;
        } else {
            if (empty($recebimentosAgrupados[$id]['forma_pagamento_baixa']) && !empty($linha['forma_pagamento_baixa'])) {
                $recebimentosAgrupados[$id]['forma_pagamento_baixa'] = $linha['forma_pagamento_baixa'];
            }

            if (empty($recebimentosAgrupados[$id]['forma_pagamento_gateway']) && !empty($linha['forma_pagamento_gateway'])) {
                $recebimentosAgrupados[$id]['forma_pagamento_gateway'] = $linha['forma_pagamento_gateway'];
            }
        }
    }

    $impostosMes = 0;
    $taxasPixMes = 0;
    $taxasBoletoMes = 0;

    foreach ($recebimentosAgrupados as $r) {
        $valor = floatval($r['valor_recebido'] ?? 0);

        $impostosMes += $valor * 0.06;

        $forma = obterFormaPagamentoDashboard($r);

        if ($forma === 'boleto') {
            $taxasBoletoMes += 2.99;
        } elseif ($forma === 'pix') {
            $taxasPixMes += 0.40;
        }
    }

    // ==========================
    // CUSTOS DA EMPRESA / CONTRATOS
    // ==========================
    $redeNeutraMes = $totalContratosAtivos * 23.50;

    $custosMensaisContratos = 0;
    if (tabelaExisteDashboard($connViaprofit, 'custos_mensais_contrato')) {
        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor), 0) AS total
            FROM custos_mensais_contrato
            WHERE ativo = 1
        ");
        $stmt->execute();
        $custosMensaisContratos = floatval($stmt->fetch()['total'] ?? 0);
    }

    $custosUnicosMes = 0;
    if (tabelaExisteDashboard($connViaprofit, 'custos_contrato')) {
        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor), 0) AS total
            FROM custos_contrato
            WHERE data_custo BETWEEN :inicio AND :fim
        ");
        $stmt->execute([
            ':inicio' => $inicioMes,
            ':fim' => $fimMes
        ]);
        $custosUnicosMes = floatval($stmt->fetch()['total'] ?? 0);
    }

    $custosGeraisMes = 0;
    if (tabelaExisteDashboard($connViaprofit, 'custos_gerais_mensais')) {
        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor), 0) AS total
            FROM custos_gerais_mensais
            WHERE referencia = :referencia
            AND ativo = 1
        ");
        $stmt->execute([
            ':referencia' => $referencia
        ]);
        $custosGeraisMes = floatval($stmt->fetch()['total'] ?? 0);
    }

    $custoTotalEstimadoMes =
        $redeNeutraMes +
        $impostosMes +
        $taxasPixMes +
        $taxasBoletoMes +
        $custosMensaisContratos +
        $custosUnicosMes +
        $custosGeraisMes;

    $lucroEstimadoMes = $recebidoMes - $custoTotalEstimadoMes;

    // ==========================
    // EQUIPAMENTOS / CONTRATOS SEM VÍNCULO
    // ==========================
    $equipamentosInstalados = 0;
    $contratosComEquipamento = 0;

    if (tabelaExisteDashboard($connViaprofit, 'equipamentos_instalados')) {
        $stmt = $connViaprofit->prepare("
            SELECT 
                COUNT(*) AS equipamentos_instalados,
                COUNT(DISTINCT numero_contrato) AS contratos_com_equipamento
            FROM equipamentos_instalados
            WHERE status = 'instalado'
        ");
        $stmt->execute();
        $equip = $stmt->fetch();

        $equipamentosInstalados = intval($equip['equipamentos_instalados'] ?? 0);
        $contratosComEquipamento = intval($equip['contratos_com_equipamento'] ?? 0);
    }

    $contratosSemEquipamento = max(0, $totalContratosAtivos - $contratosComEquipamento);

    // ==========================
    // LISTA DE CONTRATOS RECENTES
    // ==========================
    $stmt = $connSistema->prepare("
        SELECT 
            c.id,
            c.numero,
            c.valor,
            c.valor_plano,
            c.desconto,
            c.status_contrato,
            cli.nome,
            cli.cpf,
            GREATEST(COALESCE(c.valor, c.valor_plano, 0) - COALESCE(c.desconto, 0), 0) AS valor_final
        FROM contratos c
        LEFT JOIN clientes cli ON cli.id = c.id_cliente
        WHERE c.status_contrato = 'ativo'
        ORDER BY c.id DESC
        LIMIT 20
    ");
    $stmt->execute();
    $contratosRecentes = $stmt->fetchAll();

    jsonResponse(true, 'Dashboard geral calculado com sucesso.', [
        'referencia' => $referencia,
        'periodo' => [
            'inicio' => $inicioMes,
            'fim' => $fimMes
        ],
        'financeiro' => [
            'receita_mensal_prevista' => $receitaMensalPrevista,
            'previsto_mes' => $previstoMes,
            'recebido_mes' => $recebidoMes,
            'aberto_mes' => $abertoMes,
            'vencido_mes' => $vencidoMes,
            'custo_total_estimado_mes' => $custoTotalEstimadoMes,
            'lucro_estimado_mes' => $lucroEstimadoMes,
            'ticket_medio' => $ticketMedio
        ],
        'custos' => [
            'rede_neutra_mes' => $redeNeutraMes,
            'impostos_mes' => $impostosMes,
            'taxas_pix_mes' => $taxasPixMes,
            'taxas_boleto_mes' => $taxasBoletoMes,
            'custos_mensais_contratos' => $custosMensaisContratos,
            'custos_unicos_mes' => $custosUnicosMes,
            'custos_gerais_mes' => $custosGeraisMes
        ],
        'operacional' => [
            'contratos_ativos' => $totalContratosAtivos,
            'total_recebimentos' => $totalRecebimentos,
            'recebimentos_quitados' => $recebimentosQuitados,
            'equipamentos_instalados' => $equipamentosInstalados,
            'contratos_com_equipamento' => $contratosComEquipamento,
            'contratos_sem_equipamento' => $contratosSemEquipamento
        ],
        'contratos_recentes' => $contratosRecentes
    ]);
}

function obterFormaPagamentoDashboard($recebimento)
{
    $formaBaixa = strtolower(trim($recebimento['forma_pagamento_baixa'] ?? ''));
    $formaGateway = strtolower(trim($recebimento['forma_pagamento_gateway'] ?? ''));

    if (!empty($formaBaixa)) {
        return normalizarFormaPagamentoDashboard($formaBaixa);
    }

    if (!empty($formaGateway)) {
        return normalizarFormaPagamentoDashboard($formaGateway);
    }

    return 'pix';
}

function normalizarFormaPagamentoDashboard($forma)
{
    $forma = strtolower(trim($forma));

    if ($forma === 'boleto' || $forma === 'bank_slip') {
        return 'boleto';
    }

    if ($forma === 'pix' || $forma === 'qrcode' || $forma === 'qr_code') {
        return 'pix';
    }

    if ($forma === 'dinheiro') {
        return 'dinheiro';
    }

    if ($forma === 'transferencia' || $forma === 'transferência' || $forma === 'ted' || $forma === 'doc') {
        return 'transferencia';
    }

    return $forma ?: 'pix';
}

function tabelaExisteDashboard($conn, $tabela)
{
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE :tabela");
        $stmt->execute([
            ':tabela' => $tabela
        ]);

        return $stmt->rowCount() > 0;

    } catch (Exception $e) {
        return false;
    }
}