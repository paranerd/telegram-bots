<?php

$update = file_get_contents("php://input");

$calc = new Calculator($update);
$calc->exec();

class Calculator {
	static $BOT_TOKEN	= '322363957:AAGpNeFYgaYK7pauqVmR4_QvlzIAgUs_Ftk';
	static $API_URL		= 'https://api.telegram.org/bot' . '322363957:AAGpNeFYgaYK7pauqVmR4_QvlzIAgUs_Ftk';
	static $MIN			= 0;
	static $MAX			= 10;

	private $chat_id;
	private $chat_text;
	private $update_raw;
	private $update;
	private $game;
	private $wanted;

	public function __construct($update_raw) {
		$this->update_raw = $update_raw;
		$this->game = json_decode(file_get_contents('guess_game'), true);
	}

	public function exec() {
		if ($this->update_raw) {
			$this->update = json_decode($this->update_raw, true);
			$this->chat_id = $this->update['message']['chat']['id'];
			$this->chat_text = $this->update['message']['text'];

			if (isset($this->game[$this->chat_id])) {
				$this->wanted = $this->game[$this->chat_id]['wanted'];
			}

			if ($this->chat_text == 'Game Over') {
				$this->respond_private('See you next time!&reply_markup=' . json_encode(array('keyboard' => array(array('Start Game')), 'one_time_keyboard' => true, 'resize_keyboard' => true)));
			}
			else if ($this->chat_text == '/start' || $this->chat_text == 'Restart' || $this->chat_text == 'Start Game' || !isset($this->game[$this->chat_id])) {
				$this->init_game();
				$this->respond_private('I\'m thinking of a number between ' . self::$MIN . ' and ' . self::$MAX . '... Guess!');
			}
			else {
				if (preg_match('/^([0-9]+)$/', $this->chat_text)) {
					if ($this->chat_text < $this->wanted) {
						$count = $this->update_game();
						$text = ($count == 1) ? " try" : " tries";
						$this->respond_private('Higher! (' . $count . $text . ')');
					}
					else if ($this->chat_text > $this->wanted) {
						$count = $this->update_game();
						$text = ($count == 1) ? " try" : " tries";
						$this->respond_private('Lower! (' . $count . $text . ')');
					}
					else {
						$this->end_game();
						$this->respond_private('You got it!&reply_markup=' . json_encode(array('keyboard' => array(array('Restart', 'Game Over')), 'one_time_keyboard' => true, 'resize_keyboard' => true)));
					}
				}
				else {
					$this->respond_private('This game is about numbers, dummy!');
				}
			}
		}
	}

	private function respond_private($msg) {
		file_get_contents(self::$API_URL . '/sendmessage?chat_id=' . $this->chat_id . '&text=' . $msg);
	}

	private function init_game() {
			$wanted = rand(self::$MIN, self::$MAX);
			$this->game[$this->chat_id] = array('tries' => 0, 'wanted' => $wanted);
			file_put_contents('guess_game', json_encode($this->game, JSON_PRETTY_PRINT));
	}

	private function end_game() {
		unset($this->game[$this->chat_id]);
		file_put_contents('guess_game', json_encode($this->game, JSON_PRETTY_PRINT));
	}

	private function update_game() {
		$count = $this->game[$this->chat_id]['tries'];
		$count++;
		$this->game[$this->chat_id]['tries'] = $count;
		file_put_contents('guess_game', json_encode($this->game, JSON_PRETTY_PRINT));
		return $count;
	}

	private function apiRequestJson($method, $parameters) {
		if (!is_string($method)) {
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		}
		else if (!is_array($parameters)) {
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

	private function exec_curl_request($handle) {
		$response = curl_exec($handle);

		if ($response === false) {
			$errno = curl_errno($handle);
			$error = curl_error($handle);
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
			//file_put_contents('log_jokes', "Request has failed with error {$response['error_code']}: {$response['description']}\n", FILE_APPEND);
			return false;
		}
		else {
			$response = json_decode($response, true);
			$response = $response['result'];
		}

		return $response;
	}
}
