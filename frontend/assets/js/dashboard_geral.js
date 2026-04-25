const API = '../api/dashboard_geral.php';

document.addEventListener('DOMContentLoaded', carregar);

async function carregar() {
  try {
    const res = await fetch(`${API}?action=resumo`);
    const json = await res.json();

    if (!json.success) {
      alert('Erro ao carregar dashboard');
      return;
    }

    montarFinanceiro(json.data.financeiro);
    montarCustos(json.data.custos);
    montarOperacional(json.data.operacional);
    montarContratos(json.data.contratos_recentes);

  } catch (e) {
    console.error(e);
    alert('Erro geral');
  }
}

function montarFinanceiro(d) {
  document.getElementById('financeiro').innerHTML = `
    ${card('Receita prevista', moeda(d.receita_mensal_prevista))}
    ${card('Recebido', moeda(d.recebido_mes))}
    ${card('Em aberto', moeda(d.aberto_mes))}
    ${card('Vencido', moeda(d.vencido_mes))}
    ${card('Lucro estimado', moeda(d.lucro_estimado_mes), d.lucro_estimado_mes >= 0)}
    ${card('Ticket médio', moeda(d.ticket_medio))}
  `;
}

function montarCustos(d) {
  document.getElementById('custos').innerHTML = `
    ${card('Rede neutra', moeda(d.rede_neutra_mes))}
    ${card('Impostos', moeda(d.impostos_mes))}
    ${card('Pix', moeda(d.taxas_pix_mes))}
    ${card('Boleto', moeda(d.taxas_boleto_mes))}
    ${card('Custos contratos', moeda(d.custos_mensais_contratos))}
    ${card('Custos gerais', moeda(d.custos_gerais_mes))}
  `;
}

function montarOperacional(d) {
  document.getElementById('operacional').innerHTML = `
    ${card('Contratos ativos', d.contratos_ativos)}
    ${card('Recebimentos', d.total_recebimentos)}
    ${card('Quitados', d.recebimentos_quitados)}
    ${card('Equipamentos', d.equipamentos_instalados)}
    ${card('Sem equipamento', d.contratos_sem_equipamento)}
  `;
}

function montarContratos(lista) {
  document.getElementById('contratos').innerHTML = lista.map(c => `
    <tr>
      <td>${c.numero}</td>
      <td>${c.nome}</td>
      <td>${moeda(c.valor_final)}</td>
    </tr>
  `).join('');
}

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