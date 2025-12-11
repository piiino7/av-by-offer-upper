<?php

namespace Includes;

use Includes\Requester;
use Includes\LogsMaker;

class AvByManager
{
    private $config;
    private $apiKeys;
    private array $accounts;
    private string $apikeyPath;
    private array $proxy;

    public function __construct($config, $accountsPath, $apikeyPath, $proxy) {
        $this->config = $config;
        $this->proxy = $proxy;
        $this->apikeyPath = $apikeyPath;
        $this->accounts = $this->getData($accountsPath);
        $this->apiKeys = $this->getData($apikeyPath);
    }

    /**
     * @param string $filePath
     * Возвращает данные из файлов или false
     */
    private function getData(string $filePath) {
        if (is_file($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        return false;
    }

    /**
     * Старт менеджера
     */
    public function start() {
        $log = new LogsMaker(__DIR__ . '/../logs/avby.log');
        $log->info("START скрипта");
        echo "start\n";

        try {
            // Перебор всех аккаунтов
            foreach ($this->accounts as $account) {
                $log->info("=========== Начало операций по аккаунту: {$account['login']} ===========");

                $signer = new Requester($this->config['site'], $account, $this->proxy); // На каждый аккаунт создаётся объект типа Requester
                $apiKey = $this->apiKeys[$account['login']]['apiKey'] ?? false; // Если уже была авторизация, проверяет на наличие api ключа для авторизованных запросов

                if (!$apiKey) {
                    $log->info("ApiKey отсутствует, делаем логин для {$account['login']}");

                    try {
                        $login = $signer->login('/auth/login/sign-in');

                        if ($login['logged'] AND $login['status'] === 200) {
                            $apiKey = $login['response']['apiKey'];
                            $this->apiKeys[$account['login']] = [
                                'apiKey' => $apiKey,
                                'created_at' => time(),
                            ];
                            $log->info("Авторизация успешна. Получен apiKey для {$account['login']}");
                        }
                    } catch (\Throwable $e) {
                        $log->error("Логин не удался, пропускаем аккаунт {$account['login']}");
                        continue;
                    }
                }

                try {
                    $offersTypes = $signer->safeGetRequest("/users/me/counters/offers", ['X-Api-Key' => $apiKey]); // Получения списка категорий и количества офферов по ним
                    $log->info("Получено " . count($offersTypes) . " категорий офферов для {$account['login']}");
                } catch (\Exception $e) {
                    if ($e->getCode() === 401) {
                        $log->warning("ApiKey недействителен, делаем логин для {$account['login']}");

                        $login = $signer->login('/auth/login/sign-in');
                        if ($login['logged'] AND $login['status'] === 200) {
                            $apiKey = $login['response']['apiKey'];
                            $this->apiKeys[$account['login']] = [
                                'apiKey' => $apiKey,
                                'created_at' => time(),
                            ];
                            $log->info("Авторизация успешна. Получен apiKey для {$account['login']}");
                            $offersTypes = $signer->safeGetRequest("/users/me/counters/offers", ['X-Api-Key' => $apiKey]); // Получения списка категорий и количества офферов по ним ещё раз после повторного логина
                            $log->info("Получено " . count($offersTypes) . " категорий офферов для {$account['login']}");
                        }
                    } else {
                        $log->error("Логин не удался, пропускаем аккаунт {$account['login']}");
                        continue;
                    }
                }

                foreach ($offersTypes as $type) {
                    if ($type['total'] > 0) {
                        $headers = [
                            'X-Api-Key' => $apiKey,
                            'Content-Type' => 'application/json'
                        ];
                        $offers = $signer->safePostRequest("/users/me/offer-types/{$type['advertType']}/offers", [], $headers); // Получение офферов по категории
                        foreach ($offers['body']['items'] as $item) {
                            try {
                                $offers = $signer->safePostRequest("/offers/{$item['id']}/refresh", [], $headers); // Клик на "поднять выше"
                                if ($offers['code'] === 200) {
                                    $log->info("Поднят оффер с id: {$item['id']} для {$account['login']}");
                                }
                            } catch (\RuntimeException $e) {
                                $log->warning("Оффер с id: {$item['id']} уже был нажат для {$account['login']}");
                            }
                        }
                    }
                }
                $log->info("=========== Конец операций по аккаунту: {$account['login']} ===========");
            }
            file_put_contents($this->apikeyPath, json_encode($this->apiKeys, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $log->info("Все apiKeys сохранены в {$this->apikeyPath}");
            $log->info("Завершение работы скрипта");
            echo "end\n";
        } catch (\Exception $e) {
            $log->error("Ошибка в аккаунте {$account['login']} | Код: {$e->getCode()} | Сообщение: {$e->getMessage()}");
            throw $e;
        }
    }
}