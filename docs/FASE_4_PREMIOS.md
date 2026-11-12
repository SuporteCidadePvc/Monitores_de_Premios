# Fase 4 — Prémios mensais

## Fecho mensal administrativo

A tabela `dbo.MonitorDePremios_Premios_Mensais` é alimentada exclusivamente pelo fluxo administrativo de fecho mensal disponível em `/admin/fecho-mensal`.

O administrador seleciona o setor e um mês já terminado e introduz as horas totais do setor e as horas médias individuais. A produção é sempre consultada no servidor através do `ProductionRepository`, usando o intervalo civil completo do mês no fuso horário da aplicação. As horas não são estimadas nem obtidas de outra tabela.

Antes da gravação, a aplicação apresenta uma pré-visualização da produção, do escalão, da versão da regra, do prémio global e do prémio médio individual. A confirmação explícita volta a consultar a produção e as regras ativas e recalcula o resultado; valores calculados enviados pelo browser são ignorados.

Cada setor pode ter apenas um fecho por mês. Fechos existentes não podem ser sobrescritos, editados, recalculados ou removidos por este fluxo. A gravação e a entrada `monthly_prize.recorded` em `dbo.MonitorDePremios_Auditoria` são feitas na mesma transação.

As regras do fecho são lidas diretamente da base administrativa, sem o fallback de configuração reservado ao painel público. Se a produção, as regras ou a base administrativa estiverem indisponíveis, nenhuma gravação é confirmada.
