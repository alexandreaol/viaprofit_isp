# Proposta de API de monitoramento

Este documento define um contrato inicial. A API ainda não está implementada.

## Objetivos

- Distinguir processo ativo de aplicação pronta para atender.
- Medir a dependência do MySQL sem expor dados ou credenciais.
- Permitir healthchecks do Docker e alertas externos.
- Responder rapidamente, inclusive quando uma dependência estiver degradada.

## Endpoints propostos

### `GET /internal/health/live`

Confirma apenas que o processo PHP atende. Não consulta o banco.

```json
{
  "status": "ok",
  "service": "viasistemas-app",
  "timestamp": "2026-07-28T13:12:56-03:00"
}
```

### `GET /internal/health/ready`

Verifica se a aplicação está pronta, agregando dependências essenciais.

```json
{
  "status": "degraded",
  "checks": {
    "php": "ok",
    "database": "slow"
  },
  "database_latency_ms": 2840,
  "timestamp": "2026-07-28T13:12:56-03:00"
}
```

### `GET /internal/health/database`

Executa uma conexão com timeout curto e uma consulta constante, como `SELECT
1`. Não deve retornar host, banco, usuário, SQL de negócio ou mensagem bruta do
driver.

### `GET /internal/health/workers`

Expõe métricas sanitizadas do PHP-FPM quando disponíveis: limite, workers
ativos, ociosos e fila. O status do PHP-FPM deve ser acessível somente pela rede
interna.

## Status HTTP

| Condição | HTTP |
|---|---:|
| Saudável | 200 |
| Degradado, mas capaz de atender | 200 |
| Dependência essencial indisponível | 503 |
| Erro inesperado do healthcheck | 500 |

## Requisitos de segurança

- `live` pode ser público se retornar somente os campos definidos.
- Endpoints detalhados devem ser restritos por rede, autenticação ou ambos.
- Nunca retornar exceções, DSN, credenciais, IP interno ou conteúdo do `.env`.
- Aplicar rate limit e registrar somente status e duração.

## Timeouts e orçamento

- `live`: até 200 ms e sem dependências.
- `ready`: até 2 s no total.
- teste do banco: timeout de conexão entre 1 e 2 s.
- uma falha do healthcheck não pode ocupar indefinidamente um worker.

## Monitoramento externo sugerido

- Consultar `live` a cada 30 segundos.
- Consultar `ready` a cada 60 segundos.
- Alertar após três falhas consecutivas.
- Alertar latência do banco acima de 1 segundo.
- Registrar disponibilidade e percentis de latência, não apenas média.

