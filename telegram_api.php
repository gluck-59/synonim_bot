<?php
mb_internal_encoding("UTF-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('pdo.php');
$date = date('d-m-Y G:i:s');


/**
 * яндексит очепятки, возвращает скорректированное слово
 *
 * @param $inputText
 * @return mixed|SimpleXMLElement
 */
function checkSpell($inputText)
{
    // настройки yamdex.Speller https://tech.yandex.ru/speller/doc/dg/reference/speller-options-docpage
    $html = simplexml_load_file("http://speller.yandex.net/services/spellservice/checkText?lang=ru&options=22&text={$inputText}");
    if (is_object($html)) {
        return $html->error->s;
    }
    else
        return $inputText;
}


/**
 * пишет статистику слов в базу
 *
 * @param $message
 * @param $suggest
 * @param $state
 * @return void
 */
function setStat($message, $suggest, $state)
{
    global  $pdo, $date;
    try
    {
        $stmt = $pdo->prepare('INSERT INTO `synonim_bot`(`id_user`, `first_name`, `last_name`, `username`, `text`, `state`, `date`) VALUES (:id_user, :first_name, :last_name, :username, :text, :state, :date)');
        $stmt->execute(array(':id_user' => $message['from']['id'], ':first_name' => $message['from']['first_name'] ?? '', ':last_name' => $message['from']['last_name'] ?? '', ':username' => $message['from']['username'] ?? '', ':text' => mb_strtolower(strip_tags($message['text'])), ':state' => $state, ':date' => $message['date']));
        
        
        // если удачно, положим слово в кэш
        if ( $suggest != NULL && $state == 1)
        {
            $stmt = $pdo->prepare('INSERT INTO `synonim_cache`(`text`, `suggest`, `count`) VALUES (:text, :suggest, 1 ) ON DUPLICATE KEY UPDATE `count`=count+1');
            $stmt->execute(array(':text' => mb_strtolower(strip_tags($message['text'])), ':suggest' => serialize($suggest)));
        }
    }
    catch (Exception $e)
    {
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "-- setStat Exception: --- {$date} {$e}\n", 3, "error.log");
    }
//$check = print_r($suggest, true);
//$check = serialize($suggest);
//error_log("-- setStat:  message {$message} \nsuggest {$suggest}\n state {$state} \ncheck {$check}", 3, "1test.log");    
    return;    
}


/**
 * достает статистику слов из базы
 * @return string|void
 */
function getStat()
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT text,  `count` FROM `synonim_cache` order by count desc');
    
    $stmt->execute();
    $all = $stmt->rowCount();
    $i = 1;
    
    $out = "<b>Топ-5 запросов:</b>\n";
    while ( $row = $stmt->fetch() )
    {
        $text = mb_strtolower($row->text);
        $count = round($row->count*100/$all).'%';
        $out .= "{$i}: {$text} ({$count})\n";
        $i++;
        if ($i == 6) return $out;
    }
}



function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    global $date;
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Method name must be a string\n", 3, __DIR__."/1test.log");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    global $date;      
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Parameters must be an array\n", 3, __DIR__."/1test.log");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  //echo json_encode($parameters);
  return true;
}



function exec_curl_request($handle) {
  $response = curl_exec($handle);

  //error_log("--> exec_curl_request {$response} \n\n", 3, "1test.log");

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    global $date;    
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Curl returned error $errno: $error\n", 3, __DIR__."/1test.log");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    global $date;    
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} ошибка CURL, HTTP={$http_code}\n", 3, __DIR__."/1test.log");
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    global $date;    
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Request has failed with error {$response['error_code']}: {$response['description']}, HTTP={$http_code}\n", 3, __DIR__."/1test.log");
    if ($http_code == 401) {
      global $date;    
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Invalid access token provided, HTTP={$http_code}\n", 3, __DIR__."/1test.log");
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      global $date;
//error_log("\n{$date} Request was successfull: {$response['description']}, HTTP={$http_code}\n", 3, __DIR__."/1test.log");
    }
    $response = $response['result'];
  }

  return $response;
}



function apiRequest($method, $parameters) {
    global $date;
  if (!is_string($method)) {
error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Method name must be a string\n", 3, "/1test.log");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    global $date;      
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Parameters must be an array\n", 3, "/1test.log");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);
//echo __LINE__.' url = '.$url;
//error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} apiRequest {$url} \n", 3, "/1test.log");

  $handle = curl_init($url);

  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  


$result = exec_curl_request($handle);
//error_log("--> apiRequest {$result} \n\n", 3, "1test.log");  
//print_r($result);
  return $result;
}



function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    global $date;      
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Method name must be a string\n", 3, __DIR__."/1test.log");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    global $date;      
    error_log(date('d-m-y H:i') . ' ' . __LINE__ . ' ' . "{$date} Parameters must be an array\n", 3, __DIR__."/1test.log");
    return false;
  }

  $parameters["method"] = $method;
  $parameters = json_encode($parameters);

  $handle = curl_init(API_URL);

  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
//curl_setopt($handle, CURLOPT_POST, 1); // 
  curl_setopt($handle, CURLOPT_POSTFIELDS, $parameters);
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));


  //$log = print_r($parameters, true);
  //error_log("--> apiRequestJson {$log} \n\n", 3, "1test.log");  
  
  return exec_curl_request($handle);
}

/*
if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}
*/



$content = file_get_contents("php://input");
if ($content !== false) {
    $logEntry = date('c')."\n".$content."\n\n";
} else {
    $content = '$content = false в строке'.__LINE__;
}
$update = json_decode($content, true);


// прилетело с сервера талаграм вывод в лог
//error_log(date("j.m.y G:i:s", time()) ." GMT:\n".print_r($content, true). "\n\n", 3, "1test.log");


if (!$update) {
  // receive wrong update, must not happen
}


// если прилетела мессага
if (isset($update["message"])) {
    processMessage($update["message"]);
}



// если прилетел online query
if ( isset($update["callback_query"]) ) {
    processQuery($update["callback_query"]);
}

?>
