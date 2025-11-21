<?php
    
// бот "synonim_bot"
// https://telegram.me/synonim_bot

mb_internal_encoding("UTF-8");
// Включаем максимально подробный лог в файлы, чтобы поймать фаталы/ворнинги
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/error.log');
set_time_limit(20);

// Логгер ошибок
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $levels = [
        E_ERROR=>'E_ERROR', E_WARNING=>'E_WARNING', E_PARSE=>'E_PARSE', E_NOTICE=>'E_NOTICE',
        E_CORE_ERROR=>'E_CORE_ERROR', E_CORE_WARNING=>'E_CORE_WARNING', E_COMPILE_ERROR=>'E_COMPILE_ERROR',
        E_COMPILE_WARNING=>'E_COMPILE_WARNING', E_USER_ERROR=>'E_USER_ERROR', E_USER_WARNING=>'E_USER_WARNING',
        E_USER_NOTICE=>'E_USER_NOTICE', E_STRICT=>'E_STRICT', E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR',
        E_DEPRECATED=>'E_DEPRECATED', E_USER_DEPRECATED=>'E_USER_DEPRECATED'
    ];
    $lvl = $levels[$errno] ?? $errno;
    $msg = "PHP $lvl: $errstr in $errfile:$errline\n";
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . $msg); // в error.log
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . $msg, 3, __DIR__.'/1test.log');
    return false; // позволяем стандартной обработке продолжить, если нужно
});

// Логгер фатальных ошибок по завершению
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e) {
        $msg = sprintf("Shutdown error: %s in %s:%d\n", $e['message'] ?? '', $e['file'] ?? '', $e['line'] ?? 0);
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . $msg);
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . $msg, 3, __DIR__.'/1test.log');
    }
});

$token = null;
$envPath = __DIR__.'/.env';
if (is_readable($envPath)) {
    $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($env && isset($env['SYNONIM_BOT_TOKEN'])) {
        $token = trim($env['SYNONIM_BOT_TOKEN']);
    }
} else {
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . 'отсутствует файл .env?', 3, __DIR__.'/1test.log');
    die;
}

if (!$token) {
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . 'SYNONIM_BOT_TOKEN is missing. Add it to '.basename($envPath).' file.', 3, __DIR__.'/error.log');
    exit;
}
define("TOKEN", $token);
define("API_URL", 'https://api.telegram.org/bot'.TOKEN.'/');
define('WEBHOOK_URL', "https://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}");


require_once('banlist.php');

// ВАЖНО: сначала подключаем simple_html_dom, затем API, т.к. API может сразу вызвать processMessage()
require_once __DIR__.'/simple_html_dom.php';
require_once __DIR__.'/telegram_api.php';


//require('emoticon_fuck.php');

if (!empty($_SERVER['QUERY_STRING'])) {
    if ($_SERVER['QUERY_STRING'] == 'setWebhook') {
        apiRequest('setWebhook', array('url' => WEBHOOK_URL));
    } else {
        $inputText = mb_strtolower( urldecode($_SERVER['QUERY_STRING']));
        processMessage($inputText);
    }
}


function resolveSenderName(array $message): string
{
    $from = $message['from'] ?? [];
    foreach (['first_name', 'username', 'last_name'] as $field) {
        if (!empty($from[$field])) {
            return $from[$field];
        }
    }

    return 'мой друг';
}


function userIsBanned(array $message, array $banlist): bool
{
    $userId = $message['from']['id'] ?? null;
    return $userId !== null && in_array($userId, $banlist);
}


function extractInputText(array $message): ?array
{
    if (isset($message['text'])) {
        return [mb_strtolower($message['text']), $message['text']];
    }

    if (!empty($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== 'setWebhook') {
        $original = urldecode($_SERVER['QUERY_STRING']);
        return [mb_strtolower($original), $original];
    }

    return null;
}


function handleCommand(string $inputText, int $chatId, string $from, array $message = []): bool
{
    switch (true) {
        case strpos($inputText, '/start') === 0:
            apiRequestJson("sendMessage", array('chat_id' => $chatId, "text" => "Привет, {$from}!\nНапишите мне слово по-русски и я подберу к нему синоним."));
            return true;

        case strpos($inputText, '/help') === 0:
            apiRequestJson("sendMessage", array('chat_id' => $chatId, "text" => "Напишите мне слово на русском языке, к которому Вы хотите найти синонимы и я постараюсь Вам помочь.\nТакже я умею исправлять ошибки и опечатки.", 'disable_web_page_preview' => true, 'parse_mode' => 'HTML'));
            return true;

        case strpos($inputText, '/about') === 0:
            apiRequestJson("sendMessage", array('chat_id' => $chatId, "text" => "Я робот Синоним и я знаю более 160 тысяч слов: существительных, прилагательных, глаголов... В своих делах я использую <a href=\"www.trishin.ru/left/dictionary\">словарь синонимов</a> В.Н.Тришина, а правописание проверяет <a href=\"http://api.yandex.ru/speller\">Яндекс.Спеллер</a>. \nЕсли Вы нашли баг, свяжитесь <a href=\"telegram.me/motokofr\">с моим разработчиком</a>.", 'disable_web_page_preview' => true, 'parse_mode' => 'HTML'));
            return true;

        case strpos($inputText, '/stat') === 0:
            apiRequestJson("sendMessage", array('chat_id' => $chatId, "text" => getStat(), 'parse_mode' => 'HTML'));
            return true;

        case strpos($inputText, 'хуй') === 0:
            apiRequestJson("sendMessage", array('chat_id' => $chatId, "text" => buildObsceneReply($message), 'parse_mode' => 'HTML'));
            return true;

        default:
            return false;
    }
}


function buildObsceneReply(array $message): string
{
    if (empty($message['from'])) {
        return '';
    }

    $from = $message['from'];
    foreach (['first_name', 'last_name', 'username'] as $field) {
        if (!empty($from[$field])) {
            return ' ' . $from[$field];
        }
    }

    return '';
}


function buildSynonymResponse(string $inputText, string $originalText, string $from): array
{
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "идем в словарь без спеллинга, ввод: {$inputText}\n", 3, __DIR__.'/1test.log');
    $synonymData = getSyn($inputText);
    $normalizedText = $inputText;

    if (($synonymData['state'] ?? 0) === 2) {
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "юзаем спелл", 3, __DIR__.'/1test.log');
        $spell = mb_strtolower(checkSpell($inputText));
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "спелл: {$spell}\n", 3, __DIR__.'/1test.log');
        if (!empty($spell)) {
            $normalizedText = $spell;
            $synonymData = getSyn($normalizedText);
        }
    }

    return formatSynonymOutput($synonymData, $inputText, $normalizedText, $from, $originalText);
}


function formatSynonymOutput(array $synonymData, string $inputText, string $normalizedText, string $from, string $originalText): array
{
    $synonyms = $synonymData['arr'] ?? [];
    $state = $synonymData['state'] ?? 0;
    $out = '';
    $menu = '';
    $displaySynonyms = $synonyms;
    $lastKey = null;
    $limitReached = false;

    foreach ($synonyms as $key => $value) {
        $out .= $value."\n";
        $lastKey = $key;
        if (mb_strlen($out) > 150) {
            $limitReached = true;
            break;
        }
    }

    if ($limitReached && $lastKey !== null && ($lastKey + 1) < count($synonyms)) {
        $menu = buildPaginationMenu($normalizedText, $lastKey);
    }

    if ($out === '') {
        $state = 0;
        $displaySynonyms = null;
        $out = buildFailureMessage($normalizedText, $from);
    }

    $userText = $originalText !== '' ? $originalText : $inputText;
    if ($normalizedText !== $inputText) {
        $out = "<b>{$userText} → {$normalizedText}</b>\n" . $out;
    }

    return [
        'text' => $out,
        'menu' => $menu,
        'state' => $state,
        'suggest' => $displaySynonyms,
    ];
}


function buildPaginationMenu(string $text, int $shift): array
{
    $payload = json_encode(['text' => $text, 'shift' => $shift], JSON_UNESCAPED_UNICODE);

    return [
        'inline_keyboard' => [
            [
                ['text' => 'Далее', 'callback_data' => $payload],
            ],
        ],
    ];
}


function buildFailureMessage(string $text, string $from): string
{
    if (strpos($text, ' ') !== false || strpos($text, "\n") !== false) {
        return "Попробуйте использовать одно слово, {$from}.";
    }

    $messages = [
        "Увы, подходящего синонима для «{$text}» не нашлось. \nПопробуйте использовать единственное число или неопределенную форму.",
        "Простите, {$from}, я не в силах подобрать синоним к «{$text}». \nПопробуйте использовать единственное число или неопределенную форму.",
        "{$from}, мне очень жаль, но в моем словарном запасе слово «{$text}» отсуствует напрочь. \nПопробуйте использовать единственное число или неопределенную форму.",
    ];

    return $messages[array_rand($messages)];
}


function processMessage($message) {
    if (!is_array($message)) {
        if ($message !== '') {
            $response = buildSynonymResponse(mb_strtolower((string)$message), (string)$message, 'мой друг');
            echo nl2br(htmlspecialchars($response['text'], ENT_QUOTES, 'UTF-8'));
        }
        return;
    }

    if (!isset($message['chat']['id'])) {
        return;
    }

    global $banlist;

    $chat_id = $message['chat']['id'];
    $from = resolveSenderName($message);

    if (userIsBanned($message, $banlist)) {
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => '<a href="http://natribu.org">Узнать ответ</a>', 'parse_mode' => 'HTML'));
        return;
    }

    $textData = extractInputText($message);
    if ($textData === null) {
        apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Я понимаю только текст. Пишите, {$from}, пишите :)"));
        return;
    }

    [$inputText, $originalText] = $textData;

    if (handleCommand($inputText, $chat_id, $from, $message)) {
        return;
    }

    $response = buildSynonymResponse($inputText, $originalText, $from);
    send($chat_id, $response['text'], $response['menu']);
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "send ".strlen($response['text']).' символов', 3, __DIR__.'/1test.log');

    if (isset($message['text'])) {
        $suggest = $response['state'] === 1 ? $response['suggest'] : null;
        setStat($message, $suggest, $response['state']);
    }
}




function processQuery($query)
{
    $callback = json_decode($query['data']);
    $text = $callback->text;
    $chat_id = $query['message']['chat']['id'];
    $shift = $callback->shift;
    $menu = '';

    // теперь вызываем getSyn и выделяем заполнялку строки в функцию
    $arr = getSyn($text);
    
    foreach ($arr['arr'] as $key => $value)
    {
        if ($key > $shift)
        {
            $out .= $value."\n";
            $len = mb_strlen($out);
            if ( $len > 150 ) 
            {
                $menu = array('inline_keyboard' => 
                    array(
                        array(
                            //array('text' => 'Отмена', 'callback_data' => 'cancel'),
                            array('text' => 'Далее', 'callback_data' => '{ "text": "'.$text.'", "shift": "'.$key.'" }')
                        ),
                    ),
                );
                break;    
            }
        }
    }
    
    //$check = print_r( $menu, true );
    //error_log("из processQuery:     send({$chat_id}, {$out}, {$check});", 3, "1test.log");
    
    // отправляем
    send($chat_id, $out, $menu);
}



function send($chat_id, $out, $menu)
{
    apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $out, 'parse_mode' => 'HTML',  'reply_markup' => $menu));
}



function getSyn($text)
{
    // сначала пойдем в кэш
    global $pdo;
    $html = $pdo->prepare('SELECT suggest FROM synonim_cache WHERE text like "'.$text.'" ');
    $html->execute();
    $arr = $html->fetchColumn();
//echo __LINE__.' из getSyn(), sizeof arr = '.sizeof($arr).'<br>';
//error_log("ответ getSyn(), sizeof arr = ".sizeof($arr).", gettype arr = ".gettype($arr)."\n", 3, "1test.log");
    if (!empty($arr)) {
        return array('arr' => unserialize($arr), 'state' => 1);
    }
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "в кэше слова нет. вызываем парсер со словом $text \n", 3, "1test.log");
    unset($html);
    unset($arr);
//    $arr = parsingZkir($text);
    $arr = parsingSinonim_org($text);
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "ответ parsingZkir: ".sizeof($arr)."\n", 3, "1test.log");
    if (empty($arr))
    {
        $state = 2; // === то ли slova.zkir.ru в дауне, то ли юзер прислал хуйню ===
        $arr = [0 => "Попробуйте ввести одно слово в единственном числе, именительном падеже или неопределенной форме. И да: слово должно существовать в русском языке."];
        return array('arr' => $arr, 'state' => $state);
    } else $state = 1;

    return array('arr' => $arr, 'state' => $state);
}


/**
 * ходит в словарь slova.zkir.ru, тянет синонимы
 * http://simplehtmldom.sourceforge.net/manual.htm
 *
 * @param string $word
 * @return array
 */
function parsingZkir(string $word): array {
//    error_log("parsingZkir start со словом: ".$word."\n", 3, __DIR__."/1test.log");

    $url = 'http://slova.zkir.ru/dict/' . urlencode($word);

    // Если cURL недоступен — лог и безопасный фоллбек
    if (!function_exists('curl_init'))

    {
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "cURL недоступен, используем stream_context + file_get_contents\n", 3, __DIR__."/1test.log");
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header'  => "User-Agent: synonim_bot\r\nAccept: text/html\r\n",
            ],
        ]);
//        error_log("before file_get_contents\n", 3, __DIR__."/1test.log");
        $body = @file_get_contents($url, false, $context);
//        error_log("after file_get_contents bodyLen=".strlen((string)$body)."\n", 3, __DIR__."/1test.log");
        if ($body === false || $body === '') {
            error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "file_get_contents вернул пусто\n", 3, __DIR__."/1test.log");
            return [];
        }
    } else {
        // Используем cURL вместо file_get_contents/file_get_html для стабильности на PHP 7.4
        // Добавляем ретраи и быстрые таймауты, чтобы не зависать в вебхуке
        $attempts = 2;
        $delayMs  = 250; // backoff между попытками
        $body = '';
        $finalErr = '';
        for ($i = 1; $i <= $attempts; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'telegram_bot',
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ],
                CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_TCP_NODELAY => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                CURLOPT_LOW_SPEED_LIMIT => 300, // байт/сек
//                CURLOPT_LOW_SPEED_TIME => 3,    // сек
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $err  = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno === 0 && $code >= 200 && $code < 300 && $body !== false && $body !== '') {
                break; // успех
            }
            $finalErr = "try#$i errno=$errno http=$code err=$err bodyLen=".strlen((string)$body);
            error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "cURL retry: $finalErr\n", 3, __DIR__."/1test.log");
            if ($i < $attempts) usleep($delayMs * 1000);
        }

        if ($errno !== 0) {
            error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "cURL error ($errno): $err\n", 3, __DIR__."/1test.log");
            return [];
        }
        if ($code < 200 || $code >= 300 || $body === false || $body === '') {
            error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "HTTP code: $code, bodyLen: ".strlen((string)$body)."\n", 3, __DIR__."/1test.log");
            return [];
        }
    }

    // Парсим HTML из строки, чтобы не использовать сетевой вызов внутри simple_html_dom
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "before str_get_html\n", 3, __DIR__."/1test.log");
    if (strlen($body) > 800000) {
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "body too large: ".strlen($body)."\n", 3, __DIR__."/1test.log");
        return [];
    }
    $dom = str_get_html($body);
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "after str_get_html\n", 3, __DIR__."/1test.log");
    if ($dom === false) {
        error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "str_get_html вернул false\n", 3, __DIR__."/1test.log");
        return [];
    }

    $result = [];
    foreach ($dom->find('a.synonim') as $el) {
        $result[] = strip_tags(trim($el->innertext));
    }
//    error_log("parsed synonims: ".count($result)."\n", 3, __DIR__."/1test.log");
    return $result;
}


/**
 *
 */
function parsingSinonim_org($inputText = '') {
    $url = 'http://sinonim.org/s/'.$inputText;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,           // вернуть ответ строкой
        CURLOPT_FOLLOWLOCATION => true,           // следовать редиректам
        CURLOPT_CONNECTTIMEOUT => 5,              // таймаут соединения (сек)
        CURLOPT_TIMEOUT => 10,             // общий таймаут запроса
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('cURL error: ' . $error);
    }

    echo "HTTP $httpCode\n";
    $html = str_get_html($response);
    $out = [];
    foreach ($html->find('table#mainTable') as $table) {
        foreach($table->find('td') as $td) {
            if (gettype($td) === 'object') {
                foreach($td->find('a[id^=as]') as $a) {
                    $out[] = $a->plaintext;
                }
            }
        }
    }
    return $out;
}


/*
commands
start - Начать
help - Как пользоваться
about - О Синониме
stat - Статистика использования





[
    {
        "message_id":1584,
        "from":
        {
            "id":83561141,
            "first_name":"\u0413\u043b\u044e\u043a\u044a",
            "username":"motokofr"
        },
        "chat":
        {
            "id":83561141,
            "first_name":"\u0413\u043b\u044e\u043a\u044a",
            "username":"motokofr",
            "type":"private"
            },
        "date":1476436169,
        "text":"\u0442\u0435\u0441\u0442"
    }
]    



*/
?>

