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

  const dataHoje = new Date().toISOString().split('T')[0];
  document.querySelector('input[name="data_instalacao"]').value = dataHoje;

  carregarEquipamentos();
});

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
      <option value="${eq.id}">
        ${eq.tipo || ''} - ${eq.marca || ''} ${eq.modelo || ''} | Serial: ${eq.serial || 'sem serial'} | R$ ${formatarValor(eq.valor_compra)}
      </option>
    `).join('');

  } catch (erro) {
    console.error(erro);
    select.innerHTML = '<option value="">Erro de comunicação</option>';
  }
}

document.getElementById('formVinculo').addEventListener('submit', async (e) => {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
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
});

function formatarValor(valor) {
  return parseFloat(valor || 0).toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}