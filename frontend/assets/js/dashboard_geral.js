const API = '../api/dashboard_geral.php';

document.addEventListener('DOMContentLoaded', () => {
  const inputRef = document.getElementById('referencia');

  // Define mês atual
  const hoje = new Date();
  const ano = hoje.getFullYear();
  const mes = String(hoje.getMonth() + 1).padStart(2, '0');
  inputRef.value = `${ano}-${mes}`;

  carregarDashboard();
});

async function carregarDashboard() {
  const ref = document.getElementById('referencia').value;

  try {
    const res = await fetch(`${API}?action=resumo&referencia=${ref}`);
    const json = await res.json();

    if (!json.success) {
      alert('Erro ao carregar dados');
      return;
    }

    const data = json.data;

    montarLinhaFinanceira(data.linha_1_visao_financeira);
    montarLinhaSaude(data.linha_2_saude_contratos);
    montarLinhaCustos(data.linha_3_custos);
    montarRanking(data.linha_4_ranking);

  } catch (e) {
    console.error(e);
    alert('Erro geral');
  }
}

// =======================
// LINHA 1 — FINANCEIRO
// =======================
function montarLinhaFinanceira(d) {
  document.getElementById('linhaFinanceira').innerHTML = `
    ${card('Receita recebida', moeda(d.receita_recebida))}
    ${card('Receita prevista', moeda(d.receita_prevista))}
    ${card('Em aberto', moeda(d.em_aberto))}
    ${card('Vencido', moeda(d.vencido), false)}
    ${card('Lucro estimado', moeda(d.lucro_estimado), d.lucro_estimado >= 0)}
  `;
}

// =======================
// LINHA 2 — SAÚDE
// =======================
function montarLinhaSaude(d) {
  document.getElementById('linhaSaudeContratos').innerHTML = `
    ${card('Contratos ativos', d.contratos_ativos)}
    ${card('Lucrativos', d.contratos_lucrativos, true)}
    ${card('Sem lucro / payback > 6m', d.contratos_em_prejuizo, false)}
    ${card('Com payback', d.contratos_em_payback)}
    ${card('Ticket médio', moeda(d.ticket_medio))}
  `;
}

// =======================
// LINHA 3 — CUSTOS
// =======================
function montarLinhaCustos(d) {
  document.getElementById('linhaCustos').innerHTML = `
    ${card('Rede neutra', moeda(d.rede_neutra_total))}
    ${card('Impostos', moeda(d.impostos_estimados))}
    ${card('Taxas Pix/Boleto', moeda(d.taxas_pix_boleto))}
    ${card('Custos únicos', moeda(d.custos_unicos_mes))}
    ${card('Custos mensais', moeda(d.custos_mensais_contratos))}
    ${card('Custos gerais', moeda(d.custos_gerais_rateados))}
  `;
}

// =======================
// LINHA 4 — RANKING
// =======================
function montarRanking(d) {

  document.getElementById('topMelhores').innerHTML =
    montarTabelaRanking(d.top_10_melhores_contratos);

  document.getElementById('topPiores').innerHTML =
    montarTabelaRanking(d.top_10_piores_contratos);

  document.getElementById('semEquipamento').innerHTML =
    montarTabelaSimples(d.contratos_sem_equipamento);

  document.getElementById('semCustoMensal').innerHTML =
    montarTabelaSimples(d.contratos_sem_custo_mensal);
}

function montarTabelaRanking(lista) {
  if (!lista.length) {
    return `<tr><td colspan="5">Nenhum dado</td></tr>`;
  }

  return lista.map(c => `
    <tr>
      <td>${c.numero}</td>
      <td>${c.cliente}</td>
      <td>${moeda(c.valor_final)}</td>
      <td class="${c.lucro_mensal_projetado >= 0 ? 'verde' : 'vermelho'}">
        ${moeda(c.lucro_mensal_projetado)}
      </td>
      <td>
        <a class="btn" href="rentabilidade.html?contrato=${encodeURIComponent(c.numero)}">
          Ver
        </a>
      </td>
    </tr>
  `).join('');
}

function montarTabelaSimples(lista) {
  if (!lista.length) {
    return `<tr><td colspan="4">Nenhum dado</td></tr>`;
  }

  return lista.map(c => `
    <tr>
      <td>${c.numero}</td>
      <td>${c.cliente}</td>
      <td>${moeda(c.valor_final)}</td>
      <td>
        <a class="btn" href="rentabilidade.html?contrato=${encodeURIComponent(c.numero)}">
          Ver
        </a>
      </td>
    </tr>
  `).join('');
}

// =======================
// UTIL
// =======================
function card(titulo, valor, positivo = null) {
  let cor = '';

  if (positivo !== null) {
    cor = positivo ? 'verde' : 'vermelho';
  }

  return `
    <div class="metric">
      <span>${titulo}</span>
      <strong class="${cor}">${valor}</strong>
    </div>
  `;
}

function moeda(v) {
  return parseFloat(v || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });
}
