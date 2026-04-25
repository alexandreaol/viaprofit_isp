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
    ");
    $stmt->execute();
    $contratosAtivos = $stmt->fetchAll();

    $totalContratosAtivos = count($contratosAtivos);

    $receitaPrevistaContratos = 0;
    foreach ($contratosAtivos as $contrato) {
        $receitaPrevistaContratos += floatval($contrato['valor_final'] ?? 0);
    }

    $ticketMedio = $totalContratosAtivos > 0
        ? $receitaPrevistaContratos / $totalContratosAtivos
        : 0;

    // ==========================
    // RECEBIMENTOS DO MÊS
    // ==========================
    $stmt = $connSistema->prepare("
        SELECT
            COALESCE(SUM(valor_final), 0) AS receita_prevista_mes,
            COALESCE(SUM(CASE WHEN status = 'quitado' THEN valor_recebido ELSE 0 END), 0) AS receita_recebida_mes,
            COALESCE(SUM(CASE WHEN status IN ('aberto','gerado','parcial') THEN saldo_restante ELSE 0 END), 0) AS em_aberto_mes,
            COALESCE(SUM(CASE WHEN status = 'vencido' THEN saldo_restante ELSE 0 END), 0) AS vencido_mes
        FROM recebimentos
        WHERE data_vencimento BETWEEN :inicio AND :fim
        AND status NOT IN ('cancelado','excluido')
    ");
    $stmt->execute([
        ':inicio' => $inicioMes,
        ':fim' => $fimMes
    ]);
    $recebimentosMes = $stmt->fetch();

    $receitaPrevistaMes = floatval($recebimentosMes['receita_prevista_mes'] ?? 0);
    $receitaRecebidaMes = floatval($recebimentosMes['receita_recebida_mes'] ?? 0);
    $emAbertoMes = floatval($recebimentosMes['em_aberto_mes'] ?? 0);
    $vencidoMes = floatval($recebimentosMes['vencido_mes'] ?? 0);

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

    $recebimentosBrutos = $stmt->fetchAll();
    $recebimentosAgrupados = [];

    foreach ($recebimentosBrutos as $linha) {
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

    $impostosEstimados = 0;
    $taxasPix = 0;
    $taxasBoleto = 0;

    foreach ($recebimentosAgrupados as $recebimento) {
        $valorRecebido = floatval($recebimento['valor_recebido'] ?? 0);

        $impostosEstimados += $valorRecebido * 0.06;

        $formaPagamento = obterFormaPagamentoDashboard($recebimento);

        if ($formaPagamento === 'boleto') {
            $taxasBoleto += 2.99;
        } elseif ($formaPagamento === 'pix') {
            $taxasPix += 0.40;
        }
    }

    // ==========================
    // CUSTOS
    // ==========================
    $redeNeutraTotal = $totalContratosAtivos * 23.50;

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

    $custosGeraisRateados = 0;
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
        $custosGeraisRateados = floatval($stmt->fetch()['total'] ?? 0);
    }

    $custosMensaisContratoTotal = 0;
    if (tabelaExisteDashboard($connViaprofit, 'custos_mensais_contrato')) {
        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor), 0) AS total
            FROM custos_mensais_contrato
            WHERE ativo = 1
        ");
        $stmt->execute();
        $custosMensaisContratoTotal = floatval($stmt->fetch()['total'] ?? 0);
    }

    $taxasPixBoleto = $taxasPix + $taxasBoleto;

    $custoTotalEstimado =
        $redeNeutraTotal +
        $impostosEstimados +
        $taxasPixBoleto +
        $custosUnicosMes +
        $custosGeraisRateados +
        $custosMensaisContratoTotal;

    $lucroEstimado = $receitaRecebidaMes - $custoTotalEstimado;

    // ==========================
    // SAÚDE DOS CONTRATOS / RANKINGS
    // ==========================
    $contratosLucrativos = 0;
    $contratosPrejuizo = 0;
    $contratosPayback = 0;

    $rankingContratos = [];

    foreach ($contratosAtivos as $contrato) {
        $numero = $contrato['numero'];
        $valorMensal = floatval($contrato['valor_final'] ?? 0);

        $custosUnicosContrato = buscarCustosUnicosContrato($connViaprofit, $numero);
        $custoMensalContrato = buscarCustoMensalContrato($connViaprofit, $numero);

        $impostoMensal = $valorMensal * 0.06;
        $taxaPagamentoPadrao = 0.40;
        $redeNeutraMensal = 23.50;

        $lucroMensalProjetado =
            $valorMensal -
            $redeNeutraMensal -
            $impostoMensal -
            $taxaPagamentoPadrao -
            $custoMensalContrato;

        if ($lucroMensalProjetado > 0) {
            $contratosLucrativos++;
        } elseif ($lucroMensalProjetado < 0) {
            $contratosPrejuizo++;
        }

        if ($custosUnicosContrato > 0 && $lucroMensalProjetado > 0) {
            $contratosPayback++;
        }

        $rankingContratos[] = [
            'numero' => $numero,
            'cliente' => $contrato['nome'],
            'cpf' => $contrato['cpf'],
            'valor_final' => $valorMensal,
            'custos_unicos' => $custosUnicosContrato,
            'custo_mensal_contrato' => $custoMensalContrato,
            'lucro_mensal_projetado' => $lucroMensalProjetado
        ];
    }

    usort($rankingContratos, function ($a, $b) {
        return $b['lucro_mensal_projetado'] <=> $a['lucro_mensal_projetado'];
    });

    $topMelhores = array_slice($rankingContratos, 0, 10);

    $topPiores = $rankingContratos;
    usort($topPiores, function ($a, $b) {
        return $a['lucro_mensal_projetado'] <=> $b['lucro_mensal_projetado'];
    });
    $topPiores = array_slice($topPiores, 0, 10);

    // ==========================
    // CONTRATOS SEM EQUIPAMENTO
    // ==========================
    $contratosComEquipamento = [];

    if (tabelaExisteDashboard($connViaprofit, 'equipamentos_instalados')) {
        $stmt = $connViaprofit->prepare("
            SELECT DISTINCT numero_contrato
            FROM equipamentos_instalados
            WHERE status = 'instalado'
        ");
        $stmt->execute();
        $lista = $stmt->fetchAll();

        foreach ($lista as $item) {
            $contratosComEquipamento[$item['numero_contrato']] = true;
        }
    }

    $contratosSemEquipamento = [];

    foreach ($contratosAtivos as $contrato) {
        if (empty($contratosComEquipamento[$contrato['numero']])) {
            $contratosSemEquipamento[] = [
                'numero' => $contrato['numero'],
                'cliente' => $contrato['nome'],
                'cpf' => $contrato['cpf'],
                'valor_final' => floatval($contrato['valor_final'] ?? 0)
            ];
        }
    }

    $contratosSemEquipamento = array_slice($contratosSemEquipamento, 0, 10);

    // ==========================
    // CONTRATOS SEM CUSTO MENSAL
    // ==========================
    $contratosComCustoMensal = [];

    if (tabelaExisteDashboard($connViaprofit, 'custos_mensais_contrato')) {
        $stmt = $connViaprofit->prepare("
            SELECT DISTINCT numero_contrato
            FROM custos_mensais_contrato
            WHERE ativo = 1
        ");
        $stmt->execute();
        $lista = $stmt->fetchAll();

        foreach ($lista as $item) {
            $contratosComCustoMensal[$item['numero_contrato']] = true;
        }
    }

    $contratosSemCustoMensal = [];

    foreach ($contratosAtivos as $contrato) {
        if (empty($contratosComCustoMensal[$contrato['numero']])) {
            $contratosSemCustoMensal[] = [
                'numero' => $contrato['numero'],
                'cliente' => $contrato['nome'],
                'cpf' => $contrato['cpf'],
                'valor_final' => floatval($contrato['valor_final'] ?? 0)
            ];
        }
    }

    $contratosSemCustoMensal = array_slice($contratosSemCustoMensal, 0, 10);

    jsonResponse(true, 'Dashboard geral calculado com sucesso.', [
        'referencia' => $referencia,

        'linha_1_visao_financeira' => [
            'receita_recebida' => $receitaRecebidaMes,
            'receita_prevista' => $receitaPrevistaMes,
            'em_aberto' => $emAbertoMes,
            'vencido' => $vencidoMes,
            'lucro_estimado' => $lucroEstimado
        ],

        'linha_2_saude_contratos' => [
            'contratos_ativos' => $totalContratosAtivos,
            'contratos_lucrativos' => $contratosLucrativos,
            'contratos_em_prejuizo' => $contratosPrejuizo,
            'contratos_em_payback' => $contratosPayback,
            'ticket_medio' => $ticketMedio
        ],

        'linha_3_custos' => [
            'rede_neutra_total' => $redeNeutraTotal,
            'impostos_estimados' => $impostosEstimados,
            'taxas_pix_boleto' => $taxasPixBoleto,
            'taxas_pix' => $taxasPix,
            'taxas_boleto' => $taxasBoleto,
            'custos_unicos_mes' => $custosUnicosMes,
            'custos_gerais_rateados' => $custosGeraisRateados,
            'custos_mensais_contratos' => $custosMensaisContratoTotal,
            'custo_total_estimado' => $custoTotalEstimado
        ],

        'linha_4_ranking' => [
            'top_10_melhores_contratos' => $topMelhores,
            'top_10_piores_contratos' => $topPiores,
            'contratos_sem_equipamento' => $contratosSemEquipamento,
            'contratos_sem_custo_mensal' => $contratosSemCustoMensal
        ]
    ]);
}

function buscarCustosUnicosContrato($connViaprofit, $numeroContrato)
{
    if (!tabelaExisteDashboard($connViaprofit, 'custos_contrato')) {
        return 0;
    }

    $stmt = $connViaprofit->prepare("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM custos_contrato
        WHERE numero_contrato = :numero
    ");
    $stmt->execute([
        ':numero' => $numeroContrato
    ]);

    return floatval($stmt->fetch()['total'] ?? 0);
}

function buscarCustoMensalContrato($connViaprofit, $numeroContrato)
{
    if (!tabelaExisteDashboard($connViaprofit, 'custos_mensais_contrato')) {
        return 0;
    }

    $stmt = $connViaprofit->prepare("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM custos_mensais_contrato
        WHERE numero_contrato = :numero
        AND ativo = 1
    ");
    $stmt->execute([
        ':numero' => $numeroContrato
    ]);

    return floatval($stmt->fetch()['total'] ?? 0);
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