const API_CUSTOS = '../api/custos.php';

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const contrato = params.get('contrato') || localStorage.getItem('viaprofit_numero_contrato');

  if (!contrato) {
    alert('Nenhum contrato selecionado.');
    window.location.href = 'dashboard.html';
    return;
  }

  // Preenche dados
  localStorage.setItem('viaprofit_numero_contrato', contrato);
  document.getElementById('contratoSelecionado').textContent = contrato;
  document.getElementById('numeroContratoUnico').value = contrato;
  document.getElementById('numeroContratoMensal').value = contrato;
  document.getElementById('linkRentabilidade').href =
    `rentabilidade.html?contrato=${encodeURIComponent(contrato)}`;
  document.getElementById('linkEquipamento').href =
    `vincular_equipamento.html?contrato=${encodeURIComponent(contrato)}`;

  // Data atual
  const hoje = dataLocal();
  const campoData = document.querySelector('input[name="data_custo"]');
  if (campoData) campoData.value = hoje;

  configurarForms();
  carregarCustos(contrato);
});

function configurarForms() {
  const formUnico = document.getElementById('formCustoUnico');
  const formMensal = document.getElementById('formCustoMensal');

  if (formUnico) {
    formUnico.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(formUnico);
      formData.append('action', 'cadastrar_unico');

      await salvarCusto(formData);
    });
  }

  if (formMensal) {
    formMensal.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(formMensal);
      formData.append('action', 'cadastrar_mensal');

      await salvarCusto(formData);
    });
  }
}

async function salvarCusto(formData) {
  try {
    const response = await fetch(API_CUSTOS, {
      method: 'POST',
      body: formData
    });

    const resultado = await response.json();

    if (!resultado.success) {
      alert(resultado.message || 'Erro ao salvar custo.');
      return;
    }

    alert('Custo salvo com sucesso.');

    const contrato = formData.get('numero_contrato');
    carregarCustos(contrato);

  } catch (erro) {
    console.error(erro);
    alert('Erro ao comunicar com o servidor.');
  }
}

async function carregarCustos(contrato) {
  try {
    const response = await fetch(
      `${API_CUSTOS}?action=listar&numero_contrato=${encodeURIComponent(contrato)}`
    );

    const resultado = await response.json();

    if (!resultado.success) {
      mostrarErroCustos(resultado.message);
      return;
    }

    montarCustosUnicos(resultado.data.custos_unicos || []);
    montarCustosMensais(resultado.data.custos_mensais || []);

  } catch (erro) {
    console.error(erro);
    mostrarErroCustos('Erro ao carregar custos.');
  }
}

function mostrarErroCustos(msg) {
  const tabelaUnicos = document.getElementById('listaCustosUnicos');
  const tabelaMensais = document.getElementById('listaCustosMensais');

  if (tabelaUnicos) {
    tabelaUnicos.innerHTML = `<tr><td colspan="5">${textoSeguro(msg)}</td></tr>`;
  }

  if (tabelaMensais) {
    tabelaMensais.innerHTML = `<tr><td colspan="4">${textoSeguro(msg)}</td></tr>`;
  }
}

function montarCustosUnicos(custos) {
  const tbody = document.getElementById('listaCustosUnicos');

  if (!tbody) return;

  if (!custos.length) {
    tbody.innerHTML = '<tr><td colspan="5">Nenhum custo único cadastrado.</td></tr>';
    return;
  }

  tbody.innerHTML = custos.map(c => `
    <tr>
      <td>${textoSeguro(c.data_custo || '')}</td>
      <td>${textoSeguro(c.tipo || '')}</td>
      <td>${textoSeguro(c.descricao || '')}</td>
      <td>${moeda(c.valor)}</td>
      <td>${textoSeguro(c.origem || '')}</td>
    </tr>
  `).join('');
}

function montarCustosMensais(custos) {
  const tbody = document.getElementById('listaCustosMensais');

  if (!tbody) return;

  if (!custos.length) {
    tbody.innerHTML = '<tr><td colspan="4">Nenhum custo mensal cadastrado.</td></tr>';
    return;
  }

  tbody.innerHTML = custos.map(c => `
    <tr>
      <td>${textoSeguro(c.tipo || '')}</td>
      <td>${textoSeguro(c.descricao || '')}</td>
      <td>${moeda(c.valor)}</td>
      <td>${c.ativo == 1 ? 'Sim' : 'Não'}</td>
    </tr>
  `).join('');
}

function moeda(valor) {
  return parseFloat(valor || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });
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

function dataLocal() {
  const hoje = new Date();
  const ano = hoje.getFullYear();
  const mes = String(hoje.getMonth() + 1).padStart(2, '0');
  const dia = String(hoje.getDate()).padStart(2, '0');

  return `${ano}-${mes}-${dia}`;
}
