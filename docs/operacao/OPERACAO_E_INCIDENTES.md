# Operação e recuperação de incidentes

## Verificação rápida

```bash
docker inspect viasistemas_app viasistemas_web \
  --format '{{.Name}} status={{.State.Status}} restart={{.RestartCount}}'

docker stats --no-stream viasistemas_app viasistemas_web

curl -k -sS -o /dev/null \
  -w 'FRONT HTTP=%{http_code} TEMPO=%{time_total}s\n' --max-time 10 \
  https://vianetminas.conectabrasil.online/cadastro/frontend/

curl -k -sS -o /dev/null \
  -w 'API HTTP=%{http_code} TEMPO=%{time_total}s\n' --max-time 10 \
  https://vianetminas.conectabrasil.online/cadastro/api/tenant_public.php
```

## Incidente conhecido: PHP-FPM saturado

Em 28/07/2026, as APIs passaram a retornar timeout enquanto o frontend estático
continuava com HTTP 200. O log apresentou:

```text
server reached pm.max_children setting (5)
```

Também foram observados HTTP 499 e 504. Os cinco workers estavam provavelmente
presos aguardando uma dependência, com o MySQL remoto como principal hipótese.

### Recuperação emergencial

```bash
docker restart viasistemas_app
```

Depois do restart, valide frontend e API. O restart não substitui a investigação
da causa e provoca indisponibilidade curta das APIs.

### Coleta antes do restart, quando possível

```bash
date
docker stats --no-stream viasistemas_app viasistemas_web
docker top viasistemas_app
docker logs --since 30m viasistemas_app 2>&1
docker logs --since 30m viasistemas_web 2>&1
tail -n 200 /var/log/nginx/error.log
ss -ntp | grep -E ':3306|viasistemas'
```

## Critérios de escalonamento

- API acima de 5 segundos por três verificações: investigar banco e workers.
- HTTP 499 isolado: pode ser cancelamento do cliente.
- HTTP 499 em sequência: verificar lentidão do upstream.
- HTTP 504: PHP-FPM não respondeu dentro do limite do Nginx.
- `pm.max_children`: todos os workers estão ocupados; não aumentar o limite sem
  verificar dependências lentas e timeouts ausentes.

## Melhorias recomendadas

- Definir timeout de conexão no PDO.
- Definir `request_terminate_timeout` no PHP-FPM.
- Instrumentar duração de conexão e consultas.
- Adicionar healthcheck aos containers.
- Avaliar aumento moderado de `pm.max_children` após medir consumo.
- Criar alerta antes de todos os workers ficarem ocupados.

