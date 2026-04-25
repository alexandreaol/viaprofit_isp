// =======================
// LINHA 1 — VISÃO FINANCEIRA
// =======================

function montarLinhaFinanceira(dados) {
  const container = document.getElementById('linhaFinanceira');

  if (!container) {
    console.error('Elemento linhaFinanceira não encontrado.');
    return;
  }

  if (!dados) {
    container.innerHTML = `
      <div class="metric">
        <span>Erro</span>
        <strong>Sem dados financeiros</strong>
      </div>
    `;
    return;
  }

  container.innerHTML = `
    ${card('Receita recebida', moeda(dados.receita_recebida))}
    ${card('Receita prevista', moeda(dados.receita_prevista))}
    ${card('Em aberto', moeda(dados.em_aberto))}
    ${card('Vencido', moeda(dados.vencido), false)}
    ${card('Lucro estimado', moeda(dados.lucro_estimado), dados.lucro_estimado >= 0)}
  `;
}