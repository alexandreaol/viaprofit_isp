// =======================
// LINHA 4 — RANKING
// =======================

function montarRanking(dados) {
  if (!dados) {
    mostrarErroRanking('Sem dados de ranking.');
    return;
  }

  const topMelhores = document.getElementById('topMelhores');
  const topPiores = document.getElementById('topPiores');
  const semEquipamento = document.getElementById('semEquipamento');
  const semCustoMensal = document.getElementById('semCustoMensal');

  if (topMelhores) {
    topMelhores.innerHTML = montarTabelaRanking(dados.top_10_melhores_contratos || []);
  }

  if (topPiores) {
    topPiores.innerHTML = montarTabelaRanking(dados.top_10_piores_contratos || []);
  }

  if (semEquipamento) {
    semEquipamento.innerHTML = montarTabelaSimples(dados.contratos_sem_equipamento || [], 'equipamento');
  }

  if (semCustoMensal) {
    semCustoMensal.innerHTML = montarTabelaSimples(dados.contratos_sem_custo_mensal || [], 'custos');
  }
}

function montarTabelaRanking(lista) {
  if (!lista.length) {
    return '<tr><td colspan="5">Nenhum dado encontrado.</td></tr>';
  }

  return lista.map(c => {
    const lucro = parseFloat(c.lucro_mensal_projetado || 0);
    const classe = lucro >= 0 ? 'verde' : 'vermelho';

    return `
      <tr>
        <td>${c.numero || ''}</td>
        <td>${c.cliente || ''}</td>
        <td>${moeda(c.valor_final)}</td>
        <td class="${classe}"><strong>${moeda(lucro)}</strong></td>
        <td>
          <a class="btn" href="rentabilidade.html?contrato=${encodeURIComponent(c.numero || '')}">
            Ver
          </a>
        </td>
      </tr>
    `;
  }).join('');
}

function montarTabelaSimples(lista, tipoAcao) {
  if (!lista.length) {
    return '<tr><td colspan="4">Nenhum dado encontrado.</td></tr>';
  }

  return lista.map(c => {
    let link = `rentabilidade.html?contrato=${encodeURIComponent(c.numero || '')}`;
    let texto = 'Ver';

    if (tipoAcao === 'equipamento') {
      link = `vincular_equipamento.html?contrato=${encodeURIComponent(c.numero || '')}`;
      texto = 'Vincular';
    }

    if (tipoAcao === 'custos') {
      link = `custos.html?contrato=${encodeURIComponent(c.numero || '')}`;
      texto = 'Custos';
    }

    return `
      <tr>
        <td>${c.numero || ''}</td>
        <td>${c.cliente || ''}</td>
        <td>${moeda(c.valor_final)}</td>
        <td>
          <a class="btn" href="${link}">
            ${texto}
          </a>
        </td>
      </tr>
    `;
  }).join('');
}

function mostrarErroRanking(msg) {
  const ids = ['topMelhores', 'topPiores', 'semEquipamento', 'semCustoMensal'];

  ids.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.innerHTML = `<tr><td colspan="5">${msg}</td></tr>`;
    }
  });
}