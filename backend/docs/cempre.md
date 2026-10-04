# Trabalho e empresas — CEMPRE

```sh
docker compose exec -T backend php artisan indicators:sync-cempre
```

Sincroniza somente os cinco indicadores abaixo, para 2022, 2023 e 2024, no nível municipal N6 da [tabela SIDRA 9509](https://sidra.ibge.gov.br/tabela/9509). Não importa RAIS, Novo CAGED, CNAE ou a tabela 1685.

| Code | Variável oficial | Unidade |
| --- | --- | --- |
| `cempre_local_units` | 706 — Número de unidades locais | Unidades |
| `cempre_active_organizations` | 367 — Número de empresas e outras organizações atuantes | Unidades |
| `cempre_employed_personnel` | 707 — Pessoal ocupado total | Pessoas |
| `cempre_salaried_personnel` | 708 — Pessoal ocupado assalariado | Pessoas |
| `cempre_average_monthly_salary` | 10143 — Salário médio mensal em reais | Reais |

## Origem e interpretação

O catálogo usa os campos existentes `source_type=ibge_aggregates`, `source_aggregate_id=9509`, `source_variable_id`, `source_series`, `source_methodological_reference`, `source_filters`, `source_universe` e `source_definition`. Não existem colunas `source_url`, `source_aggregate` ou `source_variable`: a URL fica no texto da definição e os identificadores nos respectivos campos `_id`, sem migration.

As definições preservam o nome oficial e distinguem locais de atuação de entidades jurídicas. Empresas e organizações atuantes incluem outras organizações e não apenas sedes. Pessoal ocupado total inclui assalariados e sócios/proprietários; pessoal ocupado assalariado não é renomeado para empregados CLT. O universo exclui MEIs e não representa todos os moradores ou a economia informal.

A [Nota técnica 01/2024](https://biblioteca.ibge.gov.br/visualizacao/livros/liv102096.pdf) documenta a ruptura anterior a 2022. A série desta V1 não é concatenada à tabela 1685. A base de preços da variável salarial não foi esclarecida na auditoria: importa-se a média oficial sem classificá-la como nominal/real, sem deflação, crescimento ou recálculo. Reais não são multiplicados por mil.

## Consulta, validação e publicação

Uma chamada nacional por ano:

```text
https://apisidra.ibge.gov.br/values/t/9509/n6/all/v/706,367,707,708,10143/p/{year}
```

Conexão: 10 s; resposta: 120 s; até três tentativas, com intervalo de 500 ms, somente para falhas de conexão, HTTP 429 ou 5xx. O relatório conta tentativas reais e retries. Erros de conteúdo não são repetidos nem convertidos em ausência.

O serviço valida cabeçalho, estrutura, N6, código municipal de sete dígitos presente no banco, ano solicitado, IDs e nomes das cinco variáveis, unidades e duplicações. Cada variável deve cobrir o mesmo conjunto municipal. A ordem das observações não determina sua associação.

Municípios do banco ausentes de `n6/all` são consultados explicitamente, em lotes de até 100 códigos. Anos com o mesmo lote ausente compartilham a consulta complementar. Ela precisa resolver toda a cobertura; resposta incompleta ou falha aborta a execução. Para a base auditada, são três chamadas nacionais e uma complementar para Boa Esperança do Norte nos três anos. Esses códigos e contagens não são hardcoded.

Somente strings originais indexadas por ano/variável/município permanecem em memória entre downloads; o JSON completo de cada chamada é liberado. Não se usa float. Valores além de `decimal(24,6)` são rejeitados, não arredondados.

| Fonte | Status | Valor | `source_value` |
| --- | --- | --- | --- |
| Número | available | Decimal publicado | String original |
| `-` | available | 0 | `-` |
| `0` | available | 0 | `0` |
| `X` | suppressed | null | `X` |
| `..` | not_applicable | null | `..` |
| `...` | unavailable | null | `...` |

Símbolos desconhecidos, null e linhas inválidas abortam a carga. Uma omissão nacional não recebe status antes da confirmação explícita. [Legenda SIDRA](https://sidra.ibge.gov.br/tabela/7361).

Todos os downloads e validações terminam antes da publicação. Uma transação publica o catálogo e os três anos completos, com upserts em lotes de 500. Falha na gravação reverte a publicação inteira. O lock local `storage/app/cempre.lock` impede execuções concorrentes neste ambiente. Reexecuções preservam IDs e `created_at`; atualizam valores, status, string original e timestamps de atualização/sincronização. Outros indicadores e observações estaduais não são alterados.

O endpoint existente `GET /api/municipalities/{ibge_code}/indicators` continua retornando somente observações disponíveis, com strings de seis casas decimais, ordenação por code e ano decrescente. Células indisponíveis são armazenadas, mas não adicionadas ao contrato público. Assim, Boa Esperança do Norte não recebe números CEMPRE inventados.

## Testes

`CempreIndicatorTest` usa HTTP fake; a fixture contém as cinco observações oficiais de Vitória/2024. Outros anos e casos de ausência/erro são construídos em memória exclusivamente para testes, sem alegar valores históricos reais.

```sh
docker compose exec -T backend php artisan test --filter=CempreIndicatorTest
```

A referência de auditoria é 83.565 células: 83.550 disponíveis e 15 indisponíveis de Boa Esperança do Norte. Serve para conferir a execução real, não como restrição permanente da fonte.

## Validação da carga inicial

As duas execuções reais publicaram 83.565 observações, com quatro requests e nenhum retry por execução. A primeira levou 101,98 s; a segunda, 44,08 s. Ambas registraram pico de 83,23 MB, mantendo `memory_limit=128M`.

Por ano foram confirmadas 27.850 observações disponíveis e cinco indisponíveis, todas de Boa Esperança do Norte. Não houve zeros, supressões, não aplicáveis ou duplicações. Os checksums de IDs, chaves, `created_at`, valores, status e `source_value` foram idênticos entre as execuções, assim como os metadados do catálogo excluindo `updated_at`.

Permaneceram idênticos os checksums completos dos 21 indicadores anteriores, 840.677 observações municipais anteriores, 81 observações estaduais e 233.982 células da pirâmide etária. Os quatro endpoints municipais reais retornaram HTTP 200, com contrato, tipos e ordenação preservados: Vitória, Vila Velha e Serra receberam 15 observações CEMPRE cada; Boa Esperança do Norte não recebeu observações disponíveis dessa fonte.

| Município, 2024 | Unidades locais | Organizações atuantes | Pessoal total | Assalariado | Salário (string de origem) |
| --- | --- | --- | --- | --- | --- |
| Vitória | 41791 | 40063 | 276540 | 222607 | `5375.96` |
| Vila Velha | 32079 | 30582 | 160045 | 120642 | `2856.81` |
| Serra | 25857 | 24137 | 190430 | 161670 | `3393.20` |

Boa Esperança do Norte mantém `value=null`, `status=unavailable` e `source_value="..."` nos cinco indicadores e três anos. As contagens acima descrevem esta carga, não pressupõem resultados futuros.
