<?php
require_once 'config.php';
require_once 'request.php';
ini_set('error_log', 'error_log');
function panel_login_cookie($code_panel)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $panel['url_panel'] . '/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT_MS => 4000,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => "username={$panel['username_panel']}&password=" . urlencode($panel['password_panel']),
        CURLOPT_COOKIEJAR => (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt',
        CURLOPT_HEADER => false,
    ));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    
    if (curl_error($curl)) {
        $token = [];
        $token['errror'] = curl_error($curl);
        curl_close($curl);
        return $token;
    }
    
    // Check HTTP status code
    if ($http_code < 200 || $http_code >= 300) {
        $token = [];
        $token['errror'] = "HTTP Error {$http_code}: " . substr($response, 0, 200);
        curl_close($curl);
        return $token;
    }
    
    // Check if response is empty
    if (empty($response)) {
        $token = [];
        $token['errror'] = "Empty response from server";
        curl_close($curl);
        return $token;
    }
    
    // Log response for debugging (first 500 chars)
    error_log("Login response (first 500 chars): " . substr($response, 0, 500));
    error_log("HTTP Code: {$http_code}, Content-Type: {$content_type}");
    
    curl_close($curl);
    return $response;
}
function login($code_panel, $verify = true)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    if ($panel['datelogin'] != null && $verify) {
        $date = json_decode($panel['datelogin'], true);
        if (isset($date['time'])) {
            $timecurrent = time();
            $start_date = time() - strtotime($date['time']);
            if ($start_date <= 3000) {
                $cookiePath = (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp');
                if (!is_dir($cookiePath)) { @mkdir($cookiePath, 0775, true); }
                file_put_contents($cookiePath . '/cookie.txt', $date['access_token']);
                return array('success' => true, 'msg' => 'Using cached login');
            }
        }
    }
    $response = panel_login_cookie($panel['code_panel']);
    
    // Check if panel_login_cookie returned an error
    if (is_array($response) && isset($response['errror'])) {
        return $response;
    }
    
    // Check if response is valid string
    if (!is_string($response)) {
        return array('success' => false, 'errror' => 'Invalid response from login endpoint');
    }
    
    $cookieFile = (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt';
    $cookieContent = @file_get_contents($cookieFile);
    
    $time = date('Y/m/d H:i:s');
    $data = json_encode(array(
        'time' => $time,
        'access_token' => $cookieContent ? $cookieContent : ''
    ));
    update("marzban_panel", "datelogin", $data, 'name_panel', $panel['name_panel']);
    
    // Try to decode JSON
    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        // Log the actual response for debugging
        error_log("JSON decode error. Response: " . substr($response, 0, 500));
        error_log("JSON error: " . json_last_error_msg());
        
        // Check if response looks like HTML
        if (stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false) {
            return array('success' => false, 'errror' => 'Server returned HTML instead of JSON. Check panel URL and credentials.');
        }
        
        // Check if response is empty or whitespace
        if (trim($response) === '') {
            return array('success' => false, 'errror' => 'Empty response from server');
        }
        
        return array('success' => false, 'errror' => 'Invalid JSON response: ' . json_last_error_msg() . ' (Response: ' . substr(trim($response), 0, 100) . '...)');
    }
    
    return $decoded !== null ? $decoded : array('success' => false, 'errror' => 'Empty JSON response');
}

function get_clinets($username, $namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . "/panel/api/inbounds/getClientTraffics/$username";
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(((defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt'));
    $response = $req->get();
    error_log(json_encode($response));
    @unlink(((defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt'));
    return $response;
}
function addClient($namepanel, $usernameac, $Expire, $Total, $Uuid, $Flow, $subid, $inboundid, $name_product, $note = "")
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    if ($name_product == "usertest") {
        if ($marzban_list_get['on_hold_test'] == "1") {
            if ($Expire == 0) {
                $timeservice = 0;
            } else {
                $timelast = $Expire - time();
                $timeservice = -intval(($timelast / 86400) * 86400000);
            }
        } else {
            $timeservice = $Expire * 1000;
        }
    } else {
        if ($marzban_list_get['conecton'] == "onconecton") {
            if ($Expire == 0) {
                $timeservice = 0;
            } else {
                $timelast = $Expire - time();
                $timeservice = -intval(($timelast / 86400) * 86400000);
            }
        } else {
            $timeservice = $Expire * 1000;
        }
    }
    $config = array(
        "id" => intval($inboundid),
        'settings' => json_encode(array(
            'clients' => array(
                array(
                    "id" => $Uuid,
                    "flow" => $Flow,
                    "email" => $usernameac,
                    "totalGB" => $Total,
                    "expiryTime" => $timeservice,
                    "enable" => true,
                    "tgId" => "",
                    "subId" => $subid,
                    "reset" => 0,
                    "comment" => $note
                )
            ),
            'decryption' => 'none',
            'fallbacks' => array(),
        ))
    );
    if (!isset($usernameac))
        return array(
            'status' => 500,
            'msg' => 'username is null'
        );
    $configpanel = json_encode($config, true);
    $url = $marzban_list_get['url_panel'] . '/panel/api/inbounds/addClient';
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $cookieFile = (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt';
    if (!is_dir(dirname($cookieFile))) { @mkdir(dirname($cookieFile), 0775, true); }
    $req->setCookie($cookieFile);
    $response = $req->post($configpanel);
    @unlink($cookieFile);
    return $response;
}
function updateClient($namepanel, $uuid, array $config)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $configpanel = json_encode($config, true);
    $url = $marzban_list_get['url_panel'] . '/panel/api/inbounds/updateClient/' . $uuid;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $cookieFile = (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt';
    if (!is_dir(dirname($cookieFile))) { @mkdir(dirname($cookieFile), 0775, true); }
    $req->setCookie($cookieFile);
    $response = $req->post($configpanel);
    @unlink($cookieFile);
    return $response;
}
function ResetUserDataUsagex_uisin($usernamepanel, $namepanel)
{
    $data_user = get_clinets($usernamepanel, $namepanel);
    $data_user = json_decode($data_user['body'], true)['obj'];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . "/panel/api/inbounds/{$data_user['inboundId']}/resetClientTraffic/" . $usernamepanel;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $cookieFile = (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt';
    if (!is_dir(dirname($cookieFile))) { @mkdir(dirname($cookieFile), 0775, true); }
    $req->setCookie($cookieFile);
    $response = $req->post(array());
    @unlink($cookieFile);
    return $response;
}
function removeClient($location, $username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . "/panel/api/inbounds/{$marzban_list_get['inboundid']}/delClientByEmail/" . $username;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $cookieFile = (defined('APP_TMP') ? APP_TMP : __DIR__ . '/storage/tmp') . '/cookie.txt';
    if (!is_dir(dirname($cookieFile))) { @mkdir(dirname($cookieFile), 0775, true); }
    $req->setCookie($cookieFile);
    $response = $req->post(array());
    @unlink($cookieFile);
    return $response;
}