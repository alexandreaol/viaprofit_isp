# Arquitetura e diretórios

## Repositório histórico

Este repositório representa a primeira implementação isolada do ViaProfit ISP:

```text
rentabilidade-isp/
├── api/                       # API PHP original
├── frontend/                  # HTML e JavaScript originais
└── u308598921_via_profit.sql  # esquema histórico
```

Não foi encontrada uma implantação dessa árvore na VPS em 28/07/2026.

## Aplicação atualmente publicada

O sistema operacional atual está no repositório ViaSistemas:

```text
/var/www/viasistemas/
├── cadastro/     # cadastro, contratos, equipamentos e funções ISP
├── cliente/      # portal do assinante
├── financeiro/   # indicadores e rotinas financeiras
├── config/       # configurações compartilhadas
└── docker/       # configuração dos containers
```

Arquivos ligados ao domínio ISP encontrados no histórico do ViaSistemas:

```text
cadastro/api/contrato_custos.php
cadastro/api/contrato_equipamentos.php
cadastro/api/equipamentos.php
cadastro/api/empresa_custos.php
cadastro/frontend/cadastro_equipamentos.html
cadastro/frontend/empresa_custos.html
```

## Fluxo de uma requisição

```text
Internet
  -> Nginx da VPS (HTTPS)
  -> 127.0.0.1:8082
  -> container viasistemas_web (Nginx)
  -> container viasistemas_app:9000 (PHP-FPM)
  -> MySQL remoto da Hostinger
```

Os dois containers montam `/var/www/viasistemas` em `/var/www/html`.

## Endereços observados

- Aplicação administrativa: `https://vianetminas.conectabrasil.online/cadastro/frontend/`
- Portal do cliente: `https://cliente.vianetminas.com.br/cliente/`
- Host MySQL observado: `srv1435.hstgr.io`

Esses valores devem ser validados após qualquer mudança de infraestrutura.

