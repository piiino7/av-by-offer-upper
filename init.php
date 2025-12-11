<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use Includes\AvByManager;

$config = get_config(); // Получение конфига из /configs/config.php
$accountsPath = $config['accounts']; // Получение пути к папке с аккаунтами
$filePath = $config['api_key']; // Получение пути к папке с api ключами
$proxy = $config['proxy']; // Прокси
if (!file_exists($filePath)) {
    file_put_contents($filePath, "{}");
}

$manager = new AvByManager($config, $accountsPath, $filePath, $proxy);
$manager->start();
