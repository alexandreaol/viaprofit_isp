# Manual de testes

Execute os testes após deploy, restart, mudança de banco ou alteração no Nginx.
Não use credenciais reais em histórico de shell compartilhado.

## 1. Containers

```bash
docker inspect viasistemas_app viasistemas_web \
  --format '{{.Name}} {{.State.Status}} restarts={{.RestartCount}}'
```

Esperado: ambos em `running` e sem crescimento inesperado de reinícios.

## 2. Frontend

```bash
curl -k -sS -o /dev/null \
  -w 'HTTP=%{http_code} TEMPO=%{time_total}s\n' --max-time 10 \
  https://vianetminas.conectabrasil.online/cadastro/frontend/
```

Esperado: HTTP 200 ou 304 e resposta inferior a 2 segundos.

## 3. API pública

```bash
curl -k -sS -o /dev/null \
  -w 'HTTP=%{http_code} TEMPO=%{time_total}s\n' --max-time 10 \
  https://vianetminas.conectabrasil.online/cadastro/api/tenant_public.php
```

Esperado: HTTP 200 e resposta inferior a 2 segundos.

## 4. API de sessão

```bash
curl -k -sS -o /dev/null \
  -w 'HTTP=%{http_code} TEMPO=%{time_total}s\n' --max-time 10 \
  https://vianetminas.conectabrasil.online/cadastro/api/me.php
```

Sem sessão, o corpo pode indicar usuário não autenticado, mas a API deve
responder rapidamente e sem HTTP 5xx.

## 5. Porta do MySQL remoto

Este teste verifica rede e porta, não autenticação:

```bash
docker exec viasistemas_app php -r '
$host = "srv1435.hstgr.io";
for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    $socket = @fsockopen($host, 3306, $code, $error, 3);
    $elapsed = round(microtime(true) - $start, 3);
    echo "$i ".($socket ? "OK" : "FALHA")." {$elapsed}s".PHP_EOL;
    if ($socket) fclose($socket);
    sleep(1);
}'
```

Esperado: cinco conexões bem-sucedidas, sem aproximação do timeout de 3 s.

## 6. Logs

```bash
docker logs --since 10m viasistemas_app 2>&1 \
  | grep -Ei 'max_children|warning|error|fatal|timeout'

docker logs --since 10m viasistemas_web 2>&1 \
  | grep -Ei ' 499 | 502 | 503 | 504 |timed out'
```

Esperado: nenhuma saturação ou sequência de erros.

## Registro do teste

Registre data, responsável, versão/commit, ambiente, resultados, latências e
evidências sem segredos. Marque o teste como aprovado apenas quando frontend,
API, banco e logs estiverem dentro dos critérios.

