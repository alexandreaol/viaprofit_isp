# Inventário da VPS

Dados observados na VPS `srv1620644` em 28/07/2026.

| Função | Componente | Observação |
|---|---|---|
| Proxy HTTPS | `nginx.service` | Encaminha o domínio para `127.0.0.1:8082` |
| Servidor estático | `viasistemas_web` | Nginx interno |
| Aplicação PHP | `viasistemas_app` | PHP-FPM na porta interna 9000 |
| Código | `/var/www/viasistemas` | Bind mount nos dois containers |
| Banco da aplicação | MySQL Hostinger | Host observado: `srv1435.hstgr.io` |
| Banco local auxiliar | `conectabrasil_banco` | MariaDB; não confundir com o banco remoto |

## Comandos de inventário

```bash
systemctl --type=service --state=running --no-pager \
  | grep -Ei 'nginx|apache|php.*fpm|mysql|mariadb'

docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}'

docker inspect viasistemas_app viasistemas_web \
  --format '{{.Name}}{{range .Mounts}}{{printf "\n  %s -> %s" .Source .Destination}}{{end}}'
```

## Configuração e segredos

- A configuração local observada fica em
  `/var/www/viasistemas/cadastro/api/config_local.php`.
- O carregamento e a criação do PDO ficam em
  `/var/www/viasistemas/cadastro/api/config.php`.
- Nunca copie `config_local.php`, `.env` ou saída de `docker inspect` contendo
  variáveis para chamados ou documentação pública.
- Registre apenas os nomes das variáveis:

```bash
docker exec viasistemas_app sh -lc \
  'env | cut -d= -f1 | grep -Ei "DB|MYSQL|HOST|DATABASE" | sort'
```

