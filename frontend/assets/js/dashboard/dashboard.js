// =======================
// DASHBOARD GERAL — CONTROLADOR PRINCIPAL
// =======================

const API_DASHBOARD_GERAL = '../api/dashboard_geral.php';

document.addEventListener('DOMContentLoaded', () => {
  const inputReferencia = document.getElementById('referencia');

  if (inputReferencia) {
    const hoje = new Date();
    inputReferencia.value = hoje.toISOString().slice(0, 7);
  }

  carregarDashboard();
});

async function carregarDashboard() {
  const inputReferencia = document.getElementById('referencia');
  const referencia = inputReferencia ? inputReferencia.value : '';

  try {
    const url = `${API_DASHBOARD_GERAL}?action=resumo&referencia=${encodeURIComponent(referencia)}`;

    const response = await fetch(url);
    const resultado = await response.json();

    if (!resultado.success) {
      alert(resultado.message || 'Erro ao carregar dashboard.');
      return;
    }

    const data = resultado.data;

    montarLinhaFinanceira(data.linha_1_visao_financeira);
    montarLinhaSaude(data.linha_2_saude_contratos);
    montarLinhaCustos(data.linha_3_custos);
    montarRanking(data.linha_4_ranking);

  } catch (erro) {
    console.error(erro);
    alert('Erro ao comunicar com o servidor.');
  }
}