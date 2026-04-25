// =======================
// LINHA 3 — CUSTOS
// =======================

function montarLinhaCustos(dados) {
  const container = document.getElementById('linhaCustos');

  if (!container) {
    console.error('Elemento linhaCustos não encontrado.');
    return;
  }

  if (!dados) {
    container.innerHTML = `
      <div class="metric">
        <span>Erro</span>
        <strong>Sem dados de custos</strong>
      </div>
    `;
    return;
  }

  container.innerHTML = `
    ${card('Rede neutra total', moeda(dados.rede_neutra_total))}
    ${card('Impostos estimados', moeda(dados.impostos_estimados))}
    ${card('Taxas Pix/Boleto', moeda(dados.taxas_pix_boleto))}
    ${card('Custos únicos do mês', moeda(dados.custos_unicos_mes))}
    ${card('Custos mensais contratos', moeda(dados.custos_mensais_contratos))}
    ${card('Custos gerais do mês', moeda(dados.custos_gerais_rateados))}
  `;
}
