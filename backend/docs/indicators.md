# Indicadores municipais do IBGE

## Fonte e referência

Os metadados foram conferidos diretamente na API oficial do IBGE:

- [Metadados do agregado 4714](https://servicodados.ibge.gov.br/api/v3/agregados/4714/metadados)
- [Períodos disponíveis](https://servicodados.ibge.gov.br/api/v3/agregados/4714/periodos)
- [Tabela SIDRA 4714](https://sidra.ibge.gov.br/tabela/4714)

O agregado é “População Residente, Área territorial e Densidade demográfica”, da pesquisa Censo Demográfico. O período disponível é 2022. Os níveis administrativos são N1 (Brasil), N2 (grandes regiões), N3 (UFs) e N6 (municípios). Não há classificações nesse agregado.

| Código interno | Variável IBGE | Unidade oficial |
| --- | --- | --- |
| population_census | 93 — População residente | Pessoas |
| territorial_area | 6318 — Área da unidade territorial | Quilômetros quadrados |
| population_density | 614 — Densidade demográfica | Habitante por quilômetro quadrado |

A área importada é a referência **2022 da tabela 4714**, não a área territorial mais recente. A densidade é o valor publicado pelo IBGE, sem cálculo próprio. Os nomes não são usados para associar localidades: a associação usa os códigos IBGE existentes em `municipalities` e `states`.

## Modelagem

`indicators` contém código único, nome, unidade e fonte. Os metadados ficam no catálogo, sem repetição em cada observação. `source_type` é obrigatório, sem valor padrão: `ibge_aggregates` identifica a API de Agregados/SIDRA e `ibge_research` identifica a API de Pesquisas.

| Campo de origem | Uso |
| --- | --- |
| `source_aggregate_id`, `source_variable_id` | Agregado/tabela e variável na API de Agregados. Nulos para Pesquisas. |
| `source_research_id`, `source_indicator_id` | Pesquisa e indicador na API de Pesquisas. Nulos para Agregados. |
| `source_methodological_reference` | Texto opcional da referência metodológica oficialmente identificada. |
| `source_series` | Texto opcional que identifica a série publicada. |
| `source_filters` | JSONB opcional com classificações e categorias do recorte. |
| `source_universe`, `source_definition` | Universo estatístico e interpretação do indicador. |

Os quatro identificadores são inteiros opcionais; a combinação coerente com `source_type` deve ser preenchida pelo sincronizador responsável. Não há enum nem restrição no banco para essas combinações nesta etapa. `source` continua sendo o nome legível da fonte, e `unit` preserva a unidade publicada, sem conversão implícita de escala.

A migration incremental classifica os registros existentes como `ibge_aggregates`, preservando agregado 4714, variáveis, IDs e timestamps. O Censo informa esse tipo explicitamente no `upsert`; os novos campos opcionais permanecem nulos, sem atribuir uma metodologia não confirmada. O rollback restaura a obrigatoriedade dos identificadores antigos e remove os novos campos. Se houver origem incompatível ou identificadores antigos nulos, ele recusa a reversão antes de alterar o schema; esses registros precisam de uma decisão explícita antes de voltar ao modelo anterior. Os metadados adicionais são removidos no rollback.

Referência metodológica, identificação de série e ano da observação são conceitos distintos. Indicadores podem ter referências e períodos diferentes. O Censo usa Agregados; a sincronização econômica descrita abaixo usa Agregados e Pesquisas.

`municipality_indicator_values` e `state_indicator_values` contêm `id`, FK do indicador, FK da localidade, `value`, `reference_year` e timestamps. Cada tabela tem uma restrição única para localidade + indicador + ano. As FKs não permitem apagar silenciosamente localidades ou indicadores com valores associados.

As duas tabelas explícitas evitam polimorfismo e o par de FKs opcionais que exigiria uma regra adicional de exclusividade. Compartilham o catálogo de indicadores. Novos indicadores anuais, como estimativa populacional ou PIB, podem usar a mesma estrutura com outro código e sincronização própria. Frequências mensais ou trimestrais exigiriam evolução do período; não são representadas artificialmente como anos.

O PostgreSQL armazena valores como `numeric(24,6)`: até 18 dígitos inteiros e 6 decimais. A sincronização mantém strings decimais, sem passagem por `float`, e rejeita precisão excedente em vez de arredondar silenciosamente. População exige inteiros na fonte. O Eloquent serializa os valores em strings com seis casas decimais.

Esse tipo aceita negativos e mantém capacidade para PIB em Mil Reais, PIB per capita em R$, VAB e percentuais dentro dessa precisão. Não foi alterado. A validação de valores não negativos é específica do Censo; o importador econômico aceita negativos publicados e valida sua própria precisão e unidade.

`state_indicator_values.value` continua obrigatório. Em `municipality_indicator_values`, `value` agora aceita null e possui `status`, `source_value` e `last_synced_at`. Os estados são available, unavailable, not_applicable, suppressed e omitted; CHECK constraints exigem valor não nulo somente para available. Não foi adicionada restrição de positividade: os valores econômicos negativos continuam válidos.

A migration preserva IDs, valores e timestamps anteriores, atribuindo available e deixando texto original/data da coleta nulos. Não reconstrói proveniência histórica. Seu rollback recusa observações sem valor antes de restaurar NOT NULL. Os novos importadores de [Demografia e condições de vida](demography.md) preservam os marcadores. O Censo anterior continua estrito; a Economia mantém a política de remover ausência explícita e preservar localidades omitidas. Esses importadores antigos não foram reescritos.

## Sincronização

Com as UFs e os municípios já cadastrados:

```sh
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan indicators:sync-census
```

O comando chama `CensusIndicatorSyncService`, que consulta:

```text
https://servicodados.ibge.gov.br/api/v3/agregados/4714/periodos/2022/variaveis/93|6318|614?localidades=N6[all]|N3[all]
```

Há timeout de conexão de 10 s, timeout de resposta de 90 s e até três tentativas. Antes de gravar, são conferidos variáveis, unidades, ausência de classificações, período, localidades, duplicações, formato e precisão dos valores. Os três indicadores devem cobrir o mesmo conjunto de localidades, com municípios e UFs presentes.

Catálogo e valores são gravados juntos em uma transação, por `upsert`, em lotes de até 500 valores. Reexecutar atualiza os valores da mesma localidade/indicador/ano, preserva IDs e `created_at`, e não remove outros anos. Valores estaduais são importados diretamente do IBGE, nunca somados a partir dos municípios.

Localidade na resposta sem correspondência no cadastro local provoca falha antes da gravação. Localidade cadastrada ausente da fonte histórica é informada pelo comando e não recebe valores inventados. Símbolos de indisponibilidade como `...` não são convertidos em zero: interrompem a sincronização, preservando os dados existentes.

## Consulta local

```text
GET /api/municipalities/3205309/indicators
```

A API consulta somente o banco local. Retorna a identificação pública do município e as observações com status available e value não nulo, ordenadas por código, depois por ano decrescente. Todos os períodos disponíveis são preservados na resposta. O formato público permanece code, name, value, unit e reference_year. IDs internos, status e metadados técnicos de importação não são expostos.

Município existente sem observações retorna HTTP 200 com `indicators: []`. Código inexistente ou malformado retorna HTTP 404 com `{"message":"Município não encontrado."}`, inclusive com `APP_DEBUG` ativo.

Não foi adicionada rota de consulta de indicadores estaduais nesta etapa; os valores estaduais estão armazenados e acessíveis pelos relacionamentos Eloquent.

## Validação no PostgreSQL local

A primeira importação trouxe 5.570 valores municipais e 27 estaduais por indicador: 16.710 observações municipais e 81 estaduais. Boa Esperança do Norte/MT (`5101837`) está no cadastro atual de municípios, mas não na resposta desse agregado de 2022; sua consulta retorna a identificação e `indicators: []`.

As duas execuções reais duraram aproximadamente 2,79 s e 2,66 s, medidos pelo comando. A segunda manteve quantidades, IDs e datas de criação, verificados por contagens distintas e checksums de identidade no PostgreSQL.

Os valores da API local foram comparados com uma nova consulta direta à API oficial, com igualdade decimal para os seis valores:

| Município | Código IBGE | População (pessoas) | Área (km²) | Densidade (hab/km²) |
| --- | --- | --- | --- | --- |
| Vitória/ES | 3205309 | 322869 | 97.123 | 3324.33 |
| São Paulo/SP | 3550308 | 11451999 | 1521.202 | 7528.26 |

Todos os valores têm referência 2022. Esses números descrevem a consulta realizada, não são constantes usadas pela sincronização.

## Testes

`CensusIndicatorTest` usa uma fixture reduzida da resposta oficial de Vitória, São Paulo e suas UFs, com `Http::fake` e bloqueio de requisições inesperadas. A suíte verifica consulta, isolamento por município, formato, períodos, relacionamentos, atualização, preservação de identidade e histórico, FKs, unicidade, cobertura ausente e rejeição de respostas externas inválidas. Nenhum teste depende da disponibilidade do IBGE.

Também verifica origem explícita do Censo, campos opcionais nulos, representação de Pesquisa exclusivamente no banco de testes e recusa de rollback incompatível. `IndicatorSourceMigrationTest` verifica preservação de catálogo e observações pela migration e pelo rollback, fora da transação de `RefreshDatabase`, pois o SQLite precisa reconstruir a tabela ao alterar a nulabilidade.

```sh
docker compose exec -T backend php artisan test
docker compose exec -T backend vendor/bin/pint
git diff --check
```

## Indicadores econômicos municipais

| Código interno | Origem | Unidade | Anos importados |
| --- | --- | --- | --- |
| `municipal_gdp` | Agregado 5938, variável 37 | Mil Reais | 2002–2023 |
| `municipal_gdp_per_capita` | Pesquisa 38, indicador 47001 | R$ | 2010–2023 |
| `agriculture_gva` | Agregado 5938, variável 513 | Mil Reais | 2002–2021 |
| `industry_gva` | Agregado 5938, variável 517 | Mil Reais | 2002–2021 |
| `services_gva` | Agregado 5938, variável 6575 | Mil Reais | 2002–2021 |
| `public_administration_gva` | Agregado 5938, variável 525 | Mil Reais | 2002–2021 |

Os nomes dos cinco indicadores de Agregados são os nomes oficiais da resposta, conferidos a cada lote. Serviços exclui administração, defesa, educação e saúde públicas e seguridade social; esse conjunto é representado separadamente pela variável 525, também com seu nome oficial completo. PIB per capita usa o nome contextual `PIB per capita / Série revisada`.

Todos declaram a fonte `IBGE - Produto Interno Bruto dos Municípios`. PIB e VABs usam `source_type=ibge_aggregates`, `source_aggregate_id=5938`, sua variável e `source_methodological_reference=Referência 2010`. Pesquisa e indicador ficam nulos. PIB per capita usa `source_type=ibge_research`, `source_research_id=38`, `source_indicator_id=47001`, `source_series=Série revisada`; agregado, variável e referência metodológica ficam nulos.

Fontes oficiais consultadas:

- [Metadados da tabela 5938](https://servicodados.ibge.gov.br/api/v3/agregados/5938/metadados)
- [Metadados de Pesquisa 38 / indicador 47001](https://servicodados.ibge.gov.br/api/v1/pesquisas/38/indicadores/47001)
- [Documentação da API de Pesquisas](https://servicodados.ibge.gov.br/api/docs/pesquisas?versao=1)
- [PIB nacional municipal de 2023](https://servicodados.ibge.gov.br/api/v3/agregados/5938/periodos/2023/variaveis/37?localidades=N6%5Ball%5D)
- [PIB per capita nacional municipal de 2023](https://servicodados.ibge.gov.br/api/v1/pesquisas/38/periodos/2023/indicadores/47001/resultados/1xxxxx%7C2xxxxx%7C3xxxxx%7C4xxxxx%7C5xxxxx)

### Execução e lotes

```sh
docker compose exec -T backend php artisan indicators:sync-economy
```

`SyncEconomicIndicators` chama `EconomicIndicatorSyncService`. Cada lote contém um indicador, um ano e todos os municípios retornados pela fonte. São 102 consultas de Agregados (`22 + 4 × 20`) usando `localidades=N6[all]`, 14 consultas de Pesquisas e uma consulta inicial aos metadados de 47001 para validar nome, unidade R$ e multiplicador 1: **117 requisições sem retries**. A consulta de Pesquisas usa `1xxxxx|2xxxxx|3xxxxx|4xxxxx|5xxxxx`, padrões de seis dígitos, sem incluir estados ou Brasil.

O timeout de conexão é 10 s e o de resposta é 90 s. Cada chamada tem no máximo três tentativas, com intervalo de 500 ms, apenas para falha de conexão, HTTP 429 ou 5xx. Demais erros HTTP e respostas inválidas falham explicitamente. O comando informa contagens por lote, tentativas HTTP efetivamente executadas e duração; retorna código de falha se a execução não terminar.

A estrutura, identificador, nome/unidade quando presentes, período exato, nível N6 de Agregados, código municipal, duplicidade e formato decimal são conferidos antes de gravar o lote. A resposta de valores de Pesquisas não informa unidade; por isso seus metadados são validados antes das consultas de valores. Listas vazias e períodos ausentes não são interpretados como ausência estatística.

Agregados devolve sete dígitos; Pesquisas devolve seis. O serviço monta um mapa dos seis primeiros dígitos dos códigos já cadastrados, valida unicidade antes de qualquer requisição e usa esse mapa para obter o ID local. Não calcula nem inventa o sétimo dígito. Código/prefixo desconhecido ou duplicado na resposta interrompe o lote.

Cada lote validado grava catálogo e valores em uma transação, com `upsert` em blocos de até 500 observações. Nenhuma chamada HTTP fica dentro da transação. Reexecutar preserva IDs e `created_at`, atualiza revisões e `updated_at`, respeitando município + indicador + ano. Se houver falha posterior, os lotes anteriores permanecem; reexecutar reprocessa a série inteira. Não há checkpoint, execução paralela ou agendamento automático.

### Ausências, precisão e cobertura

| Valor oficial | Tratamento econômico |
| --- | --- |
| Número decimal, inclusive `-19046` | Persistido sem cálculo, conversão de unidade ou passagem por float |
| `-` | Zero absoluto, persistido como zero |
| `0` | Zero publicado, persistido como zero |
| `X`, `..`, `...`, `null` | Sem linha numérica; remove eventual valor anterior somente daquela localidade, indicador e ano |
| Valor inválido, HTTP com erro, JSON incompatível | Falha do lote; nenhuma gravação ou remoção desse lote |

Valores fora de 18 dígitos inteiros ou 6 decimais são rejeitados, nunca arredondados pelo importador. Mil Reais permanece Mil Reais. PIB per capita é importado, nunca calculado. Não são importados percentuais, impostos, VAB total ou valores econômicos estaduais/nacionais.

Município omitido da resposta é contabilizado separadamente e não recebe zero; dados anteriores de localidades omitidas são preservados. A cobertura histórica não exige 5.571 observações anuais. Boa Esperança do Norte/MT (5101837) não possui PIB 2023 nesta série. VABs são limitados a 2021; sua ausência em 2022–2023 não é extrapolada.

O endpoint municipal existente retorna os novos indicadores e todos os anos armazenados, mantendo seu contrato. O frontend continua selecionando os indicadores demográficos pelo código; não foi alterado.

`EconomicIndicatorTest` usa HTTP fake com bloqueio de chamadas inesperadas. A fixture `economic-aggregates.json` contém uma resposta oficial reduzida de Vitória e São Paulo em 2021; os testes adaptam anos e valores em memória para exercitar os demais cenários. Cobrem metadados, períodos, endpoint, mapeamento territorial, ambiguidade, símbolos, negativos, precisão, lotes inválidos, idempotência, revisões, remoção por indisponibilidade explícita e retries limitados.

### Validação da primeira carga econômica

As duas execuções reais fizeram 117 requisições cada, sem retries, em 170,64 s e 77,76 s. O catálogo passou de 3 para 9 indicadores e as observações municipais de 16.710 para 662.460, incluindo 645.750 econômicas. As 81 observações estaduais do Censo permaneceram intactas.

| Indicador | Observações econômicas |
| --- | --- |
| PIB | 122.466 |
| PIB per capita | 77.980 |
| Agropecuária | 111.326 |
| Indústria | 111.326 |
| Serviços | 111.326 |
| Administração pública | 111.326 |

PIB e cada VAB têm 5.560 valores anuais em 2002–2004, 5.564 em 2005–2008, 5.565 em 2009–2012 e 5.570 desde 2013, respeitando o último ano de cada indicador. A API de Pesquisas retornou 5.570 valores de PIB per capita em cada ano de 2010–2023; sua cobertura não foi artificialmente igualada à do PIB. Foram preservadas 45 observações negativas oficiais.

Os checksums após as duas execuções foram iguais para IDs, chaves, valores, metadados e datas de criação. Não houve duplicatas; as 645.750 observações econômicas tiveram `updated_at` atualizado na segunda execução. Os 30 valores de referência de Vitória, Vila Velha, Serra, Colatina e São Paulo foram conferidos no PostgreSQL, sem divergência.

O endpoint de Vitória retornou HTTP 200 e 119 observações, com população `322869.000000`, área `97.123000` e densidade `3324.330000` preservadas. O serviço e o seletor demográfico existentes do frontend foram executados em memória contra essa resposta e mantiveram os três indicadores corretos, sem alteração dos arquivos do frontend. A suíte completa passou com 77 testes e 686 assertions; Pint aprovou os 55 arquivos PHP.
