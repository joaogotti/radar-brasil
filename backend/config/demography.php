<?php

return [
    'year' => 2022,
    'known_omissions' => [5101837],
    'pyramid' => [
        'aggregate' => 9514,
        'variables' => ['population' => 93, 'percentage' => 1000093],
        'units' => ['population' => 'Pessoas', 'percentage' => 'Percentual'],
        'classifications' => ['sex' => 2, 'age' => 287, 'declaration' => 286],
        'declaration_total' => 113635,
    ],
    'sexes' => [4 => 'Homens', 5 => 'Mulheres'],
    'age_groups' => array_map(static fn (int $code, int $index): array => [
        'ibge_code' => $code,
        'label' => $index === 20 ? '100 anos ou mais' : ($index * 5).' a '.($index * 5 + 4).' anos',
        'sort_order' => $index + 1,
        'min_age' => $index * 5,
        'max_age' => $index === 20 ? null : $index * 5 + 4,
    ], [93070, 93084, 93085, 93086, 93087, 93088, 93089, 93090, 93091, 93092, 93093, 93094, 93095, 93096, 93097, 93098, 49108, 49109, 60040, 60041, 6653], range(0, 20)),
    'indicators' => [
        'median_age' => [
            'name' => 'Idade mediana', 'unit' => 'Anos', 'aggregate' => 9515, 'variable' => 10613, 'filters' => [], 'measure' => 'number',
            'universe' => 'População residente.',
            'definition' => 'Idade que divide a população em duas metades, uma mais jovem e outra mais velha. Não é idade média nem expectativa de vida.',
        ],
        'aging_index_65_plus' => [
            'name' => 'Índice de envelhecimento', 'unit' => 'Razão', 'aggregate' => 9515, 'variable' => 10612, 'filters' => [], 'measure' => 'number',
            'universe' => 'População residente de 65 anos ou mais e de 0 a 14 anos.',
            'definition' => 'Pessoas de 65 anos ou mais por 100 pessoas de 0 a 14 anos.',
        ],
        'sex_ratio' => [
            'name' => 'Razão de sexo', 'unit' => 'Razão', 'aggregate' => 9515, 'variable' => 8845, 'filters' => [], 'measure' => 'number',
            'universe' => 'População residente.', 'definition' => 'Homens por 100 mulheres. Não é percentual masculino.',
        ],
        'literacy_rate_15_plus' => [
            'name' => 'Taxa de alfabetização das pessoas de 15 anos ou mais de idade', 'unit' => '%', 'aggregate' => 9543, 'variable' => 2513,
            'filters' => [2 => [6794], 86 => [95251], 287 => [100362]], 'measure' => 'percentage',
            'universe' => 'Pessoas residentes de 15 anos ou mais de idade.',
            'definition' => 'Percentual que sabe ler e escrever um bilhete simples. Não mede escolarização ou alfabetismo funcional.',
        ],
        'households_main_water_network_pct' => [
            'name' => 'Domicílios com ligação à rede geral de água e uso como forma principal', 'unit' => 'Percentual', 'aggregate' => 6803, 'variable' => 1000381,
            'filters' => [1821 => [72144]], 'measure' => 'percentage', 'universe' => 'Domicílios particulares permanentes ocupados.',
            'definition' => 'Percentual que possui ligação à rede geral e a utiliza como forma principal. Não mede qualidade, potabilidade ou continuidade.',
        ],
        'households_sewer_stormwater_or_connected_septic_pct' => [
            'name' => 'Domicílios com esgotamento por rede geral, rede pluvial ou fossa ligada à rede', 'unit' => 'Percentual', 'aggregate' => 6805, 'variable' => 1000381,
            'filters' => [11558 => [46290]], 'measure' => 'percentage', 'universe' => 'Domicílios particulares permanentes ocupados.',
            'definition' => 'Percentual com rede geral, rede pluvial ou fossa ligada à rede. Não mede esgoto tratado nem comprova adequação ambiental.',
        ],
        'households_collected_garbage_pct' => [
            'name' => 'Domicílios com lixo coletado', 'unit' => 'Percentual', 'aggregate' => 6892, 'variable' => 1000381,
            'filters' => [67 => [2520]], 'measure' => 'percentage', 'universe' => 'Domicílios particulares permanentes ocupados.',
            'definition' => 'Percentual com coleta direta ou depósito em caçamba do serviço de limpeza. Não mede coleta seletiva, reciclagem ou destinação ambientalmente adequada.',
        ],
    ],
];
