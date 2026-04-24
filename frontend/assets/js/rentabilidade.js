const API_RENTABILIDADE = '../api/rentabilidade.php';

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const contrato = params.get('contrato') || localStorage.getItem('viaprofit_numero_contrato');

  if (contrato) {
    document.getElementById('numeroContrato').value = contrato;
    buscarRentabilidade();
  }
});

async function buscarRentabilidade() {
  const numero = document.getElementById('numeroContrato').value.trim();
  const resultadoDiv = document.getElementById('resultado');

  if (!numero) {
    alert('Informe o número do contrato.');
    return;
  }

  resultadoDiv.innerHTML = '<div class="card">Carregando...</div>';

  try {
    const response = await fetch(`${API_RENTABILIDADE}?action=contrato&numero=${encodeURIComponent(numero)}`);
    const resultado = await response.json();

    if (!resultado.success) {
      resultadoDiv.innerHTML = `<div class="card">${resultado.message}</div>`;
      return;
    }

    montarTela(resultado.data);

  } catch (erro) {
    console.error(erro);
    resultadoDiv.innerHTML = '<div class="card">Erro ao consultar rentabilidade.</div>';
  }
}

function montarTela(data) {
  const contrato = data.contrato;
  const resumo = data.resumo;
  const equipamentos = data.equipamentos || [];
  const manutencoes = data.manutencoes || [];

  const statusClass = resumo.status_rentabilidade || 'empate';

  document.getElementById('resultado').innerHTML = `
    <div class="card">
      <h2>${contrato.cliente || 'Cliente não informado'}</h2>
      <p><strong>Contrato:</strong> ${contrato.numero}</p>
      <p><strong>Status do contrato:</strong> ${contrato.status_contrato}</p>
      <p><strong>Valor sem desconto:</strong> ${moeda(contrato.valor_bruto)}</p>
      <p><strong>Desconto:</strong> ${moeda(contrato.desconto)}</p>
      <p><strong>Valor final mensal:</strong> ${moeda(contrato.valor_mensal)}</p>
    </div>

    <div class="card">
      <h2>Resumo financeiro</h2>
      <div class="grid">
        <div class="metric">
          <span>Receita recebida</span>
          <strong>${moeda(resumo.receita_total)}</strong>
        </div>

        <div class="metric">
          <span>Mensalidade sem desconto</span>
          <strong>${moeda(contrato.valor_bruto)}</strong>
        </div>

        <div class="metric">
          <span>Desconto mensal</span>
          <strong>${moeda(contrato.desconto)}</strong>
        </div>

        <div class="metric">
          <span>Mensalidade final</span>
          <strong>${moeda(contrato.valor_mensal)}</strong>
        </div>

        <div class="metric">
          <span>Custo total</span>
          <strong>${moeda(resumo.custo_total)}</strong>
        </div>

        <div class="metric">
          <span>Lucro / Prejuízo</span>
          <strong class="${statusClass}">${moeda(resumo.lucro_total)}</strong>
        </div>

        <div class="metric">
          <span>Meses pagos</span>
          <strong>${resumo.meses_pagos}</strong>
        </div>

        <div class="metric">
          <span>Lucro médio mensal</span>
          <strong class="${statusClass}">${moeda(resumo.lucro_mensal_medio)}</strong>
        </div>

        <div class="metric">
          <span>Payback estimado</span>
          <strong>${numero(resumo.payback_meses)} meses</strong>
        </div>

        <div class="metric">
          <span>Status</span>
          <strong class="${statusClass}">${statusClass.toUpperCase()}</strong>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Equipamentos vinculados</h2>
      ${montarTabelaEquipamentos(equipamentos)}
    </div>

    <div class="card">
      <h2>Manutenções</h2>
      ${montarTabelaManutencoes(manutencoes)}
    </div>
  `;
}

function montarTabelaEquipamentos(equipamentos) {
  if (!equipamentos.length) {
    return '<p>Nenhum equipamento vinculado.</p>';
  }

  return `
    <table>
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Marca/Modelo</th>
          <th>Serial</th>
          <th>Valor cálculo</th>
          <th>Custo instalação</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        ${equipamentos.map(e => `
          <tr>
            <td>${e.tipo || ''}</td>
            <td>${e.marca || ''} ${e.modelo || ''}</td>
            <td>${e.serial || ''}</td>
            <td>${moeda(e.valor_usado_no_calculo)}</td>
            <td>${moeda(e.custo_instalacao)}</td>
            <td>${e.status_vinculo || ''}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function montarTabelaManutencoes(manutencoes) {
  if (!manutencoes.length) {
    return '<p>Nenhuma manutenção registrada.</p>';
  }

  return `
    <table>
      <thead>
        <tr>
          <th>Data</th>
          <th>Tipo</th>
          <th>Técnico</th>
          <th>Custo</th>
          <th>Cobrado</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        ${manutencoes.map(m => {
          const custo = parseFloat(m.custo_tecnico || 0) + parseFloat(m.custo_material || 0);
          return `
            <tr>
              <td>${m.data_manutencao || ''}</td>
              <td>${m.tipo_manutencao || ''}</td>
              <td>${m.tecnico || ''}</td>
              <td>${moeda(custo)}</td>
              <td>${moeda(m.valor_cobrado_cliente)}</td>
              <td>${m.status || ''}</td>
            </tr>
          `;
        }).join('')}
      </tbody>
    </table>
  `;
}

function moeda(valor) {
  return parseFloat(valor || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });
}

function numero(valor) {
  return parseFloat(valor || 0).toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}