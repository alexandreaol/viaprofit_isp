# ViaProfit ISP

Este repositório contém a implementação PHP original do módulo de rentabilidade
para provedores. Ele deve ser tratado como **legado e referência funcional** até
que seja confirmada a sua relação com o módulo atualmente publicado na VPS.

## Situação da produção

Na VPS `srv1620644`, o sistema ISP em uso foi identificado dentro da aplicação
ViaSistemas, em `/var/www/viasistemas`. Não existe um serviço `viaprofit_isp` nem
um diretório com esse nome na VPS. A publicação atual passa pelos containers
`viasistemas_web` e `viasistemas_app`.

Consulte o [índice da documentação](docs/README.md) antes de realizar deploy,
restauração ou alteração de infraestrutura.

## Código deste repositório

- `api/`: endpoints PHP da implementação original.
- `frontend/`: páginas HTML e JavaScript da implementação original.
- `u308598921_via_profit.sql`: esquema SQL histórico do módulo.

## Segurança

O arquivo `api/config.php` histórico contém valores padrão sensíveis. Não use
essas credenciais em novos ambientes. As senhas correspondentes devem ser
rotacionadas e substituídas por configuração externa ao Git.
