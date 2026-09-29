# Unidades da Federação — IBGE

`brazil-states.json` é uma FeatureCollection GeoJSON com as 27 UFs, incluindo o Distrito Federal. As coordenadas são longitude/latitude em graus, conforme a resposta do IBGE.

Fontes:

- [API de Malhas, versão 3](https://servicodados.ibge.gov.br/api/docs/malhas?versao=3): Brasil, subdivisão UF, formato GeoJSON, qualidade intermediária.
- [API de Localidades](https://servicodados.ibge.gov.br/api/docs/localidades): código, nome e sigla das UFs.

As URLs completas, data de obtenção e SHA-256 do arquivo estão em `provenance.json`. O período não foi especificado: a tentativa com `periodo=2024` retornou HTTP 500. A resposta utilizada e seus metadados não informaram o ano da malha. Portanto, esta cópia não deve ser apresentada como edição 2024 ou como limites oficiais de um ano específico.

## Processamento

O script `scripts/prepare-geography.mjs` mantém apenas o código IBGE nas propriedades e ordena as UFs pelo código. A geometria já vem simplificada pelo IBGE; não há simplificação, arredondamento ou deslocamento adicional de coordenadas.

A cópia inicial foi enriquecida com nomes e siglas da API de Localidades. Na integração com o Laravel, esses metadados foram removidos sem alterar coordenadas ou ordem das geometrias; o SHA-256 foi atualizado. Nome, sigla e identificador interno agora vêm exclusivamente de `GET /api/states`, associados por código IBGE. A URL de Localidades em `provenance.json` registra a origem histórica da preparação inicial.

Os anéis são invertidos quando necessário para a convenção esférica do d3-geo (exteriores no sentido horário para áreas menores que um hemisfério). Isso altera apenas a ordem dos vértices, preservando os limites e os buracos dos polígonos.

Para atualizar, execute `node scripts/prepare-geography.mjs` na pasta frontend e revise visualmente a malha e as diferenças antes de aceitar a atualização. Como a origem não fixa um período, uma nova execução pode obter limites diferentes. O arquivo versionado mantém a aplicação independente da disponibilidade do IBGE durante o uso.

Não há indicadores estatísticos no arquivo. A atribuição ao IBGE também aparece na descrição acessível do mapa.
