<?php

namespace Includes;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;

class Requester
{
    private $config;
    private $client;
    private $cookieJar;
    private $requestCount = 0;
    private $maxRequestsPerSession = 100;
    private string $login;
    private string $password;
    private array $proxy;

    public function __construct($config, $account, $proxy)
    {
        $this->config = $config;
        $this->cookieJar = new CookieJar();
        $this->proxy = $proxy;
        $options = [
            'base_uri' => $this->config['api'],
            'cookies' => $this->cookieJar,
            'timeout' => 60,
            'connect_timeout' => 20,
            'read_timeout' => 45,
            'headers' => $this->getRandomHeaders(),
            'verify' => false,
            'decode_content' => true,
        ];
        if ($this->proxy['isNeeded']) {
            $options['proxy'] = [
                'http'  => $this->proxy['proxy'],
                'https' => $this->proxy['proxy'],
            ];
        }
        $this->client = new Client($options);
        $this->login = $account['login'] ?? null;
        $this->password = $account['password'] ?? null;
    }


    /**
     * Задержка между запросами
     */
    public function delay($min = 1, $max = 4) {
        sleep(rand($min, $max));
    }

    /**
     * Случайный user-agent
     */
    private function getRandomUserAgent() {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Whale/3.23.214.10',
        ];

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Случайные заголовки
     */
    private function getRandomHeaders() {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Cache-Control' => 'max-age=0',
        ];
    }

    /**
     * Логин с использованием куки
     */
    public function login($uri, $payload = null)
    {
        if ($this->requestCount >= $this->maxRequestsPerSession) {
            throw new \Exception("Достигнут лимит запросов за сессию");
        }

        $this->delay(1, 4);
        $this->requestCount++;

        try {
            if (is_null($this->login) OR is_null($this->password)) {
                throw new \Exception("Аутентификация невозможна: логин и пароль отсутствуют");
            }

            if (is_null($payload)) {
                $payload = [
                    'json' => [
                        'login' => $this->login,
                        'password' => $this->password,
                    ]
                ];
            }

            $response = $this->client->post($this->config['api'].$uri, $payload);

            $body = (string) $response->getBody();
            // Декодируем JSON
            $data = json_decode($body, true);

            $status = $response->getStatusCode();

            return [
                'logged' => $this->isLoggedIn(),
                'response' => $data,
                'status' => $status,
            ];
        } catch (\Exception $e) {
            return "Код ошибки: " . $e->getCode() . " | " . $e->getMessage();
        }
    }

    /**
     * Проверка на логин
     */
    public function isLoggedIn()
    {
        foreach ($this->cookieJar->toArray() as $cookie) {
            if (strpos($cookie['Name'], 'session') !== false ||
                strpos($cookie['Name'], 'auth') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Простой get-запрос
     */
    public function getRequest($uri, $headers = null, $params = null) {
        try {
            if (is_null($headers)) {
                $headers = $this->getRandomHeaders();
            }

            $response = $this->client->get($uri, [
                'headers' => $headers,
                'query' => $params,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (ClientException $e) {
            return "Код ошибки: " . $e->getCode() . " | " . $e->getMessage();
        }
    }

    /**
     * Простой post-запрос
     */
    public function postRequest($uri, $payload) {
        try {
            $response = $this->client->post($uri, [
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'code' => $response->getStatusCode(),
                'body' => $result,
            ];

        } catch (ClientException $e) {
            return "Код ошибки: " . $e->getCode() . " | " . $e->getMessage();
        }
    }

    /**
     * Безопасный post-запрос с соблюдением задержки и ограничением по запросам
     */
    public function safePostRequest($uri, $payload, $headers = null) {
        if ($this->requestCount >= $this->maxRequestsPerSession) {
            throw new \Exception("Достигнут лимит запросов за сессию");
        }

        if (is_null($headers)) {
            $headers = $this->getRandomHeaders();
        }

        $this->delay(1, 4);
        $this->requestCount++;

        try {
            $response = $this->client->post($uri, [
                'json' => $payload,
                'headers' => $headers
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'code' => $response->getStatusCode(),
                'body' => $result,
            ];

        } catch (ClientException $e) {
            if ($e->getCode() == 429) {
                $this->handleRateLimit();
                return $this->safePostRequest($uri);
            }
            throw $e;
        }
    }

    /**
     * Безопасный get-запрос с соблюдением задержки и ограничением по запросам
     */
    public function safeGetRequest($uri, array $headers = null, array $params = null) {
        if ($this->requestCount >= $this->maxRequestsPerSession) {
            throw new \Exception("Достигнут лимит запросов за сессию");
        }

        $this->delay(1, 4);
        $this->requestCount++;

        if (is_null($headers)) {
            $headers = $this->getRandomHeaders();
        }

        try {
            $response = $this->client->get($uri, [
                'headers' => $headers,
                'query' => $params,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (ClientException $e) {
            if ($e->getCode() == 429) {
                $this->handleRateLimit();
                return $this->safeGetRequest($uri);
            }
            throw $e;
        }
    }

    /**
     * Декодирует ответ в HTML-разметку
     */
    public function getHTML($response) {
        $result = (string) $response->getBody();
        return $result;
    }

    /**
     * Отдаёт cookie
     */
    public function getCookies() {
        return $this->cookieJar->toArray();
    }

    /**
     * Задержка при ограничении и смена клиента с другими заголовками
     */
    private function handleRateLimit() {
        echo "Обнаружено ограничение запросов! Ждем 30 секунд...\n";
        sleep(30);

        $options = [
            'base_uri' => $this->config['api'],
            'cookies' => $this->cookieJar,
            'timeout' => 60,
            'connect_timeout' => 20,
            'read_timeout' => 45,
            'headers' => $this->getRandomHeaders(),
            'verify' => false,
            'decode_content' => true,
        ];
        if ($this->proxy['isNeeded']) {
            $options['proxy'] = [
                'http'  => $this->proxy['proxy'],
                'https' => $this->proxy['proxy'],
            ];
        }
        $this->client = new Client($options);
    }

    /**
     * Получает api ключ или возвращает false
     */
    public function getKey($filePath) {
        if (is_file($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (!empty($data[$this->login]['apiKey'])) {
                    return $data[$this->login]['apiKey'];
                }
            }
        }
        return false;
    }
}