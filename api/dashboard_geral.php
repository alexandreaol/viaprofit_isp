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
    $custoRedeNeutraMensal = 23.50;
    $percentualImposto = 0.06;
    $taxaBoleto = 2.99;
    $taxaPix = 0.50;

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
    // LINHA 1 - VISÃO FINANCEIRA
    // ==========================
    $stmt = $connSistema->prepare("
        SELECT
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

    $receitaPrevistaMes = $receitaPrevistaContratos;
    $emAbertoMes = floatval($recebimentosMes['em_aberto_mes'] ?? 0);
    $vencidoMes = floatval($recebimentosMes['vencido_mes'] ?? 0);

    $stmt = $connSistema->prepare("
        SELECT 
            r.id,
            r.status,
            r.valor_final,
            r.valor_recebido,
            br.forma_pagamento AS forma_pagamento_baixa,
            br.valor_pago AS valor_pago_baixa,
            cg.gateway,
            cg.forma_pagamento AS forma_pagamento_gateway,
            cg.status_local,
            cg.status_gateway
        FROM recebimentos r
        LEFT JOIN baixas_recebimentos br ON br.id_recebimento = r.id
        LEFT JOIN cobrancas_gateway cg ON cg.id_recebimento = r.id
        WHERE r.data_vencimento BETWEEN :inicio AND :fim
        AND r.status NOT IN ('cancelado','excluido')
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

            if (empty($recebimentosAgrupados[$id]['valor_pago_baixa']) && !empty($linha['valor_pago_baixa'])) {
                $recebimentosAgrupados[$id]['valor_pago_baixa'] = $linha['valor_pago_baixa'];
            }

            if (empty($recebimentosAgrupados[$id]['gateway']) && !empty($linha['gateway'])) {
                $recebimentosAgrupados[$id]['gateway'] = $linha['gateway'];
            }

            if (empty($recebimentosAgrupados[$id]['status_local']) && !empty($linha['status_local'])) {
                $recebimentosAgrupados[$id]['status_local'] = $linha['status_local'];
            }

            if (empty($recebimentosAgrupados[$id]['status_gateway']) && !empty($linha['status_gateway'])) {
                $recebimentosAgrupados[$id]['status_gateway'] = $linha['status_gateway'];
            }
        }
    }

    $receitaRecebidaMes = 0;
    $impostosEstimados = 0;
    $taxasPix = 0;
    $taxasBoleto = 0;

    foreach ($recebimentosAgrupados as $recebimento) {
        $statusRecebimento = $recebimento['status'] ?? '';
        $valorRecebido = floatval($recebimento['valor_recebido'] ?? 0);
        $valorBaixa = floatval($recebimento['valor_pago_baixa'] ?? 0);
        $valorPrevisto = floatval($recebimento['valor_final'] ?? 0);
        $recebidoViaBaixaManual = $valorBaixa > 0;
        $recebidoViaGateway = gatewayConfirmadoDashboard($recebimento);
        $valorBaixado = max($valorBaixa, $valorRecebido);
        $foiRecebido = $recebidoViaBaixaManual || $recebidoViaGateway || $statusRecebimento === 'quitado' || $statusRecebimento === 'parcial' || $valorBaixado > 0;
        $valorReceita = $valorBaixado > 0 ? $valorBaixado : $valorPrevisto;
        $valorBaseImposto = $foiRecebido ? $valorReceita : $valorPrevisto;

        if ($foiRecebido) {
            $receitaRecebidaMes += $valorReceita;
        }

        $impostosEstimados += $valorBaseImposto * $percentualImposto;

        $formaPagamento = obterFormaPagamentoDashboard($recebimento);

        if ($formaPagamento === 'boleto') {
            $taxasBoleto += $taxaBoleto;
        } elseif ($formaPagamento === 'pix') {
            $taxasPix += $taxaPix;
        }
    }

    // ==========================
    // CUSTOS
    // ==========================
    $redeNeutraTotal = $totalContratosAtivos * $custoRedeNeutraMensal;

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

    $lucroEstimado = $receitaPrevistaMes - $custoTotalEstimado;
    $lucroRealizadoParcial = $receitaRecebidaMes - $custoTotalEstimado;

    // ==========================
    // SAÚDE DOS CONTRATOS / RANKINGS
    // ==========================
    $contratosLucrativos = 0;
    $contratosPrejuizo = 0;
    $contratosPrejuizoMais4Meses = 0;
    $contratosPrejuizoMais6Meses = 0;
    $contratosPayback = 0;

    $rankingContratos = [];

    foreach ($contratosAtivos as $contrato) {
        $numero = $contrato['numero'];
        $valorMensal = floatval($contrato['valor_final'] ?? 0);

        $custosUnicosContrato = buscarCustosUnicosContrato($connViaprofit, $numero);
        $custoMensalContrato = buscarCustoMensalContrato($connViaprofit, $numero);

        $saudeContrato = calcularSaudeContratoDashboard(
            $valorMensal,
            $custoMensalContrato,
            $custosUnicosContrato,
            $custoRedeNeutraMensal,
            $percentualImposto,
            $taxaPix
        );

        $lucroMensalProjetado = $saudeContrato['lucro_mensal_projetado'];
        $saudeRealContrato = calcularSaudeRealContratoDashboard(
            $connSistema,
            $numero,
            $contrato['id'],
            $custosUnicosContrato,
            $custoMensalContrato,
            $custoRedeNeutraMensal,
            $percentualImposto,
            $taxaPix,
            $taxaBoleto
        );
        $lucroClassificacao = $saudeRealContrato['tem_resultado_real']
            ? $saudeRealContrato['lucro_total_real']
            : $lucroMensalProjetado;
        $paybackAcimaLimite = $saudeContrato['payback_meses'] > 6;

        $estaEmPrejuizo = $lucroClassificacao < 0 || $paybackAcimaLimite;

        if (!$estaEmPrejuizo && $lucroClassificacao > 0) {
            $contratosLucrativos++;
        } else {
            $contratosPrejuizo++;
        }

        if ($estaEmPrejuizo && $saudeRealContrato['meses_pagos'] > 4) {
            $contratosPrejuizoMais4Meses++;
        }

        if ($estaEmPrejuizo && $saudeRealContrato['meses_pagos'] > 6) {
            $contratosPrejuizoMais6Meses++;
        }

        if ($saudeContrato['tem_payback']) {
            $contratosPayback++;
        }

        $rankingContratos[] = [
            'numero' => $numero,
            'cliente' => $contrato['nome'],
            'cpf' => $contrato['cpf'],
            'valor_final' => $valorMensal,
            'custos_unicos' => $custosUnicosContrato,
            'custo_mensal_contrato' => $custoMensalContrato,
            'custo_rede_neutra' => $saudeContrato['custo_rede_neutra'],
            'imposto_mensal' => $saudeContrato['imposto_mensal'],
            'taxa_pagamento' => $saudeContrato['taxa_pagamento'],
            'custo_total_mensal_projetado' => $saudeContrato['custo_total_mensal_projetado'],
            'payback_meses' => $saudeContrato['payback_meses'],
            'payback_acima_limite' => $paybackAcimaLimite,
            'lucro_mensal_projetado' => $lucroMensalProjetado,
            'tem_resultado_real' => $saudeRealContrato['tem_resultado_real'],
            'receita_real' => $saudeRealContrato['receita_real'],
            'custo_total_real' => $saudeRealContrato['custo_total_real'],
            'lucro_total_real' => $saudeRealContrato['lucro_total_real'],
            'meses_pagos' => $saudeRealContrato['meses_pagos']
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
            'lucro_estimado' => $lucroEstimado,
            'lucro_realizado_parcial' => $lucroRealizadoParcial
        ],

        'linha_2_saude_contratos' => [
            'contratos_ativos' => $totalContratosAtivos,
            'contratos_lucrativos' => $contratosLucrativos,
            'contratos_em_prejuizo' => $contratosPrejuizo,
            'contratos_prejuizo_mais_4_meses' => $contratosPrejuizoMais4Meses,
            'contratos_prejuizo_mais_6_meses' => $contratosPrejuizoMais6Meses,
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
    $total = 0;

    if (tabelaExisteDashboard($connViaprofit, 'custos_contrato')) {
        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor), 0) AS total
            FROM custos_contrato
            WHERE numero_contrato = :numero
        ");
        $stmt->execute([
            ':numero' => $numeroContrato
        ]);

        $total += floatval($stmt->fetch()['total'] ?? 0);
    }

    if (tabelaExisteDashboard($connViaprofit, 'equipamentos_instalados')) {
        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor_usado_no_calculo + custo_instalacao), 0) AS total
            FROM equipamentos_instalados
            WHERE numero_contrato = :numero
            AND status = 'instalado'
        ");
        $stmt->execute([
            ':numero' => $numeroContrato
        ]);

        $totalEquipamentos = floatval($stmt->fetch()['total'] ?? 0);

        // Quando o vínculo já gerou custos_contrato, evita contar o investimento duas vezes.
        if ($total <= 0) {
            $total += $totalEquipamentos;
        }
    }

    return $total;
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

function calcularSaudeContratoDashboard(
    $valorMensal,
    $custoMensalContrato,
    $custosUnicosContrato,
    $custoRedeNeutraMensal,
    $percentualImposto,
    $taxaPagamentoPadrao
) {
    $impostoMensal = $valorMensal * $percentualImposto;

    $custoTotalMensalProjetado =
        $custoRedeNeutraMensal +
        $impostoMensal +
        $taxaPagamentoPadrao +
        $custoMensalContrato;

    $lucroMensalProjetado = $valorMensal - $custoTotalMensalProjetado;

    $paybackMeses = ($custosUnicosContrato > 0 && $lucroMensalProjetado > 0)
        ? ($custosUnicosContrato / $lucroMensalProjetado)
        : 0;

    return [
        'custo_rede_neutra' => $custoRedeNeutraMensal,
        'imposto_mensal' => $impostoMensal,
        'taxa_pagamento' => $taxaPagamentoPadrao,
        'custo_mensal_contrato' => $custoMensalContrato,
        'custo_total_mensal_projetado' => $custoTotalMensalProjetado,
        'lucro_mensal_projetado' => $lucroMensalProjetado,
        'payback_meses' => $paybackMeses,
        'tem_payback' => $paybackMeses > 0
    ];
}

function calcularSaudeRealContratoDashboard(
    $connSistema,
    $numeroContrato,
    $idContrato,
    $custosUnicosContrato,
    $custoMensalContrato,
    $custoRedeNeutraMensal,
    $percentualImposto,
    $taxaPix,
    $taxaBoleto
) {
    $stmt = $connSistema->prepare("
        SELECT
            r.id,
            r.status,
            r.valor_final,
            r.valor_recebido,
            br.forma_pagamento AS forma_pagamento_baixa,
            br.valor_pago AS valor_pago_baixa,
            cg.gateway,
            cg.forma_pagamento AS forma_pagamento_gateway,
            cg.status_local,
            cg.status_gateway
        FROM recebimentos r
        LEFT JOIN baixas_recebimentos br ON br.id_recebimento = r.id
        LEFT JOIN cobrancas_gateway cg ON cg.id_recebimento = r.id
        WHERE r.id_contrato = :id_contrato
        AND r.status NOT IN ('cancelado','excluido')
    ");
    $stmt->execute([
        ':id_contrato' => $idContrato
    ]);

    $recebimentosAgrupados = agruparRecebimentosDashboard($stmt->fetchAll());

    $receitaReal = 0;
    $totalImposto = 0;
    $totalTaxas = 0;
    $mesesPagos = 0;

    foreach ($recebimentosAgrupados as $recebimento) {
        $statusRecebimento = $recebimento['status'] ?? '';
        $valorRecebido = floatval($recebimento['valor_recebido'] ?? 0);
        $valorBaixa = floatval($recebimento['valor_pago_baixa'] ?? 0);
        $valorPrevisto = floatval($recebimento['valor_final'] ?? 0);
        $valorBaixado = max($valorBaixa, $valorRecebido);
        $foiRecebido = $valorBaixado > 0
            || $statusRecebimento === 'quitado'
            || $statusRecebimento === 'parcial'
            || gatewayConfirmadoDashboard($recebimento);

        if (!$foiRecebido) {
            continue;
        }

        $valorReceita = $valorBaixado > 0 ? $valorBaixado : $valorPrevisto;
        $receitaReal += $valorReceita;
        $totalImposto += $valorReceita * $percentualImposto;
        $mesesPagos++;

        $formaPagamento = obterFormaPagamentoDashboard($recebimento);

        if ($formaPagamento === 'boleto') {
            $totalTaxas += $taxaBoleto;
        } elseif ($formaPagamento === 'pix') {
            $totalTaxas += $taxaPix;
        }
    }

    if ($mesesPagos <= 0) {
        return [
            'tem_resultado_real' => false,
            'receita_real' => 0,
            'custo_total_real' => 0,
            'lucro_total_real' => 0,
            'meses_pagos' => 0
        ];
    }

    $custoTotalReal =
        $custosUnicosContrato +
        ($custoMensalContrato * $mesesPagos) +
        ($custoRedeNeutraMensal * $mesesPagos) +
        $totalImposto +
        $totalTaxas;

    return [
        'tem_resultado_real' => true,
        'receita_real' => $receitaReal,
        'custo_total_real' => $custoTotalReal,
        'lucro_total_real' => $receitaReal - $custoTotalReal,
        'meses_pagos' => $mesesPagos
    ];
}

function agruparRecebimentosDashboard($linhas)
{
    $recebimentosAgrupados = [];

    foreach ($linhas as $linha) {
        $id = $linha['id'];

        if (!isset($recebimentosAgrupados[$id])) {
            $recebimentosAgrupados[$id] = $linha;
            continue;
        }

        foreach ([
            'forma_pagamento_baixa',
            'valor_pago_baixa',
            'gateway',
            'forma_pagamento_gateway',
            'status_local',
            'status_gateway'
        ] as $campo) {
            if (empty($recebimentosAgrupados[$id][$campo]) && !empty($linha[$campo])) {
                $recebimentosAgrupados[$id][$campo] = $linha[$campo];
            }
        }
    }

    return $recebimentosAgrupados;
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

function gatewayConfirmadoDashboard($recebimento)
{
    $statusLocal = normalizarStatusDashboard($recebimento['status_local'] ?? '');
    $statusGateway = normalizarStatusDashboard($recebimento['status_gateway'] ?? '');

    $statusConfirmados = [
        'pago',
        'paid',
        'quitado',
        'confirmed',
        'confirmado',
        'completed',
        'complete',
        'concluido',
        'concluida',
        'liquidado',
        'received',
        'recebido'
    ];

    return in_array($statusLocal, $statusConfirmados, true)
        || in_array($statusGateway, $statusConfirmados, true);
}

function normalizarStatusDashboard($status)
{
    $status = strtolower(trim($status));

    return str_replace(
        ['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'],
        ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'],
        $status
    );
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
