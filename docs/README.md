# Documentação operacional do ViaProfit ISP

Esta documentação registra a topologia observada em 28 de julho de 2026 e
separa claramente o repositório histórico da aplicação em produção.

## Leitura recomendada

1. [Arquitetura e diretórios](arquitetura/DIRETORIOS_E_MODULOS.md)
2. [Inventário da VPS](infraestrutura/INVENTARIO_VPS.md)
3. [Operação e recuperação](operacao/OPERACAO_E_INCIDENTES.md)
4. [Testes manuais](testes/TESTES_MANUAIS.md)
5. [API de monitoramento](testes/API_MONITORAMENTO.md)

## Regra de atualização

Ao mudar domínio, caminho, container, banco ou procedimento de deploy, atualize
esta documentação no mesmo commit. Nunca registre senhas, tokens, certificados
privados ou conteúdo integral de arquivos `.env`.

