# Monitores de Prémios

Nova aplicação interna para os painéis de produção e prémios dos setores PVC e Alumínio. O projeto é construído separadamente do sistema legado, que permanece disponível apenas para comparação e rollback.

## Estado

A fundação Laravel, o isolamento dos setores, o repository de leitura SQL, as regras de prémio no servidor, a interface, os comunicados, a administração e o cache de último valor válido foram implementados. Os testes e a homologação da Fase 9 foram concluídos. A Fase 10 (cutover) aguarda a cópia controlada do projeto para o futuro servidor `192.168.2.37`; consulte [docs/CUTOVER_PREFLIGHT.md](docs/CUTOVER_PREFLIGHT.md) antes de qualquer troca nas TVs.

A aplicação será iniciada manualmente pelo terminal com `artisan serve`; Apache não será usado nesta fase. Esta operação não cria um serviço Windows nem oferece reinício automático: após reinicialização ou paragem do processo, o operador deverá iniciá-lo novamente. A troca dos links das TVs continua fora do âmbito atual e exige autorização própria.

Consulte o [plano de implementação](Spec/PLANO_IMPLEMENTACAO.md) antes de alterar a aplicação.

## Stack inicial

- PHP 8.3;
- Laravel 13;
- Blade, CSS local e JavaScript vanilla;
- SQL Server via ODBC/PDO_ODBC, a implementar por repositories;
- regras de prémio lidas do SQL Server, com a configuração local aprovada como fallback temporário caso a ligação administrativa esteja indisponível;
- Composer 2 instalado localmente em `.tools/composer.phar`.

## Contextos disponíveis

Cada rota passa pelo middleware de contexto e mantém a navegação dentro do setor atual:

```text
/pvc
/pvc/hoje
/pvc/semana
/pvc/mes
/pvc/comunicado

/aluminio
/aluminio/hoje
/aluminio/semana
/aluminio/mes
/aluminio/comunicado
```

Os dois setores apontam para a mesma consulta parametrizada de produção. O Alumínio apresenta obrigatoriamente um aviso de dados provisórios.

## Produção via ODBC

A aplicação usa o DSN `BD_PREF` por `PDO_ODBC`. Utilizador e password são lidos exclusivamente do `.env` local. Consulte [docs/ODBC.md](docs/ODBC.md) para configuração e teste de integração.

Quando o SQL Server estiver indisponível ou recusar a autenticação, o painel responde sem detalhes técnicos e reutiliza o último snapshot de produção válido para o setor e período, identificando a respetiva data e hora. Se ainda não existir snapshot, tenta novamente no próximo carregamento.

Antes de colocar a aplicação em operação — ou depois de reiniciar o servidor ou o Application Pool do IIS — valide os dois DSNs pelo mesmo PHP usado pelo site:

```powershell
rtk php -d extension=fileinfo artisan monitores:odbc:check
```

O comando consulta as fontes de produção e administração sem mostrar DSNs, credenciais ou mensagens nativas do driver. Retorna código `0` apenas quando ambas estão disponíveis.

Depois de uma falha, a aplicação mantém o último snapshot válido e aguarda `MONITOR_ODBC_RETRY_AFTER_FAILURE_SECONDS` (60 segundos por padrão) antes de tentar novamente cada fonte ODBC. Isto evita várias tentativas e registos idênticos enquanto o serviço de dados está indisponível.

### Registo dos DSNs no IIS

O PHP do site é de 64 bits; `BD_PREF` e `Conecta-Conexao` devem existir como **System DSNs de 64 bits**, nunca como User DSNs. O script [register-odbc-system-dsns.ps1](scripts/register-odbc-system-dsns.ps1) cria ambos sem guardar palavras-passe. Execute-o numa consola PowerShell elevada e use primeiro `-WhatIf`:

```powershell
.\scripts\register-odbc-system-dsns.ps1 `
  -ProductionServer '<servidor-aprovado>' -ProductionDatabase '<base-producao>' `
  -DashboardServer '<servidor-aprovado>' -DashboardDatabase '<base-administrativa>' `
  -DriverName 'ODBC Driver 18 for SQL Server' -WhatIf
```

Repita sem `-WhatIf` apenas depois de confirmar os parâmetros. Se o ambiente exigir propriedades adicionais do driver — por exemplo, as definições TLS aprovadas — passe-as explicitamente em `-AdditionalDriverProperty`. O script recusa substituir um DSN existente sem `-ReplaceExisting`.

Se os dois DSNs já funcionarem como User DSNs de 64 bits para o utilizador que executa a consola, o script pode copiar as propriedades não sensíveis desses DSNs — servidor, base e definições do driver — para System DSNs. Passwords e identificadores de utilizador são excluídos. Primeiro simule:

```powershell
.\scripts\register-odbc-system-dsns.ps1 -CopyCurrentUserDsns -WhatIf
```

## Painel e playlist

O painel usa somente assets locais: fundo, logótipo, fontes, CSS e JavaScript. A playlist é sincronizada por uma marca de tempo fornecida pelo servidor, portanto abrir ou recarregar uma TV não reinicia o ciclo individualmente.

O ciclo contém painel principal (60 s), hoje (30 s), semana (30 s) e mês (30 s). Quando existe um comunicado ativo para o setor — ou um global — acrescenta uma etapa de comunicado de 60 s; caso contrário, essa etapa é ignorada sem criar uma tela vazia.

Consulte [docs/FASE_4_PREMIOS.md](docs/FASE_4_PREMIOS.md) para as regras de prémio e para o registo das tabelas próprias já criadas no `CONNECTA_2024`. O script permanece em `database/schema/proposals/` como registo do schema aplicado; não é uma migration automática do Laravel.

Os comunicados são lidos do mesmo banco por `Conecta-Conexao`. Consulte [docs/FASE_6_COMUNICADOS.md](docs/FASE_6_COMUNICADOS.md): enquanto não existir comunicado ativo, a playlist ignora essa etapa sem mostrar uma tela vazia.

## Histórico administrativo

O utilizador administrativo pode analisar o histórico mensal em `/admin/historico-premios`, filtrar por setor e período, e exportar o resultado para XLSX. A funcionalidade é exclusiva da área autenticada: os monitores mantêm a visualização do mês vigente e a playlist pública não apresenta nem percorre os registos históricos.

A exportação usa `openspout/openspout` incluído em `vendor/`. Para a transferência controlada para o servidor sem instalação de dependências, consulte os requisitos do pacote em [docs/CUTOVER_PREFLIGHT.md](docs/CUTOVER_PREFLIGHT.md).

## Preparação local

O PHP instalado possui a DLL `fileinfo`, mas ela não está ativa globalmente. Para não alterar o `php.ini` usado pelo legado, os comandos deste projeto ativam a extensão apenas durante o processo:

```powershell
rtk php -d extension=fileinfo .tools/composer.phar install
rtk php -d extension=fileinfo artisan key:generate
rtk php -d extension=fileinfo artisan test
```

Para executar manualmente pelo terminal:

```powershell
rtk php -d extension=fileinfo artisan serve --host=127.0.0.1 --port=8000
```

Somente o diretório `public` deverá ser exposto pelo servidor web.

## Limites atuais

- Não executar migrations sem aprovação separada do schema.
- Não copiar credenciais do legado.
- Não alterar novamente o SQL Server, serviços do Windows, firewall ou links das TVs sem aprovação separada. As tabelas próprias aprovadas para prémios, comunicados, auditoria e utilizadores já foram criadas; objetos industriais e do legado não foram alterados.
- `php artisan serve` é o método operacional escolhido por agora. Não é um serviço Windows e não reinicia automaticamente; manter o procedimento de arranque manual disponível.

O inventário somente de leitura do legado e as instruções de homologação estão em `docs/`.
