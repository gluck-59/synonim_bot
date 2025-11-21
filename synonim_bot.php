<?php
    
// бот "synonim_bot"
// https://telegram.me/synonim_bot
// замена токена или урла: http://site/setWebhook

mb_internal_encoding("UTF-8");
error_reporting(E_ERROR);
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


function processMessage($message) {
echo __LINE__. ' processMessage start<br><pre>';
    global $banlist;
    $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if        ( $message['from']['first_name'])   $from = $message['from']['first_name'];
  else if   ( $message['from']['username']  )   $from = $message['from']['username'];
  else if   ( $message['from']['last_name'] )   $from = $message['from']['last_name'];
  else $from = 'мой друг';
  
  $out = '';
  $menu = '';

      if (in_array($message['from']['id'], $banlist))
      {
          apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => '<a href="http://natribu.org">Узнать ответ</a>', 'parse_mode' => 'HTML'));
          die;
      }

  // если пришел текст, будем работать
  if (isset($message['text']) || (!empty($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != 'setWebhook'))
  {
    $inputText = mb_strtolower($message['text']);
      if (!empty($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != 'setWebhook') {
          $inputText = mb_strtolower($message);
      }
    $state = 1; // успешно
    
    // обработаем commands
    switch ($inputText)
    {
        case strpos($inputText, "/start") === 0:
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "Привет, {$from}!\nНапишите мне слово по-русски и я подберу к нему синоним."));
        die; // в commands стандартный break не сработает, надо die/exit
        
        case strpos($inputText, "/help") === 0:
        //apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "Напишите мне слово на русском языке, к которому Вы хотите найти синонимы и я постараюсь Вам помочь.\nТакже я умею исправлять ошибки и опечатки.\n\nЕсли я понравился Вам, пожалуйста проголосуйте за меня в <a href=\"https://storebot.me/bot/synonim_bot\">каталоге ботов</a>", 'disable_web_page_preview' => true, 'parse_mode' => 'HTML'));
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "Напишите мне слово на русском языке, к которому Вы хотите найти синонимы и я постараюсь Вам помочь.\nТакже я умею исправлять ошибки и опечатки.", 'disable_web_page_preview' => true, 'parse_mode' => 'HTML'));
        die;

        case strpos($inputText, "/about") === 0:
        //apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "Я робот Синоним и я знаю более 160 тысяч слов: существительных, прилагательных, глаголов... В своих делах я использую <a href=\"www.trishin.ru/left/dictionary\">словарь синонимов</a> В.Н.Тришина, а правописание проверяет <a href=\"http://api.yandex.ru/speller\">Яндекс.Спеллер</a>. \nЕсли Вы нашли баг, свяжитесь <a href=\"telegram.me/motokofr\">с моим разработчиком</a>. \nЕсли я понравился Вам, пожалуйста проголосуйте за меня в <a href=\"https://storebot.me/bot/synonim_bot\">каталоге ботов</a>", 'disable_web_page_preview' => true, 'parse_mode' => 'HTML'));
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "Я робот Синоним и я знаю более 160 тысяч слов: существительных, прилагательных, глаголов... В своих делах я использую <a href=\"www.trishin.ru/left/dictionary\">словарь синонимов</a> В.Н.Тришина, а правописание проверяет <a href=\"http://api.yandex.ru/speller\">Яндекс.Спеллер</a>. \nЕсли Вы нашли баг, свяжитесь <a href=\"telegram.me/motokofr\">с моим разработчиком</a>.", 'disable_web_page_preview' => true, 'parse_mode' => 'HTML'));
        die;
        
        case strpos($inputText, "/stat") === 0:
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => getStat(), 'parse_mode' => 'HTML'));
        die;                        
        
        
        // если спросят ХУЙ, отдаем *name спросившего
        case strpos($inputText, "хуй") === 0:
        $hui = '';
        if ($message['from']['first_name']) 
            $hui .= ' '.$message['from']['first_name'];
        elseif ($message['from']['last_name'])
            $hui .= ' '.$message['from']['last_name'];
        elseif ($message['from']['username'])
            $hui .= ' '.$message['from']['username'];
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $hui, 'parse_mode' => 'HTML'));
        die;                        
    }
    
    
    
    /*
    **  вся логика здесь
    */
    // идем в словарь без спеллинга
echo __LINE__.' идем в словарь без спеллинга<br>';
//error_log("\n\n".date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "идем в словарь без спеллинга, ввод: $inputText\n", 3, "1test.log");
    $arr = getSyn($inputText);

    echo __LINE__.' ответ getSyn:<br>';
//error_log("ответ getSyn: ".print_r($arr, 1), 3, "1test.log");
//print_r($arr);

    // если ничего нет то возможно это опечатка
    // проспеллим ввод яндексом и пойдем в словарь с исправленным словом
    if($arr['state'] == 2)
    {
//error_log("\n\n".date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "идем в спеллинг", 3, "1test.log");
        $text = mb_strtolower(checkSpell($inputText));
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "ответ от спеллинга: $text\n", 3, "1test.log");
        $arr = getSyn($text);
    } else {
        echo __LINE__.'нужен спелл?<br>';
//error_log(__LINE__ . ' ' . " нужен спелл?", 3, "1test.log");
    }
    
    foreach ($arr['arr'] as $key => $value)
    {
        $out .= $value."\n";
        $len = mb_strlen($out);
        
        // нельзя посылать слишком длинный текст
        if ( $len > 150 ) break;    
    }
        echo __LINE__.' длина ответа '.$len.'<br>';
//error_log("длина ответа: ".$len, 3, "1test.log");

    $suggest = $arr['arr'];

    if ($key+1 < count($arr['arr']))
    {
        $menu = array('inline_keyboard' => 
            array(
                array(
                    //array('text' => 'Отмена', 'callback_data' => 'cancel'),
                    array('text' => 'Далее', 'callback_data' => '{ "text": "'.$inputText.'", "shift": "'.$key.'" }')
                ),
            ),
        );
    }        

    if (!$out) 
    {
        $out = array(
            "Увы, подходящего синонима для «{$text}» не нашлось. \nПопробуйте использовать единственное число или неопределенную форму.",
            "Простите, {$from}, я не в силах подобрать синоним к «{$text}». \nПопробуйте использовать единственное число или неопределенную форму.",
            "{$from}, мне очень жаль, но в моем словарном запасе слово «{$text}» отсуствует напрочь. \nПопробуйте использовать единственное число или неопределенную форму."
        );
        $rand_keys = array_rand($out);
        $out = $out[$rand_keys];    
        
        $state = 0; //'неудачно';
        
        if ( strpos($text, " ") OR strpos($text, "\n"))
            $out = "Попробуйте использовать одно слово, {$from}.";
    }
     
    
    if ( isset($text) && $inputText != $text ) {
        $out = "<b>{$inputText} → {$text}</b>\n" . $out;
    }

    // отправляем
    send($chat_id, $out, $menu);
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "send ".strlen($out).' символов', 3, "1test.log");

    // пишем статистику
    if ($state != 1) $suggest = NULL;
    setStat($message, $suggest, $state);
  }
  else // если пришел не текст, выдадим отлуп
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Я понимаю только текст. Пишите, {$from}, пишите :)"));
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
    $out = '';
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
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "в кэше слова нет. вызываем парсер со словом $text \n", 3, "1test.log");
    unset($html);
    unset($arr);

    //    $arr = parsingZkir($text);
    $arr = parsingSinonim_org($text);

//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "ответ parsingSinonim_org: ".sizeof($arr)."\n", 3, "1test.log");
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
//error_log("parsingZkir start со словом: ".$word."\n", 3, __DIR__."/1test.log");

    $url = 'http://slova.zkir.ru/dict/' . urlencode($word);

    // Если cURL недоступен — лог и безопасный фоллбек
    if (!function_exists('curl_init')) {
//error_log(date('d-m-y H :i') . ' ' . __LINE__ . ' ' . "cURL недоступен, используем stream_context + file_get_contents\n", 3, __DIR__."/1test.log");
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header'  => "User-Agent: synonim_bot\r\nAccept: text/html\r\n",
            ],
        ]);
//error_log("before file_get_contents\n", 3, __DIR__."/1test.log");
        $body = @file_get_contents($url, false, $context);
//error_log("after file_get_contents bodyLen=".strlen((string)$body)."\n", 3, __DIR__."/1test.log");
        if ($body === false || $body === '') {
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "file_get_contents вернул пусто\n", 3, __DIR__."/1test.log");
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
                CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ],
                CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_TCP_NODELAY => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
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
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "cURL retry: $finalErr\n", 3, __DIR__."/1test.log");
            if ($i < $attempts) usleep($delayMs * 1000);
        }

        if ($errno !== 0) {
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "cURL error ($errno): $err\n", 3, __DIR__."/1test.log");
            return [];
        }
        if ($code < 200 || $code >= 300 || $body === false || $body === '') {
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "HTTP code: $code, bodyLen: ".strlen((string)$body)."\n", 3, __DIR__."/1test.log");
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
//error_log("parsed synonims: ".count($result)."\n", 3, __DIR__."/1test.log");
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

