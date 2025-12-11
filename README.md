# av-by-offer-upper
***Поднимает оффер по кнопке***
1. **Первым делом необходимо заполнить конфиг по пути confog/config.php**
```php
'site' => [
            'url' => 'https://av.by',
            'api' => 'https://api.av.by',
        ],
        'accounts' => 'путь', // СЮДА УКАЗЫВАТЬ ФАЙЛ С АККАУНТАМИ
        'api_key' => 'путь', // СЮДА УКАЗЫВАТЬ ПУТЬ КУДА БУДУТ СОХРАНЯТСЯ КЛЮЧИ АВТОРИЗАЦИИ
        'proxy' => [
            'isNeeded' => true, // TRUE ЕСЛИ НАДО ИСПОЛЬЗОВАТЬ, FALSE ЕСЛИ ПРОКСИ НЕ НУЖЕН
            'proxy' => 'прокси', // СЮДА УКАЗЫВАТЬ ВАШ ПРОКСИ
        ]
```
2. **Запустить скрипт, выполнил**
```cmd
php init.php
```
Скрипт создаёт переменные с данными из конфига и запускает менеджер
```php
$config = get_config(); // Получение конфига из /configs/config.php
$accountsPath = $config['accounts']; // Получение пути к папке с аккаунтами
$filePath = $config['api_key']; // Получение пути к папке с api ключами
$proxy = $config['proxy']; // Прокси
if (!file_exists($filePath)) {
    file_put_contents($filePath, "{}");
}

$manager = new AvByManager($config, $accountsPath, $filePath, $proxy);
$manager->start();
```
