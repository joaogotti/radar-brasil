<?php

return [
    'year' => 2025,
    'years' => range(2005, 2025, 2),
    'census_rows' => 5571,
    'census_url' => 'https://download.inep.gov.br/dados_abertos/sinopses_estatisticas/sinopse_estatistica_censo_escolar_2025.zip',
    'indicators' => [
        'basic_education_enrollments' => [
            'name' => 'Matrículas da educação básica', 'unit' => 'Matrículas', 'table' => '1.1',
            'measure' => 'Matrículas',
            'universe' => 'Educação básica, todas as dependências administrativas, urbana e rural; inclui educação infantil, fundamental, médio, profissional, EJA e educação especial nas etapas correspondentes; exclui turmas de atividade complementar e AEE.',
            'definition' => 'Total oficial de matrículas, não de alunos únicos. Um aluno pode possuir mais de uma matrícula. Não somar etapas ou modalidades sobrepostas.',
        ],
        'basic_education_establishments' => [
            'name' => 'Estabelecimentos da educação básica', 'unit' => 'Estabelecimentos', 'table' => '3.1',
            'measure' => 'Estabelecimentos',
            'universe' => 'Estabelecimentos em atividade em 28/05/2025, com pelo menos uma matrícula; todas as dependências, urbana e rural. Exclui estabelecimentos com turmas exclusivamente de atividade complementar ou AEE.',
            'definition' => 'Total oficial de estabelecimentos no município da escola. Uma escola com várias etapas é contada uma única vez no total.',
        ],
        'basic_education_teachers' => [
            'name' => 'Docentes da educação básica', 'unit' => 'Docentes', 'table' => '2.1',
            'measure' => 'Docentes',
            'universe' => 'Indivíduos em efetiva regência de classe em 28/05/2025, todas as dependências, urbana e rural; exclui docentes de turmas de atividade complementar e AEE.',
            'definition' => 'Docentes distintos dentro de cada município segundo o total oficial da Sinopse. Não representa a soma dos docentes por escola ou etapa; um docente pode atuar em mais de um município.',
        ],
        'ideb_initial_years_public' => [
            'name' => 'IDEB — anos iniciais — rede pública', 'unit' => 'Índice', 'stage' => 'Anos Iniciais',
            'sheet' => 'IDEB_AI_MUNICÍPIOS', 'rows' => 5568,
            'url' => 'https://download.inep.gov.br/ideb/resultados/divulgacao_anos_iniciais_municipios_2025.zip',
        ],
        'ideb_final_years_public' => [
            'name' => 'IDEB — anos finais — rede pública', 'unit' => 'Índice', 'stage' => 'Anos Finais',
            'sheet' => 'IDEB_AF_MUNICÍPIOS', 'rows' => 5569,
            'url' => 'https://download.inep.gov.br/ideb/resultados/divulgacao_anos_finais_municipios_2025.zip',
        ],
    ],
];
