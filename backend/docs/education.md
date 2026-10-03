# Educação municipal

## Leitura de planilhas

A ingestão usa OpenSpout `^4.32`, leitor sequencial de XLSX compatível com PHP 8.3/8.4. A simulação do Composer resolveu apenas `openspout/openspout` 4.32.0, sem atualizar outras dependências e sem avisos de vulnerabilidade. Suas extensões já estão disponíveis no ambiente.

PhpSpreadsheet oferece edição, fórmulas e um modelo de células em memória que não são necessários para importar os totais oficiais. OpenSpout permite percorrer apenas as abas necessárias da Sinopse (~199 MB) sem carregar a pasta inteira em memória. Não foi criado parser XLSX próprio. A dependência de produção altera somente composer.json/composer.lock; não exige mudança no Docker.

Referência: https://github.com/openspout/openspout/tree/v4.32.0

## Fontes e execução

```sh
php artisan indicators:sync-school-census --year=2025
php artisan indicators:sync-ideb --edition=2025 --network=public
```

Somente essas referências são aceitas. Os URLs, abas, tabelas e cobertura específica da edição auditada ficam em `config/education.php`. A Sinopse usa a coluna Total das tabelas 1.1 (matrículas), 3.1 (estabelecimentos) e 2.1 (docentes). Cada uma deve conter 5.571 códigos distintos conhecidos no catálogo local. O IDEB usa somente Pública e os onze anos ímpares de 2005 a 2025, com 5.568 linhas nos anos iniciais e 5.569 nos finais. Revisão que altere essas coberturas exige nova conferência da fonte/configuração; os limites não são pressupostos universais para outros anos.

- https://download.inep.gov.br/dados_abertos/sinopses_estatisticas/sinopse_estatistica_censo_escolar_2025.zip
- https://download.inep.gov.br/ideb/resultados/divulgacao_anos_iniciais_municipios_2025.zip
- https://download.inep.gov.br/ideb/resultados/divulgacao_anos_finais_municipios_2025.zip
- https://download.inep.gov.br/ideb/resultados/nota_informativa_ideb_2025.pdf

`InepEducationArchive` baixa sequencialmente para disco, confere tamanho, ZIP e MD5 oficial do XLSX e calcula SHA-256 do ZIP/XLSX. Extrai apenas um XLSX para um nome local fixo, sem usar o caminho interno do ZIP. Limita o tamanho extraído a 400 MB e os manifestos a 64 KiB. Manifestos em Windows-1252 são convertidos para conferir nomes acentuados. Temporários são removidos mesmo se parsing/validação falhar.

Timeout de conexão: 20 s; resposta: 600 s; no máximo três tentativas para conexão, 429 e 5xx. Retry-After é respeitado até 120 s; valores superiores interrompem a execução, solicitando nova tentativa posterior em vez de antecipar a requisição. Redirecionamentos, demais erros HTTP, ZIP inválido, checksum divergente e layouts inesperados falham explicitamente.

`InepEducationReader` valida abas, títulos, edição, cabeçalhos, códigos, duplicações, domínio e marcadores. Apenas valores municipais selecionados permanecem em memória. A comparação com observações existentes usa paginação de 500 registros. Não há parser XLSX próprio, consulta por nome municipal, soma de totais ou dependência de microdados.

`EducationIndicatorSyncService` valida todos os arquivos da família antes da publicação. Uma transação por comando publica somente os dados já validados, com upsert em lotes de 500; não envolve download ou parsing. Um lock local impede execuções concorrentes da mesma família. Falha em qualquer etapa do IDEB impede publicar ambas as etapas. O lock é apropriado ao ambiente de instância única atual, não um lock distribuído.

## Modelo e ausência

Sem migrations: reutiliza `indicators` e `municipality_indicator_values`, incluindo metadata, `status`, `source_value` e `last_synced_at`. Não altera estados, indicadores anteriores, endpoint ou contrato. Os quatro IDs de fontes IBGE ficam nulos. Metadados usam `inep_census_synopsis` e `inep_ideb`, com universo, definição e filtros oficiais. A componente de desempenho municipal do IDEB corresponde às escolas urbanas, conforme a nota; o recorte não representa apenas a administração municipal.

Matrículas não são alunos únicos. Estabelecimentos são os ativos com matrícula; docentes são pessoas distintas em cada município, não soma de docentes escolares. Atividade complementar e AEE são excluídos conforme as notas das respectivas tabelas. O IDEB observado é importado sem cálculo.

Valores numéricos são disponíveis; zero continua zero. No IDEB, `-`, `ND`, `ND*` e `ND***` são conservadoramente indisponíveis, sem atribuição de causa específica, preservando o marcador. Vazio ou marcador desconhecido falha. Linha inexistente não gera observação, zero ou fallback para outra rede. Anos pares não são materializados.

Reexecução atualiza valores/revisões, preserva IDs e created_at e pode alterar updated_at/last_synced_at. Célula explicitamente indisponível pode substituir um valor anterior: o log registra antes/depois e hashes da fonte. Se uma observação previamente importada desaparecer inteiramente, o comando recusa a publicação e preserva o banco, exigindo investigação. Não há exclusão silenciosa.

O log `INEP education validated` contém identificador da execução, manifestos, cobertura e revisões planejadas; `INEP education committed` confirma a publicação. Somente o segundo comprova commit da transação. O manifesto não é uma cópia permanente do arquivo: para reprodução byte a byte, uma política operacional de arquivamento externo dos ZIPs ainda seria necessária. Nenhum ZIP entra no Git.

O endpoint existente retorna somente disponíveis e continua expondo code, name, value, unit e reference_year. Boa Esperança do Norte possui dados de 2025; as células históricas `-` são armazenadas como indisponíveis e não aparecem como zero na API.

## TLS do servidor oficial

Na validação real, o INEP enviou apenas o certificado final `*.inep.gov.br`, omitindo o intermediário `RNP ICPEdu GR46 OV TLS CA 2025`. O contêiner recusou a conexão com cURL 60. Não foi desativada a verificação TLS nem alterado Docker.

Foi obtido o intermediário no URL da autoridade certificadora indicado no certificado: https://secure.globalsign.com/cacert/rnpicpedugr46ovtlsca2025.crt. Após conversão DER para PEM, `openssl verify -CAfile /etc/ssl/certs/ca-certificates.crt /tmp/inep-intermediate.pem` confirmou a cadeia até GlobalSign Root R46. Um bundle temporário combinou as raízes do sistema com esse intermediário validado.

Enquanto o servidor mantiver a cadeia incompleta, os comandos podem precisar desse bundle operacional, sem mudar os arquivos do projeto:

```sh
php -d openssl.cafile=/tmp/inep-ca.pem -d curl.cainfo=/tmp/inep-ca.pem artisan indicators:sync-school-census --year=2025
php -d openssl.cafile=/tmp/inep-ca.pem -d curl.cainfo=/tmp/inep-ca.pem artisan indicators:sync-ideb --edition=2025 --network=public
```

O bundle é temporário e deve ser reconstruído/validado se necessário; não constitui atualização permanente do trust store. Nunca usar `verify=false` ou ignorar a validação de certificado. O erro inicial ocorreu antes de qualquer publicação.

As métricas distinguem download, integridade/extração/hashes, parsing com validação estrutural, validação de cobertura/revisões e persistência. O pico informado é o medido por PHP, não o RSS total do processo. Nenhum memory_limit é alterado.

## Validação real no PostgreSQL

Foram concluídas duas execuções de cada comando. O limite PHP permaneceu em 128 MB. As tentativas anteriores interrompidas por TLS e por uma linha não municipal com código preenchido apenas com espaços não publicaram dados. Essa célula vazia foi incluída na fixture; códigos presentes continuam exigindo sete dígitos, sem padding ou associação por nome.

| Execução | Download (s) | Integridade (s) | Parsing (s) | Validação (s) | Persistência (s) | Total (s) | Pico PHP (MB) | Requests | Retries |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Censo 1 | 78,26 | 1,58 | 5,00 | 0,30 | 0,71 | 86,57 | 54 | 2 | 1 |
| Censo 2 | 71,38 | 1,68 | 5,52 | 0,75 | 0,49 | 80,39 | 54 | 2 | 1 |
| IDEB 1 | 17,44 | 0,21 | 29,14 | 0,81 | 3,62 | 51,72 | 98 | 2 | 0 |
| IDEB 2 | 22,91 | 0,19 | 28,50 | 5,19 | 3,31 | 60,59 | 94 | 2 | 0 |

Tempos IDEB somam os dois arquivos. Requests incluem tentativas; cada Censo concluiu um download e cada IDEB concluiu dois. O total também inclui preparação, consultas e limpeza.

| Indicador | Municípios com linha | Disponíveis na série | Indisponíveis | Total de observações | Disponíveis em 2025 |
| --- | ---: | ---: | ---: | ---: | ---: |
| basic_education_enrollments | 5571 | 5571 | 0 | 5571 | 5571 |
| basic_education_establishments | 5571 | 5571 | 0 | 5571 | 5571 |
| basic_education_teachers | 5571 | 5571 | 0 | 5571 | 5571 |
| ideb_initial_years_public | 5568 | 58597 | 2651 | 61248 | 5478 |
| ideb_final_years_public | 5569 | 58559 | 2700 | 61259 | 5491 |

Foram inseridas 139.220 observações: 133.869 disponíveis e 5.351 indisponíveis. A segunda execução reprocessou as mesmas linhas, com zero inserções e zero mudanças de valor/status/source_value. O banco passou de 16 para 21 indicadores e de 701.457 para 840.677 observações municipais; as 81 estaduais permaneceram.

### Valores de controle de 2025

| Município | IBGE | Matrículas | Estabelecimentos | Docentes | IDEB AI pública | IDEB AF pública |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| Vitória | 3205309 | 77662 | 178 | 5251 | 6.4 | 5.3 |
| Afonso Cláudio | 3200102 | 5805 | 37 | 387 | 7.1 | 5.8 |
| São Paulo | 3550308 | 2512355 | 7187 | 136960 | 6.2 | 5.0 |
| Boa Esperança do Norte | 5101837 | 1735 | 7 | 81 | 6.7 | 5.0 |

Séries de Vitória, anos 2005, 2007, 2009, 2011, 2013, 2015, 2017, 2019, 2021, 2023 e 2025:

- AI: 4.0, 4.2, 4.8, 4.9, 5.0, 5.6, 5.6, 5.6, 5.6, 6.1, 6.4.
- AF: 3.4, 3.5, 3.8, 4.1, 4.0, 4.1, 4.3, 4.6, 4.6, 4.9, 5.3.

Boa Esperança do Norte tem 20 observações IDEB anteriores a 2025 com value=null, status=unavailable e source_value="-". Não recebeu histórico numérico retroativo. Os quatro endpoints reais retornaram HTTP 200, com contrato, tipos, ordenação e igualdade decimal conferidos contra PostgreSQL. Retornaram respectivamente 25, 25, 24 e 5 observações de Educação disponíveis. São Paulo não possui resultado AI público em 2013; a ausência não é retornada como zero.

### Idempotência e preservação

As comparações SQL foram ordenadas por ID, por indicador, confrontando contagem total com chaves distintas e hashes separados de identidade (ID + created_at), valores, status e source_value. Os quatro hashes de cada indicador permaneceram iguais. Não houve duplicação.

| Indicador | Hash de identidade, igual nas duas cargas |
| --- | --- |
| basic_education_enrollments | 8940fa1aad94b4df0cf5b657c6de4dbb |
| basic_education_establishments | 8b465d8859376e52e9cf4b1f01494364 |
| basic_education_teachers | 852b1e2501753c1b81f78b7733885a39 |
| ideb_initial_years_public | d49ed17db70cbe58cef66269e25550e4 |
| ideb_final_years_public | 74f9ff83e44af74e8062f63e2537afdb |

As 701.457 observações anteriores mantiveram o checksum `be360cd1463f79fa80fd08790bbdbedd`, incluindo IDs, chaves, valores, status, source_value e created_at. IDs de execução registrados nos logs: Censo `43f2cf2d81d57674d47632b0` / `c1b6805cefd2e6630f9879c3`; IDEB `283157f77052e257acb9f764` / `829cef5d35fdebefc1da6e08`.

### Testes

Os testes usam XLSX/ZIP mínimos gerados no diretório temporário e HTTP fake com bloqueio de requisições externas. Exercitam totais oficiais, código, título/edição, cabeçalhos, cobertura, checksum Windows-1252, integridade, retries, marcadores, zero, histórico, revisões, ausência de linha, preservação, idempotência, rollback e contrato existente. Não dependem da disponibilidade do INEP.

```sh
php artisan test --compact --filter='EducationIndicatorTest|InepEducationArchiveTest'
php artisan test --compact
vendor/bin/pint --test
```

Resultado final: 35 testes de Educação / 150 assertions; suíte completa com 151 testes / 1.032 assertions (baseline: 116 / 882). Pint aprovou 83 arquivos. Composer validate passou e composer audit não encontrou avisos de vulnerabilidade. Frontend: 17 testes, lint e build aprovados; somente o aviso conhecido de bundle acima de 500 kB. Nenhuma alteração de frontend, Docker ou migration foi necessária.
