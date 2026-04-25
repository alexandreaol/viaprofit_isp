// =======================
// LINHA 2 — SAÚDE DOS CONTRATOS
// =======================

function montarLinhaSaude(dados) {
  const container = document.getElementById('linhaSaudeContratos');

  if (!container) {
    console.error('Elemento linhaSaudeContratos não encontrado.');
    return;
  }

  if (!dados) {
    container.innerHTML = `
      <div class="metric">
        <span>Erro</span>
        <strong>Sem dados de contratos</strong>
      </div>
    `;
    return;
  }

  container.innerHTML = `
    ${card('Contratos ativos', dados.contratos_ativos)}
    ${card('Contratos lucrativos', dados.contratos_lucrativos, true)}
    ${card('Contratos com prejuízo', dados.contratos_em_prejuizo, false)}
    ${card('Contratos em payback', dados.contratos_em_payback)}
    ${card('Prejuízo > 4 meses', dados.contratos_prejuizo_mais_4_meses, false)}
    ${card('Prejuízo > 6 meses', dados.contratos_prejuizo_mais_6_meses, false)}
    ${card('Ticket médio', moeda(dados.ticket_medio))}
  `;
}
