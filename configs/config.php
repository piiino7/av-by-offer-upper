<?php

function get_config() {
    return [
        'site' => [
            'url' => 'https://av.by',
            'api' => 'https://api.av.by',
        ],
        'accounts' => 'путь', // ПУТЬ К ФАЙЛУ С АККАУНТАМИ, РАБОТАЕТ С ФАЙЛАМИ JSON
        'api_key' => 'путь', // ПУТЬ КУДА БУДУТ СОХРАНЯТСЯ КЛЮЧИ, РАБОТАЕТ С ФАЙЛАМИ JSON
        'proxy' => [
            'isNeeded' => false, // FALSE - не нужен прокси, TRUE - нужен
            'proxy' => 'прокси', // ВАШ ПРОКСИ
        ]
    ];
}