<?php

define('BOT_TOKEN', '315880661:AAG7v2cyYW5Xp332iyatKw1Jmi_H4kTdFrs');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

$update = file_get_contents("php://input");
$update_array = json_decode($update, true);

$res = file_get_contents("http://api.icndb.com/jokes/random?firstName=Chuck&amp;lastName=Norris");

if ($res) {
	$res_json = json_decode($res, true);
	$id = $res_json['value']['id'];
	$joke = $res_json['value']['joke'];

	if (isset($update_array['inline_query'])) {
		$inline_query = $update_array['inline_query'];
		apiRequestJson("answerInlineQuery", array(
			"inline_query_id" => $inline_query['id'],
			"results" => array(
				array(	"type" => "article",
					"id" => "0",
					"title" => "Joke",
					"input_message_content" => array("message_text" => $joke),
					"description" => $joke,
					"thumb_url" => "http://www.shauntmax30.com/data/out/9/996025-chuck-norris-background.jpg"
				)
			)
		));
	}
	else {
		$chat_id = $update_array['message']['chat']['id'];
		$chat_text = $update_array['message']['text'];
		file_get_contents(API_URL . '/sendmessage?chat_id=' . $chat_id . '&text=' . $joke);
	}
}
else {
	file_put_contents('log_jokes', "error...\n", FILE_APPEND);
	$error = "Something went wrong there... Not very funny...";
	file_get_contents(API_URL . '/sendmessage?chat_id=' . $chat_id . '&text=' . $error);
}

function apiRequestJson($method, $parameters) {
	if (!is_string($method)) {
		file_put_contents('log_jokes', "Method name must be a string\n", FILE_APPEND);
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	}
	else if (!is_array($parameters)) {
		file_put_contents('log_jokes', "Parameters must be an array\n", FILE_APPEND);
		return false;
	}

	$parameters["method"] = $method;

	$handle = curl_init(API_URL);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);
	curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
	curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

	return exec_curl_request($handle);
}

function exec_curl_request($handle) {
	$response = curl_exec($handle);

	if ($response === false) {
		$errno = curl_errno($handle);
		$error = curl_error($handle);
		file_put_contents('log_jokes', "Curl returned error $errno: $error\n", FILE_APPEND);
		curl_close($handle);
		return false;
	}

	$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
	curl_close($handle);

	if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
		sleep(10);
		return false;
	}
	else if ($http_code != 200) {
		$response = json_decode($response, true);
		file_put_contents('log_jokes', "Request has failed with error {$response['error_code']}: {$response['description']}\n", FILE_APPEND);
		if ($http_code == 401) {
			throw new Exception('Invalid access token provided');
		}
		return false;
	}
	else {
		$response = json_decode($response, true);
		if (isset($response['description'])) {
			file_put_contents('log_jokes', "Request was successfull: {$response['description']}\n", FILE_APPEND);
		}
		$response = $response['result'];
	}

	return $response;
}
