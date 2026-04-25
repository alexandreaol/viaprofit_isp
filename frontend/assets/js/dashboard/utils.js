// =======================
// FORMATAÇÕES
// =======================

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

function textoSeguro(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  })[caractere]);
}

// =======================
// CARD PADRÃO
// =======================

function card(titulo, valor, positivo = null) {
  let classe = '';

  if (positivo === true) {
    classe = 'verde';
  } else if (positivo === false) {
    classe = 'vermelho';
  }

  return `
    <div class="metric">
      <span>${textoSeguro(titulo)}</span>
      <strong class="${classe}">${textoSeguro(valor)}</strong>
    </div>
  `;
}

// =======================
// STATUS VISUAL INTELIGENTE
// =======================

function statusValor(valor) {
  const v = parseFloat(valor || 0);

  if (v > 0) return 'verde';
  if (v < 0) return 'vermelho';
  return 'amarelo';
}

// =======================
// DEBUG (OPCIONAL)
// =======================

function logDebug(label, data) {
  console.log(`[DEBUG] ${label}`, data);
}
