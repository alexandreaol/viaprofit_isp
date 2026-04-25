const API_CONTRATOS = '../api/contratos.php';

async function buscarContratos() {
  const busca = document.getElementById('campoBusca').value.trim();
  const tbody = document.getElementById('listaContratos');

  if (!busca) {
    alert('Digite nome, CPF ou número do contrato.');
    return;
  }

  tbody.innerHTML = '<tr><td colspan="6">Buscando...</td></tr>';

  try {
    const response = await fetch(`${API_CONTRATOS}?action=buscar&busca=${encodeURIComponent(busca)}`);
    const resultado = await response.json();

    if (!resultado.success) {
      tbody.innerHTML = `<tr><td colspan="6">${resultado.message}</td></tr>`;
      return;
    }

    if (!resultado.data.length) {
      tbody.innerHTML = '<tr><td colspan="6">Nenhum contrato encontrado.</td></tr>';
      return;
    }

    tbody.innerHTML = resultado.data.map(c => `
      <tr>
        <td>${c.numero || ''}</td>
        <td>${c.nome || ''}</td>
        <td>${c.cpf || ''}</td>
        <td>R$ ${formatarValor(c.valor || c.valor_plano || 0)}</td>
        <td><span class="status ${(c.status_contrato || '').toLowerCase()}">${c.status_contrato || ''}</span></td>
        <td>
          <button onclick="selecionarContrato('${c.numero}')">Selecionar</button>
        </td>
      </tr>
    `).join('');

  } catch (erro) {
    console.error(erro);
    tbody.innerHTML = '<tr><td colspan="6">Erro ao buscar contratos.</td></tr>';
  }
}

function selecionarContrato(numero) {
  localStorage.setItem('viaprofit_numero_contrato', numero);
  window.location.href = `rentabilidade.html?contrato=${encodeURIComponent(numero)}`;
}

function formatarValor(valor) {
  const numero = parseFloat(valor || 0);
  return numero.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

document.getElementById('campoBusca').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    buscarContratos();
  }
});
