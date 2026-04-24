const API_EQUIPAMENTOS = '../api/equipamentos.php';

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const contrato = params.get('contrato') || localStorage.getItem('viaprofit_numero_contrato');

  if (!contrato) {
    alert('Nenhum contrato selecionado.');
    window.location.href = 'contratos.html';
    return;
  }

  document.getElementById('contratoSelecionado').textContent = contrato;
  document.getElementById('numeroContrato').value = contrato;

  const hoje = new Date().toISOString().split('T')[0];
  document.querySelector('input[name="data_instalacao"]').value = hoje;
  document.getElementById('novo_data_compra').value = hoje;

  configurarModoEquipamento();
  carregarEquipamentos();
});

function configurarModoEquipamento() {
  const radios = document.querySelectorAll('input[name="modoEquipamento"]');

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      const modo = document.querySelector('input[name="modoEquipamento"]:checked').value;

      document.getElementById('blocoEquipamentoExistente').classList.toggle('hidden', modo !== 'existente');
      document.getElementById('blocoNovoEquipamento').classList.toggle('hidden', modo !== 'novo');

      if (modo === 'novo') {
        document.getElementById('equipamento_id').value = '';
      }
    });
  });
}

async function carregarEquipamentos() {
  const select = document.getElementById('equipamento_id');

  try {
    const response = await fetch(`${API_EQUIPAMENTOS}?action=listar`);
    const resultado = await response.json();

    if (!resultado.success) {
      select.innerHTML = '<option value="">Erro ao carregar equipamentos</option>';
      return;
    }

    const equipamentos = resultado.data.filter(eq => eq.status === 'estoque');

    if (!equipamentos.length) {
      select.innerHTML = '<option value="">Nenhum equipamento em estoque</option>';
      return;
    }

    select.innerHTML = '<option value="">Selecione</option>' + equipamentos.map(eq => `
      <option value="${eq.id}" data-valor="${eq.valor_compra || 0}">
        ${eq.tipo || ''} - ${eq.marca || ''} ${eq.modelo || ''} | Serial: ${eq.serial || 'sem serial'} | R$ ${formatarValor(eq.valor_compra)}
      </option>
    `).join('');

    select.addEventListener('change', () => {
      const option = select.options[select.selectedIndex];
      const valor = option.getAttribute('data-valor') || 0;
      document.getElementById('valor_usado_no_calculo').value = valor;
    });

  } catch (erro) {
    console.error(erro);
    select.innerHTML = '<option value="">Erro de comunicação</option>';
  }
}

document.getElementById('formVinculo').addEventListener('submit', async (e) => {
  e.preventDefault();

  const modo = document.querySelector('input[name="modoEquipamento"]:checked').value;
  let equipamentoId = '';

  if (modo === 'existente') {
    equipamentoId = document.getElementById('equipamento_id').value;

    if (!equipamentoId) {
      alert('Selecione um equipamento em estoque.');
      return;
    }
  }

  if (modo === 'novo') {
    equipamentoId = await cadastrarNovoEquipamento();

    if (!equipamentoId) {
      return;
    }
  }

  await vincularEquipamento(equipamentoId);
});

async function cadastrarNovoEquipamento() {
  const tipo = document.getElementById('novo_tipo').value.trim();

  if (!tipo) {
    alert('Informe o tipo do equipamento.');
    return null;
  }

  const valorCompra = document.getElementById('novo_valor_compra').value || 0;

  const formData = new FormData();
  formData.append('action', 'cadastrar');
  formData.append('tipo', tipo);
  formData.append('marca', document.getElementById('novo_marca').value);
  formData.append('modelo', document.getElementById('novo_modelo').value);
  formData.append('serial', document.getElementById('novo_serial').value);
  formData.append('mac', document.getElementById('novo_mac').value);
  formData.append('patrimonio', document.getElementById('novo_patrimonio').value);
  formData.append('valor_compra', valorCompra);
  formData.append('data_compra', document.getElementById('novo_data_compra').value);
  formData.append('status', 'estoque');
  formData.append('observacao', document.getElementById('novo_observacao').value);

  try {
    const response = await fetch(API_EQUIPAMENTOS, {
      method: 'POST',
      body: formData
    });

    const resultado = await response.json();

    if (!resultado.success) {
      alert(resultado.message || 'Erro ao cadastrar equipamento.');
      return null;
    }

    document.getElementById('valor_usado_no_calculo').value = valorCompra;

    return resultado.data.id;

  } catch (erro) {
    console.error(erro);
    alert('Erro ao cadastrar novo equipamento.');
    return null;
  }
}

async function vincularEquipamento(equipamentoId) {
  const form = document.getElementById('formVinculo');
  const formData = new FormData(form);

  formData.set('equipamento_id', equipamentoId);
  formData.append('action', 'vincular');
  formData.append('status', 'instalado');

  try {
    const response = await fetch(API_EQUIPAMENTOS, {
      method: 'POST',
      body: formData
    });

    const resultado = await response.json();

    if (!resultado.success) {
      alert(resultado.message || 'Erro ao vincular equipamento.');
      return;
    }

    alert('Equipamento vinculado com sucesso!');
    window.location.href = 'contratos.html';

  } catch (erro) {
    console.error(erro);
    alert('Erro ao comunicar com o servidor.');
  }
}

function formatarValor(valor) {
  return parseFloat(valor || 0).toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}