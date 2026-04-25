const API_CONTRATOS = '../api/contratos.php';

document.addEventListener('DOMContentLoaded', () => {
  listarContratos();

  const campoBusca = document.getElementById('campoBusca');

  if (campoBusca) {
    campoBusca.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        buscarContratos();
      }
    });
  }
});

async function listarContratos() {
  carregarContratos(`${API_CONTRATOS}?action=listar`);
}

async function buscarContratos() {
  const campoBusca = document.getElementById('campoBusca');
  const busca = campoBusca ? campoBusca.value.trim() : '';

  if (!busca) {
    listarContratos();
    return;
  }

  carregarContratos(`${API_CONTRATOS}?action=buscar&busca=${encodeURIComponent(busca)}`);
}

async function carregarContratos(url) {
  const tbody = document.getElementById('listaContratos');

  if (!tbody) {
    console.error('Elemento listaContratos não encontrado.');
    return;
  }

  tbody.innerHTML = '<tr><td colspan="9">Carregando...</td></tr>';

  try {
    const response = await fetch(url);
    const resultado = await response.json();

    if (!resultado.success) {
      tbody.innerHTML = `<tr><td colspan="9">${textoSeguro(resultado.message || 'Erro ao carregar contratos.')}</td></tr>`;
      return;
    }

    const contratos = resultado.data || [];

    if (!contratos.length) {
      tbody.innerHTML = '<tr><td colspan="9">Nenhum contrato encontrado.</td></tr>';
      return;
    }

    tbody.innerHTML = contratos.map(c => montarLinhaContrato(c)).join('');

  } catch (erro) {
    console.error(erro);
    tbody.innerHTML = '<tr><td colspan="9">Erro ao carregar contratos.</td></tr>';
  }
}

function montarLinhaContrato(c) {
  const status = (c.status_contrato || '').toLowerCase();

  const valorBase = parseFloat(c.valor || c.valor_plano || 0);
  const desconto = parseFloat(c.desconto || 0);
  const valorFinal = parseFloat(c.valor_final || Math.max(valorBase - desconto, 0));

  const numeroContrato = c.numero || '';

  return `
    <tr>
      <td>${textoSeguro(numeroContrato)}</td>
      <td>${textoSeguro(c.nome || '')}</td>
      <td>${textoSeguro(c.cpf || '')}</td>
      <td>${moeda(valorBase)}</td>
      <td>${moeda(desconto)}</td>
      <td><strong>${moeda(valorFinal)}</strong></td>
      <td>${textoSeguro(c.dia_vencimento || '')}</td>
      <td><span class="status ${classeStatus(status)}">${textoSeguro(c.status_contrato || '')}</span></td>
      <td>
        <div class="acoes">
          <a class="btn btn-green" href="rentabilidade.html?contrato=${encodeURIComponent(numeroContrato)}">Rentabilidade</a>
          <a class="btn" href="vincular_equipamento.html?contrato=${encodeURIComponent(numeroContrato)}">Equipamento</a>
          <a class="btn btn-orange" href="custos.html?contrato=${encodeURIComponent(numeroContrato)}">Custos</a>
        </div>
      </td>
    </tr>
  `;
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

function classeStatus(status) {
  return String(status || '').replace(/[^a-z0-9_-]/g, '');
}

function moeda(valor) {
  return parseFloat(valor || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });
}
