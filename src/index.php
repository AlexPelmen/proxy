<?php

    const LOG_PATH = "./log.json";
    const LOG_OUT_PATH = "./logout.json";

    $http_origin = $_SERVER['HTTP_ORIGIN'];
    $method = $_SERVER['REQUEST_METHOD'];
    $headers = [];



    //CORS headers
    header("Access-Control-Allow-Origin: ".$http_origin);
    header('Access-Control-Allow-Headers: accept, accept-language, cache-control, pragma, sec-fetch-dest, sec-fetch-mode, sec-fetch-site, Content-Type, Authorization, Set-Cookie, Host, X-Requested-With', 'Set-Cookie');
    header("Access-Control-Allow-Methods: GET,POST,PATCH,PUT,DELETE,OPTIONS");
    header("Access-Control-Allow-Credentials: true");


    if($method === "OPTIONS") {   // Значит это preflight
        exit();
    }


    // хэдеры, которые не нужно передавать в проксируемом запросе
    define("SYSTEM_HEADERS", [
        "accept-encoding",
        "user-agent",
        "connection",
        "host",
        "content-length",
        "pragma",
        "cache-control",
        "accept",
        "user-agent",
        "sec-fetch-site",
        "sec-fetch-mode",
        "sec-fetch-dest",

        "origin",
        "referer",
        "accept-encoding",
        "accept-language"
    ]);


    // Забираем хэдеры из запроса
    foreach(getallheaders() as $header => $value) {
        if(array_search(strtolower($header), SYSTEM_HEADERS)) continue;
        if($header === "accept-encoding") {
            $encoding = $value;
            continue;
        }
        if(strtolower($header) === "set-cookie") { //Специально выделил этот хэдер для установки кук, так как браузер не может так
            $headers []= "Cookie: $value";
            continue;
        }
        $headers []= "$header: $value";
    }

    // тело запроса, ищем POST
    $postdata = file_get_contents('php://input');
    try{
        $body = json_decode($postdata, true);
        if(!$body) throw new Exception("POST query in params");
    }
    catch(Exception $e) {
        $body = $_POST;
    }
    //Если не нашли POST, ищем GET
    if(!$body) { // Попробуем взять из GET
        $query = urldecode($_SERVER['QUERY_STRING']);

        try{
            $body = json_decode($query, true);
            if(!$body) throw new Exception("GET query in params");
        }
        catch(Exception $e) {
            $body = $_GET;
        }
    }
    $body = gettype($body) === "string" ? json_decode($body, true) : $body;


    // если не проставлен таргет, то мы не знаем, куда делать запрос
    // выкидываем ошибку
    try {
        $url = $body["target"];
        if(! isset($body["target"])) {
            throw new Exception("The target url is not found in the request body");
        }
        unset($body["target"]);
    }
    catch(Exception $e) {
        die($e);
    }

    // Без указания кодировки получим кракозябры
    header('Content-Encoding: '.$encoding);

    $logOut = [
        "method" => $method,
        "url" => $url,
        "headers" => $headers
    ];



    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_ENCODING => $encoding,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLINFO_HEADER_OUT => TRUE
    ));

    // указываем тело, если оно есть
    if(count((array)$body)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        $logOut["body"] = $body;
    }

    $response = curl_exec($curl);

    file_put_contents(LOG_PATH, json_encode($logOut));
    file_put_contents(LOG_OUT_PATH, $response);

    // забираем хэдеры из ответа
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $outHeaders = explode("\n", curl_getinfo($curl, CURLINFO_HEADER_OUT));
    foreach($outHeaders as $header) {
        header($header);
    }


    // профит!
    exit($response);


