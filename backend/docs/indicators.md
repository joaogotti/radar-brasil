# Indicadores do Censo 2022

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

`indicators` contém código único, nome, unidade, fonte e identificadores do agregado e variável de origem. Os metadados ficam no catálogo, sem repetição em cada observação.

`municipality_indicator_values` e `state_indicator_values` contêm `id`, FK do indicador, FK da localidade, `value`, `reference_year` e timestamps. Cada tabela tem uma restrição única para localidade + indicador + ano. As FKs não permitem apagar silenciosamente localidades ou indicadores com valores associados.

As duas tabelas explícitas evitam polimorfismo e o par de FKs opcionais que exigiria uma regra adicional de exclusividade. Compartilham o catálogo de indicadores. Novos indicadores anuais, como estimativa populacional ou PIB, podem usar a mesma estrutura com outro código e sincronização própria. Frequências mensais ou trimestrais exigiriam evolução do período; não são representadas artificialmente como anos.

O PostgreSQL armazena valores como `numeric(24,6)`: até 18 dígitos inteiros e 6 decimais. A sincronização mantém strings decimais, sem passagem por `float`, e rejeita precisão excedente em vez de arredondar silenciosamente. População exige inteiros na fonte. O Eloquent serializa os valores em strings com seis casas decimais.

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

A API consulta somente o banco local. Retorna a identificação pública do município e os indicadores ordenados por código, depois por ano decrescente. Todos os períodos armazenados são preservados na resposta. IDs internos e metadados técnicos de importação não são expostos.

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

```sh
docker compose exec -T backend php artisan test
docker compose exec -T backend vendor/bin/pint
git diff --check
```
