# Demografia e condições de vida — Censo 2022

Esta integração utiliza exclusivamente a API de Agregados do IBGE e o período 2022. Não representa atualização anual da situação municipal. Os indicadores demográficos e de condições de vida, assim como a distribuição por idade e sexo, são exibidos no perfil municipal.

## Fontes e recortes

Base: `https://servicodados.ibge.gov.br/api/v3/agregados`. Os metadados estão em `/{tabela}/metadados` e os períodos em `/{tabela}/periodos`.

| Código | Tabela | Variável | Unidade da resposta | Classificações/categorias |
| --- | --- | --- | --- | --- |
| median_age | 9515 | 10613 | Anos | Nenhuma |
| aging_index_65_plus | 9515 | 10612 | Razão | Nenhuma |
| sex_ratio | 9515 | 8845 | Razão | Nenhuma |
| literacy_rate_15_plus | 9543 | 2513 | % | 2[6794], 86[95251], 287[100362] |
| households_main_water_network_pct | 6803 | 1000381 | Percentual | 1821[72144] |
| households_sewer_stormwater_or_connected_septic_pct | 6805 | 1000381 | Percentual | 11558[46290] |
| households_collected_garbage_pct | 6892 | 1000381 | Percentual | 67[2520] |

Links oficiais: [9514](https://servicodados.ibge.gov.br/api/v3/agregados/9514/metadados), [9515](https://servicodados.ibge.gov.br/api/v3/agregados/9515/metadados), [9543](https://servicodados.ibge.gov.br/api/v3/agregados/9543/metadados), [6803](https://servicodados.ibge.gov.br/api/v3/agregados/6803/metadados), [6805](https://servicodados.ibge.gov.br/api/v3/agregados/6805/metadados), [6892](https://servicodados.ibge.gov.br/api/v3/agregados/6892/metadados), [documentação da API](https://servicodados.ibge.gov.br/api/docs/agregados?versao=3).

A idade mediana divide a população residente em duas metades; não é média nem expectativa de vida. O índice de envelhecimento expressa pessoas de 65+ por 100 pessoas de 0–14. A razão de sexo expressa homens por 100 mulheres, podendo ultrapassar 100.

Alfabetização tem como universo pessoas de 15 anos ou mais e considera ler e escrever um bilhete simples; não equivale a escolarização. Água, esgotamento e lixo têm como denominador os domicílios particulares permanentes ocupados (DPPO), não pessoas. Água exige ligação à rede e uso como forma principal, sem medir qualidade, potabilidade ou continuidade. Esgotamento inclui rede geral, pluvial ou fossa ligada: não mede tratamento. Lixo inclui coleta direta e caçamba do serviço; não comprova reciclagem ou destinação ambientalmente adequada.

## Catálogo e proveniência

Os sete indicadores usam `indicators` e `municipality_indicator_values`. O catálogo registra origem, tabela, variável, filtros JSONB, universo e definição. `source_series` fica nulo. Referência metodológica: Censo Demográfico 2022.

O valor numérico continua decimal(24,6), agora nullable. `status`, `source_value` e `last_synced_at` representam a última coleta aceita. Não são um histórico de revisões. Registros anteriores mantêm IDs, números e timestamps; recebem status available, com texto original e data da coleta nulos. Não se reconstrói o texto original a partir do número armazenado.

| Fonte | Status | Número |
| --- | --- | --- |
| Número válido | available | Valor publicado, sem float intermediário |
| - | available | Zero absoluto; marcador preservado |
| 0 / 0.00 | available | Zero numérico; texto original preservado |
| ... | unavailable | null |
| .. | not_applicable | null |
| X | suppressed | null |
| Omissão conhecida em carga nacional validada | omitted | null, source_value null |

Marcador desconhecido, null inesperado na resposta, precisão excedente ou JSON inválido interrompem a unidade de carga. Um erro de transporte não é ausência estatística. Razões não são limitadas a 100. População exige inteiro não negativo dentro da capacidade do PostgreSQL integer; percentuais devem estar entre 0 e 100 e ter no máximo seis casas.

O rollback que restauraria value obrigatório recusa observações nulas/não disponíveis antes de modificar o schema. PostgreSQL usa CHECK constraints; SQLite usa triggers equivalentes para validar inserts e updates, pois não permite adicionar CHECK com ALTER TABLE como PostgreSQL.

## Pirâmide etária

Tabela 9514, variáveis 93 (Pessoas) e 1000093 (Percentual). Classificação 2: 4 Homens, 5 Mulheres. Classificação 286: 113635 Total, incluindo ambas as formas de declaração de idade. Classificação 287: somente as faixas abaixo.

| Ordem | Código IBGE | Faixa |
| --- | --- | --- |
| 1 | 93070 | 0–4 |
| 2 | 93084 | 5–9 |
| 3 | 93085 | 10–14 |
| 4 | 93086 | 15–19 |
| 5 | 93087 | 20–24 |
| 6 | 93088 | 25–29 |
| 7 | 93089 | 30–34 |
| 8 | 93090 | 35–39 |
| 9 | 93091 | 40–44 |
| 10 | 93092 | 45–49 |
| 11 | 93093 | 50–54 |
| 12 | 93094 | 55–59 |
| 13 | 93095 | 60–64 |
| 14 | 93096 | 65–69 |
| 15 | 93097 | 70–74 |
| 16 | 93098 | 75–79 |
| 17 | 49108 | 80–84 |
| 18 | 49109 | 85–89 |
| 19 | 60040 | 90–94 |
| 20 | 60041 | 95–99 |
| 21 | 6653 | 100+ |

`age_groups` centraliza código, label, ordem e limites; 100+ tem limite máximo nulo. O seeder é idempotente e recusa mudanças inesperadas de limites/ordem. Não há HTTP em migrations.

`municipality_age_sex_values` possui uma linha por município/ano/sexo/faixa, com FK para municípios e faixas sem cascade delete. Quantidade integer e percentual decimal(9,6) ficam juntos, mas têm status e valores originais independentes. A chave única impede duplicatas. Há checks de sexo, ano, limites numéricos e coerência status/valor.

Não se importam total, meses ou idades individuais sobrepostas. Os 42 percentuais têm como denominador a população municipal total, não o total de cada sexo. Nunca são derivados da população nem normalizados. Vitória soma 100.01 nos valores publicados; São Paulo possui 337 homens de 100+ com percentual 0.00. Ambos são válidos.

## Execução e consistência

```sh
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan indicators:sync-demography
docker compose exec -T backend php artisan municipalities:sync-age-distribution
```

O cadastro de municípios deve existir. Não é necessário executar o seeder de faixas separadamente: ele participa da transação de publicação da pirâmide.

Os indicadores usam cinco consultas nacionais N6[all], agrupando as três variáveis da 9515. A pirâmide usa 28 consultas: duas variáveis × dois sexos × sete lotes de três faixas. O lote inicial de sete faixas excedeu os 128 MB disponíveis no PHP durante a primeira tentativa real, antes de publicar dados. Reduzimos o lote e liberamos ciclos de respostas HTTP entre consultas, sem alterar o Docker. Confirmações e retries podem acrescentar chamadas. Timeout de conexão 10 s, resposta 90 s, no máximo três tentativas por chamada para conexão, 429 e 5xx.

As respostas são validadas por IDs, unidades, classificações, categorias, período, código N6, duplicação, formato e precisão. Quantidade e percentual são pareados por município/sexo/faixa/ano, independentemente da posição dos arrays. Todas as células e medidas da pirâmide devem ter a mesma cobertura territorial.

Boa Esperança do Norte (5101837) é a omissão de 2022 conhecida e explicitamente configurada. Recebe sete observações omitted e 42 células com ambas as medidas omitted quando omitida na consulta nacional. Se a fonte a retornar explicitamente com ..., recebe unavailable. Não recebe dados de municípios de origem.

Omissões de outras localidades, ou de localidade que antes possuía número, exigem consulta direcionada. Confirmação inválida/inconclusiva interrompe a unidade de persistência, preservando valores anteriores. Falta isolada de uma célula da pirâmide é erro de cobertura, não autorização para inventar ausência.

Não há HTTP dentro de transações. Indicadores publicam por indicador/ano, após validação; unidades anteriores concluídas permanecem em caso de falha posterior. A pirâmide valida todos os lotes antes de publicar o período em uma única transação, em blocos de 500 linhas. Não usa staging.

Reexecutar mantém IDs e created_at e atualiza valores, status, fonte original e last_synced_at. Número → ausência atualiza a mesma linha para null; ausência → número também conserva a identidade. Não apaga outros períodos.

Locks de arquivo com flock protegem toda a execução de cada família/período e são liberados em finally ou pelo encerramento do processo. Não há expiração que permita sobreposição durante uma carga longa. O mecanismo pressupõe o mesmo filesystem compartilhado do backend atual; não é coordenação para múltiplos servidores independentes. Arquivos de lock ficam em storage/app, sem versionamento.

Os comandos imprimem contagens por status, requisições lógicas, tentativas HTTP, retries, duração e pico de memória. A pirâmide também informa o total de linhas.

## API

`GET /api/municipalities/{ibge_code}/age-distribution` consulta somente o banco local, para o período configurado 2022. Sem seleção de ano ou fallback.

Resposta: municipality, reference_year, availability, source e data. Cada célula contém sex_code, sex_label, age_group (ibge_code, label, order, min_age, max_age), population, population_status, percentage e percentage_status. Population é inteiro JSON; percentage é string com seis casas ou null. Não expõe source_value, last_synced_at ou IDs internos.

As 42 células são ordenadas por faixa e sexo. availability: not_loaded quando não há linhas, unavailable quando nenhuma medida está disponível, partial quando há disponibilidade parcial, complete quando as 84 medidas estão disponíveis. Município inválido/inexistente retorna 404 JSON; município existente sem carga retorna 200 e data vazio.

O endpoint `/indicators` mantém seu contrato plano e filtra status available e value não nulo. Motivos de ausência ficam internos. Sincronizadores antigos não foram reescritos: a Economia continua removendo ausência explícita e o Censo continua estrito. Nenhuma proveniência histórica foi fabricada.

## Testes

Fixtures reduzidas vieram das APIs oficiais; cenários de erro alteram cópias em memória. HTTP externo é bloqueado nos testes. A suíte cobre parser, filtros, cobertura, revisões, identidade, constraints, rollback e contratos. Validar também no PostgreSQL real, pois SQLite não reproduz tipos JSONB/timestamptz/numeric e ALTER TABLE do PostgreSQL.

## Validação da primeira carga

As quatro migrations foram aplicadas depois dos testes SQLite e PostgreSQL isolado. JSONB, numeric(24,6) nullable, numeric(9,6), timestamptz, CHECKs, FKs e unicidades foram conferidos no PostgreSQL 17. O checksum dos 662.460 valores anteriores, incluindo IDs e timestamps, permaneceu igual após as migrations. As 81 observações estaduais também foram preservadas.

| Carga concluída | Requisições | Retries | Duração | Pico de memória | Linhas |
| --- | --- | --- | --- | --- | --- |
| Demografia, primeira | 5 | 0 | 13,86 s | 68 MB | 38.997 |
| Demografia, segunda | 5 | 0 | 6,21 s | 68 MB | 38.997 |
| Pirâmide, primeira publicação | 28 | 0 | 127,01 s | 106 MB | 233.982 |
| Pirâmide, segunda publicação | 28 | 0 | 30,36 s | 106 MB | 233.982 |

A tentativa anterior da pirâmide, com lotes de sete faixas, falhou por memória e deixou zero linhas tanto em age_groups quanto em municipality_age_sex_values. As publicações da tabela acima ocorreram após a redução dos lotes e nova aprovação dos testes.

Demografia: 38.990 available e 7 omitted. Cada indicador tem 5.570 valores disponíveis e uma omissão. Pirâmide: 233.940 available e 42 omitted para cada medida. Não houve unavailable, not_applicable ou suppressed nessas cargas nacionais. Os oito marcadores `-` de água, 25 de esgotamento e 5.203 por medida da pirâmide foram preservados como source_value e convertidos corretamente em zero disponível.

Após as cargas: 16 indicadores, 701.457 linhas em municipality_indicator_values, 21 faixas e 233.982 linhas na pirâmide. Contagem de linhas inclui status sem valor e não deve ser confundida com quantidade de valores numéricos.

Os checksums das observações, excluindo somente updated_at e last_synced_at, foram iguais nas duas execuções: `62032dabb3d1d50db48a7b3f8565b015` para Demografia e `0df711a42a95734dcd603510096ee8e0` para a pirâmide. Nenhuma chave duplicada foi encontrada. Todos os registros tiveram os campos de sincronização atualizados na segunda execução.

| Indicador | Vitória | Afonso Cláudio | São Paulo |
| --- | --- | --- | --- |
| Idade mediana | 38 | 38 | 37 |
| Envelhecimento | 85.20 | 72.80 | 72.53 |
| Razão de sexo | 86.18 | 100.85 | 88.81 |
| Alfabetização | 97.76 | 89.79 | 97.42 |
| Água | 99.52 | 55.33 | 99.15 |
| Esgotamento | 99.67 | 46.17 | 95.23 |
| Lixo coletado | 99.74 | 70.90 | 99.72 |

São os textos originais armazenados, sem arredondamento. Boa Esperança do Norte teve os sete indicadores omitted, sem número. Na pirâmide, os três primeiros municípios tiveram 42 células completas; Boa Esperança teve 42 células omitted, valores nulos e availability unavailable. Vitória apresentou 322.869 pessoas e soma decimal 100.010000 dos percentuais. Os endpoints mantiveram os tipos e a ordenação, sem expor proveniência interna; códigos inválidos/inexistentes retornaram 404.

Os sincronizadores antigos também foram executados contra o IBGE dentro de uma transação externa revertida ao final, evitando modificar dados econômicos: Censo concluiu e Economia fez 117 requisições, sem retries. Os checksums dos valores anteriores e timestamps permaneceram iguais após essa regressão real.
