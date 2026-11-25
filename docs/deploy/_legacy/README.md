# Arquivos legados de deploy

**Arquivado em:** 2026-11-25

Estes arquivos foram preservados apenas para histórico e referência de
rollback. Eles **não fazem parte do contrato de deploy** e não devem ser usados
em novas stacks do Portainer.

| Arquivo | Origem | Situação |
|---------|--------|----------|
| `docker-compose.prod.yml` | Stack antiga do Portainer (`cotag-app`/`cotag-worker`, bind mount de `./storage`) | Substituído por `docker-compose.yml` |
| `docker-compose.prod.local.yml` | Override local com MySQL descartável e porta publicada | Não faz parte do contrato |
| `docker-compose.sail.yml` | `docker-compose.yml` original do Laravel Sail | Desenvolvimento local legado |
| `DEPLOY_PORTAINER.md` | Guia antigo de deploy | Substituído por `docs/deploy/README.md` |

## Observações

- Não havia `docker-compose.db.yml` no projeto. Nenhum banco ou dado foi
  removido.
- A stack antiga de produção pode continuar em execução na infraestrutura
  existente. Ela **não foi alterada** por esta padronização.
- O banco central do ambiente é responsabilidade da infraestrutura e não é
  criado, iniciado ou gerenciado por esta stack.
- O cutover operacional (stack nova, NPM e variáveis) está descrito em
  `docs/deploy/README.md`, na seção de cutover/rollback.
