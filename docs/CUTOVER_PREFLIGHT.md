# Cutover preflight

## Prémios mensais

- Confirmar que o fecho mensal administrativo está acessível apenas a administradores autenticados.
- Confirmar que apenas meses anteriores ao mês corrente podem ser fechados.
- Confirmar que a produção é obtida no servidor e que apenas as horas são introduzidas manualmente.
- Confirmar que a pré-visualização é recalculada no momento da confirmação.
- Confirmar que não existe sobrescrita de um fecho do mesmo setor e mês.
- Confirmar que a gravação em `dbo.MonitorDePremios_Premios_Mensais` e a auditoria `monthly_prize.recorded` são atómicas.
- Confirmar que `/admin/historico-premios` apresenta o fecho gravado.
