<?php
/**
 * Configurações específicas para EMJ Motors
 */

return [
    'cliente' => [
        'id' => 1,
        'nome' => 'EMJ Motors',
        'url_carrosp' => 'https://carrosp.com.br/piracicaba-sp/emj-motors/',
        'cidade' => 'Piracicaba',
        'estado' => 'SP'
    ],
    
    'importacao' => [
        'tipo' => 'puppeteer',
        'timeout' => 30000,
        'retry_attempts' => 3,
        'retry_delay' => 5000
    ],
    
    'cron' => [
        'horario' => '06:00',
        'timezone' => 'America/Sao_Paulo',
        'log_file' => 'logs/emj_importacao.log'
    ],
    
    'scraping' => [
        'selectors' => [
            'veiculo_container' => 'a[href*="/comprar/"]',
            'nome' => 'h1, h2, h3, .title',
            'preco' => '[class*="price"], [class*="valor"]',
            'ano' => '.ano, [class*="year"]',
            'km' => '.km, [class*="mileage"]',
            'cambio' => '.cambio, [class*="transmission"]',
            'cor' => '.cor, [class*="color"]',
            'combustivel' => '.combustivel, [class*="fuel"]',
            'foto' => 'img[src*="cdn.carrosp.com.br"]'
        ]
    ]
];
?>