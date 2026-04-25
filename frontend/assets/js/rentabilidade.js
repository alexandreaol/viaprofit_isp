const API_RENTABILIDADE = '../api/rentabilidade.php';

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const contrato = params.get('contrato') || localStorage.getItem('viaprofit_numero_contrato');

  if (contrato) {
    document.getElementById('numeroContrato').value = contrato;
    registrarContratoSelecionado(contrato);
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

  registrarContratoSelecionado(numero);
  resultadoDiv.innerHTML = '<div class="card">Carregando...</div>';

  try {
    const response = await fetch(`${API_RENTABILIDADE}?action=contrato&numero=${encodeURIComponent(numero)}`);
    const resultado = await response.json();

    if (!resultado.success) {
      resultadoDiv.innerHTML = `<div class="card">${textoSeguro(resultado.message || 'Erro ao consultar rentabilidade.')}</div>`;
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
  const simulacao = data.simulacao_12_meses;

  const statusTexto = resumo.status_rentabilidade || 'empate';
  const statusClass = classeCss(statusTexto);

  document.getElementById('resultado').innerHTML = `
    <div class="card">
      <h2>${textoSeguro(contrato.cliente || 'Cliente não informado')}</h2>
      <p><strong>Contrato:</strong> ${textoSeguro(contrato.numero || '')}</p>
      <p><strong>Status do contrato:</strong> ${textoSeguro(contrato.status_contrato || '')}</p>
      <p><strong>Valor sem desconto:</strong> ${moeda(contrato.valor_bruto)}</p>
      <p><strong>Desconto:</strong> ${moeda(contrato.desconto)}</p>
      <p><strong>Valor final mensal:</strong> ${moeda(contrato.valor_mensal)}</p>
    </div>

    <div class="card">
      <h2>Resumo financeiro real</h2>
      <div class="grid">
        <div class="metric">
          <span>Receita recebida</span>
          <strong>${moeda(resumo.receita_total)}</strong>
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
          <span>Lucro médio real</span>
          <strong class="${statusClass}">${moeda(resumo.lucro_mensal_medio)}</strong>
        </div>

        <div class="metric">
          <span>Payback real</span>
          <strong>${numero(resumo.payback_real)} meses</strong>
        </div>

        <div class="metric">
          <span>Lucro mensal projetado</span>
          <strong>${moeda(resumo.lucro_mensal_estimado)}</strong>
        </div>

        <div class="metric">
          <span>Payback projetado</span>
          <strong>${numero(resumo.payback_meses)} meses</strong>
        </div>

        <div class="metric">
          <span>Status</span>
          <strong class="${statusClass}">${textoSeguro(statusTexto.toUpperCase())}</strong>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Detalhamento dos custos</h2>
      <div class="grid">
        <div class="metric">
          <span>Custos únicos</span>
          <strong>${moeda(resumo.custos_unicos)}</strong>
        </div>

        <div class="metric">
          <span>Rede neutra acumulada</span>
          <strong>${moeda(resumo.rede_neutra)}</strong>
        </div>

        <div class="metric">
          <span>Rede neutra mensal</span>
          <strong>${moeda(resumo.rede_neutra_mensal)}</strong>
        </div>

        <div class="metric">
          <span>Impostos 6%</span>
          <strong>${moeda(resumo.impostos)}</strong>
        </div>

        <div class="metric">
          <span>Taxas Pix</span>
          <strong>${moeda(resumo.taxas_pix)}</strong>
        </div>

        <div class="metric">
          <span>Taxas Boleto</span>
          <strong>${moeda(resumo.taxas_boleto)}</strong>
        </div>

        <div class="metric">
          <span>Custos mensais acumulados</span>
          <strong>${moeda(resumo.custos_mensais)}</strong>
        </div>

        <div class="metric">
          <span>Custos gerais rateados</span>
          <strong>${moeda(resumo.custos_gerais_rateados)}</strong>
        </div>
      </div>
    </div>

    ${montarCardSimulacao12Meses(simulacao)}

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

function montarCardSimulacao12Meses(simulacao) {
  if (!simulacao || !simulacao.cenarios) {
    return '';
  }

  return `
    <div class="card">
      <h2>Simulação de 12 meses</h2>
      <p>
        Considerando equipamento de <strong>${moeda(simulacao.equipamento)}</strong>,
        instalação custando <strong>${moeda(simulacao.custo_instalacao)}</strong> e sendo cobrada do cliente por
        <strong>${moeda(simulacao.valor_cobrado_instalacao)}</strong>, rede neutra de
        <strong>${moeda(simulacao.rede_neutra)}</strong>/mês, imposto de
        <strong>${simulacao.imposto_percentual}%</strong>,
        Pix <strong>${moeda(simulacao.taxa_pix)}</strong> e boleto
        <strong>${moeda(simulacao.taxa_boleto)}</strong>.
      </p>

      <table>
        <thead>
          <tr>
            <th>Mensalidade</th>
            <th>Forma</th>
            <th>Lucro mensal recorrente</th>
            <th>Payback equipamento</th>
            <th>Lucro líquido em 12 meses</th>
          </tr>
        </thead>
        <tbody>
          ${simulacao.cenarios.map(c => `
            <tr>
              <td>${moeda(c.mensalidade)}</td>
              <td>${textoSeguro(c.forma || '')}</td>
              <td>${moeda(c.lucro_mensal_recorrente)}</td>
              <td>${numero(c.payback_meses)} meses</td>
              <td><strong>${moeda(c.lucro_12_meses)}</strong></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
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
            <td>${textoSeguro(e.tipo || '')}</td>
            <td>${textoSeguro(`${e.marca || ''} ${e.modelo || ''}`.trim())}</td>
            <td>${textoSeguro(e.serial || '')}</td>
            <td>${moeda(e.valor_usado_no_calculo)}</td>
            <td>${moeda(e.custo_instalacao)}</td>
            <td>${textoSeguro(e.status_vinculo || '')}</td>
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
              <td>${textoSeguro(m.data_manutencao || '')}</td>
              <td>${textoSeguro(m.tipo_manutencao || '')}</td>
              <td>${textoSeguro(m.tecnico || '')}</td>
              <td>${moeda(custo)}</td>
              <td>${moeda(m.valor_cobrado_cliente)}</td>
              <td>${textoSeguro(m.status || '')}</td>
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

function registrarContratoSelecionado(contrato) {
  localStorage.setItem('viaprofit_numero_contrato', contrato);

  const query = encodeURIComponent(contrato);
  const linkCustos = document.getElementById('linkCustos');
  const linkEquipamento = document.getElementById('linkEquipamento');

  if (linkCustos) {
    linkCustos.href = `custos.html?contrato=${query}`;
  }

  if (linkEquipamento) {
    linkEquipamento.href = `vincular_equipamento.html?contrato=${query}`;
  }
}

function textoSeguro(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  })[caractere]);
}

function classeCss(valor) {
  return String(valor || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
}
