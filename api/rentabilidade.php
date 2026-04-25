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

    $sqlContrato = "SELECT 
                        c.id,
                        c.numero,
                        c.valor,
                        c.valor_plano,
                        c.desconto,
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

    $valorBruto = floatval($contrato['valor'] ?? $contrato['valor_plano'] ?? 0);
    $desconto = floatval($contrato['desconto'] ?? 0);
    $valorMensal = max(0, $valorBruto - $desconto);

    // ==========================
    // RECEBIMENTOS QUITADOS
    // ==========================
    $sqlRecebimentos = "SELECT 
                            id,
                            competencia,
                            referencia,
                            valor_original,
                            valor_desconto,
                            valor_final,
                            valor_recebido,
                            emitir_boleto,
                            status
                        FROM recebimentos
                        WHERE id_contrato = :id_contrato
                        AND status = 'quitado'
                        ORDER BY competencia ASC, id ASC";

    $stmt = $connSistema->prepare($sqlRecebimentos);
    $stmt->execute([
        ':id_contrato' => $idContrato
    ]);

    $recebimentos = $stmt->fetchAll();

    $totalReceita = 0;
    $totalImposto = 0;
    $totalTaxaPix = 0;
    $totalTaxaBoleto = 0;
    $mesesPagos = count($recebimentos);

    $competenciasPagas = [];

    foreach ($recebimentos as $recebimento) {
        $valorRecebido = floatval($recebimento['valor_recebido'] ?? 0);
        $totalReceita += $valorRecebido;

        // Imposto: 6% sobre cada recebimento
        $totalImposto += ($valorRecebido * 0.06);

        // Como a tabela não tem forma_pagamento, usamos emitir_boleto:
        // emitir_boleto = sim => boleto
        // emitir_boleto = nao => pix
        if (($recebimento['emitir_boleto'] ?? 'nao') === 'sim') {
            $totalTaxaBoleto += 2.99;
        } else {
            $totalTaxaPix += 0.40;
        }

        if (!empty($recebimento['competencia'])) {
            $competenciasPagas[] = $recebimento['competencia'];
        }
    }

    $competenciasPagas = array_values(array_unique($competenciasPagas));

    // ==========================
    // CUSTOS ÚNICOS DO CONTRATO
    // equipamento, instalação, manutenção manual, material etc
    // ==========================
    $sqlCustosUnicos = "SELECT 
                            COALESCE(SUM(valor), 0) AS total_custo
                        FROM custos_contrato
                        WHERE numero_contrato = :numero";

    $stmt = $connViaprofit->prepare($sqlCustosUnicos);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $custosUnicos = $stmt->fetch();
    $totalCustosUnicos = floatval($custosUnicos['total_custo'] ?? 0);

    // ==========================
    // CUSTOS MENSAIS CADASTRADOS NO CONTRATO
    // Ex: sistema específico, suporte, repasse extra etc
    // ==========================
    $totalCustoMensalContrato = 0;
    $totalCustoMensalContratoAcumulado = 0;

    if (tabelaExiste($connViaprofit, 'custos_mensais_contrato')) {
        $sqlCustosMensais = "SELECT 
                                COALESCE(SUM(valor), 0) AS total_mensal
                             FROM custos_mensais_contrato
                             WHERE numero_contrato = :numero
                             AND ativo = 1";

        $stmt = $connViaprofit->prepare($sqlCustosMensais);
        $stmt->execute([
            ':numero' => $numero
        ]);

        $custosMensais = $stmt->fetch();
        $totalCustoMensalContrato = floatval($custosMensais['total_mensal'] ?? 0);
        $totalCustoMensalContratoAcumulado = $totalCustoMensalContrato * $mesesPagos;
    }

    // ==========================
    // REDE NEUTRA
    // R$ 23,50 por contrato/mês pago
    // ==========================
    $custoRedeNeutraMensal = 23.50;
    $totalRedeNeutra = $custoRedeNeutraMensal * $mesesPagos;

    // ==========================
    // CUSTOS GERAIS MENSAIS RATEADOS
    // Ex: link, sistema, técnico Mikrotik etc.
    // Divide entre contratos ativos.
    // ==========================
    $totalCustosGeraisRateados = 0;

    if (tabelaExiste($connViaprofit, 'custos_gerais_mensais') && !empty($competenciasPagas)) {
        $totalCustosGeraisRateados = calcularCustosGeraisRateados(
            $connSistema,
            $connViaprofit,
            $competenciasPagas
        );
    }

    // ==========================
    // EQUIPAMENTOS VINCULADOS
    // ==========================
    $sqlEquipamentos = "SELECT 
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
                        INNER JOIN equipamentos e ON e.id = ei.equipamento_id
                        WHERE ei.numero_contrato = :numero
                        ORDER BY ei.id DESC";

    $stmt = $connViaprofit->prepare($sqlEquipamentos);
    $stmt->execute([
        ':numero' => $numero
    ]);

    $equipamentos = $stmt->fetchAll();

    // ==========================
    // MANUTENÇÕES
    // ==========================
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

    // ==========================
    // CÁLCULO FINAL
    // ==========================
    $totalTaxas = $totalTaxaPix + $totalTaxaBoleto;

    $totalCusto =
        $totalCustosUnicos +
        $totalCustoMensalContratoAcumulado +
        $totalRedeNeutra +
        $totalImposto +
        $totalTaxas +
        $totalCustosGeraisRateados;

    $lucroTotal = $totalReceita - $totalCusto;

    $lucroMensalMedio = $mesesPagos > 0 ? ($lucroTotal / $mesesPagos) : 0;

    // Lucro mensal estimado do contrato daqui para frente
    // Considera mensalidade final, rede neutra, custos mensais cadastrados,
    // imposto estimado e taxa Pix padrão de R$ 0,40.
    $impostoMensalEstimado = $valorMensal * 0.06;
    $taxaPagamentoMensalEstimada = 0.40;
    $lucroMensalEstimado = $valorMensal
        - $custoRedeNeutraMensal
        - $totalCustoMensalContrato
        - $impostoMensalEstimado
        - $taxaPagamentoMensalEstimada;

    // Payback usando custos únicos dividido pelo lucro mensal estimado
    $payback = $lucroMensalEstimado > 0 ? ($totalCustosUnicos / $lucroMensalEstimado) : 0;

    $statusRentabilidade = 'empate';

    if ($lucroTotal > 0) {
        $statusRentabilidade = 'lucro';
    } elseif ($lucroTotal < 0) {
        $statusRentabilidade = 'prejuizo';
    }

    $simulacao12Meses = gerarSimulacao12Meses();

    jsonResponse(true, 'Rentabilidade calculada', [
        'contrato' => [
            'id' => $contrato['id'],
            'numero' => $contrato['numero'],
            'cliente' => $contrato['nome'],
            'valor_bruto' => $valorBruto,
            'desconto' => $desconto,
            'valor_mensal' => $valorMensal,
            'status_contrato' => $contrato['status_contrato']
        ],
        'resumo' => [
            'receita_total' => $totalReceita,

            'custo_total' => $totalCusto,
            'custos_unicos' => $totalCustosUnicos,

            'custo_mensal_contrato' => $totalCustoMensalContrato,
            'custos_mensais' => $totalCustoMensalContratoAcumulado,

            'rede_neutra_mensal' => $custoRedeNeutraMensal,
            'rede_neutra' => $totalRedeNeutra,

            'impostos' => $totalImposto,
            'taxas_pix' => $totalTaxaPix,
            'taxas_boleto' => $totalTaxaBoleto,
            'taxas_total' => $totalTaxas,

            'custos_gerais_rateados' => $totalCustosGeraisRateados,

            'lucro_total' => $lucroTotal,
            'meses_pagos' => $mesesPagos,
            'lucro_mensal_medio' => $lucroMensalMedio,
            'lucro_mensal_estimado' => $lucroMensalEstimado,
            'payback_meses' => $payback,
            'status_rentabilidade' => $statusRentabilidade
        ],
        'recebimentos' => $recebimentos,
        'equipamentos' => $equipamentos,
        'manutencoes' => $manutencoes,
        'simulacao_12_meses' => $simulacao12Meses
    ]);
}

function tabelaExiste($conn, $tabela)
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

function calcularCustosGeraisRateados($connSistema, $connViaprofit, $competenciasPagas)
{
    $totalRateado = 0;

    foreach ($competenciasPagas as $competencia) {
        $referencia = converterCompetenciaParaReferencia($competencia);

        if (empty($referencia)) {
            continue;
        }

        $stmt = $connViaprofit->prepare("
            SELECT COALESCE(SUM(valor), 0) AS total_geral
            FROM custos_gerais_mensais
            WHERE referencia = :referencia
            AND ativo = 1
        ");

        $stmt->execute([
            ':referencia' => $referencia
        ]);

        $totalGeral = floatval($stmt->fetch()['total_geral'] ?? 0);

        if ($totalGeral <= 0) {
            continue;
        }

        $stmt = $connSistema->prepare("
            SELECT COUNT(*) AS total_contratos
            FROM contratos
            WHERE status_contrato = 'ativo'
        ");

        $stmt->execute();
        $totalContratosAtivos = intval($stmt->fetch()['total_contratos'] ?? 0);

        if ($totalContratosAtivos <= 0) {
            continue;
        }

        $totalRateado += ($totalGeral / $totalContratosAtivos);
    }

    return $totalRateado;
}

function converterCompetenciaParaReferencia($competencia)
{
    // Se já vier no formato 2026-04, mantém
    if (preg_match('/^\d{4}-\d{2}$/', $competencia)) {
        return $competencia;
    }

    // Se vier 04/2026, converte para 2026-04
    if (preg_match('/^(\d{2})\/(\d{4})$/', $competencia, $m)) {
        return $m[2] . '-' . $m[1];
    }

    // Se vier 202604, converte para 2026-04
    if (preg_match('/^\d{6}$/', $competencia)) {
        return substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2);
    }

    return null;
}

function gerarSimulacao12Meses()
{
    $equipamento = 80.00;
    $custoInstalacao = 80.00;
    $valorCobradoInstalacao = 80.00;
    $redeNeutra = 23.50;
    $impostoPercentual = 0.06;
    $taxaPix = 0.40;
    $taxaBoleto = 2.99;

    $cenarios = [
        [
            'mensalidade' => 59.90,
            'forma' => 'Pix',
            'taxa' => $taxaPix
        ],
        [
            'mensalidade' => 59.90,
            'forma' => 'Boleto',
            'taxa' => $taxaBoleto
        ],
        [
            'mensalidade' => 69.90,
            'forma' => 'Pix',
            'taxa' => $taxaPix
        ],
        [
            'mensalidade' => 69.90,
            'forma' => 'Boleto',
            'taxa' => $taxaBoleto
        ],
    ];

    $resultado = [];

    foreach ($cenarios as $cenario) {
        $mensalidade = floatval($cenario['mensalidade']);
        $taxaPagamento = floatval($cenario['taxa']);
        $imposto = $mensalidade * $impostoPercentual;

        $lucroMensalRecorrente =
            $mensalidade -
            $redeNeutra -
            $imposto -
            $taxaPagamento;

        // Instalação custa R$80, mas é cobrada R$80 do cliente, então o impacto líquido é zero.
        $custoInstalacaoLiquido = $custoInstalacao - $valorCobradoInstalacao;

        $investimentoInicial = $equipamento + $custoInstalacaoLiquido;

        $payback = $lucroMensalRecorrente > 0
            ? ($investimentoInicial / $lucroMensalRecorrente)
            : 0;

        $lucro12Meses = ($lucroMensalRecorrente * 12) - $investimentoInicial;

        $resultado[] = [
            'mensalidade' => round($mensalidade, 2),
            'forma' => $cenario['forma'],
            'lucro_mensal_recorrente' => round($lucroMensalRecorrente, 2),
            'payback_meses' => round($payback, 2),
            'lucro_12_meses' => round($lucro12Meses, 2)
        ];
    }

    return [
        'equipamento' => $equipamento,
        'custo_instalacao' => $custoInstalacao,
        'valor_cobrado_instalacao' => $valorCobradoInstalacao,
        'instalacao_impacto_liquido' => $custoInstalacao - $valorCobradoInstalacao,
        'rede_neutra' => $redeNeutra,
        'imposto_percentual' => 6,
        'taxa_pix' => $taxaPix,
        'taxa_boleto' => $taxaBoleto,
        'cenarios' => $resultado
    ];
}