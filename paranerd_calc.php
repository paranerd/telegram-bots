<?php

$update = file_get_contents("php://input");

$calc = new Calculator($update);
$calc->exec();

class Calculator {
	static $BOT_TOKEN	= '296556292:AAHAxNx1hJ074QQH5_rqJI2aSrLRKlpBtz4';
	static $API_URL		= 'https://api.telegram.org/bot' . '296556292:AAHAxNx1hJ074QQH5_rqJI2aSrLRKlpBtz4';

	private $chat_id;
	private $chat_text;
	private $update_raw;
	private $update;

	public function __construct($update_raw) {
		$this->update_raw = $update_raw;
	}

	public function exec() {
		if ($this->update_raw) {
			$this->update = json_decode($this->update_raw, true);
			$this->chat_id = $this->update['message']['chat']['id'];
			$this->chat_text = $this->update['message']['text'];

			if ($this->check_format($this->chat_text)) {
				$ops = $this->extract_operands($this->chat_text);
				$solution = $this->solve($ops['a'], $ops['b'], $ops['op']);

				$this->respond_private($solution);
			}
			else {
				if ($this->chat_text != "/start") {
					$this->respond_private("Format error... Try 5+5");
				}
			}
		}
	}

	private function check_format($chat_text) {
		return preg_match('/^([0-9]+)([\+\-\/\*])([0-9]+)$/', $chat_text);
	}

	private function extract_operands($chat_text) {
		for ($i = 0; $i < strlen($chat_text); $i++) {
			if (!preg_match('/^[0-9]/', substr($chat_text, $i, 1))) {
				$a = substr($chat_text, 0, $i);
				$op = substr($chat_text, $i, 1);
				$b = substr($chat_text, $i+1);

				return array('a' => $a, 'b' => $b, 'op' => $op);
			}
		}
	}

	private function solve($a, $b, $op) {
		switch ($op) {
			case '+':
				return $this->add($a, $b);

			case '-':
				return $this->subtract($a, $b);

			case '*':
				return $this->multiply($a, $b);

			case '/':
				return $this->divide($a, $b);

			default:
				$this->respond_private('Unknown operand');
		}
	}

	private function add($a, $b) {
		return $a + $b;
	}

	private function subtract($a, $b) {
		return $a - $b;
	}

	private function multiply($a, $b) {
		return $a * $b;
	}

	private function divide($a, $b) {
		return $a / $b;
	}

	private function respond_private($msg) {
		file_get_contents(self::$API_URL . '/sendmessage?chat_id=' . $this->chat_id . '&text=' . urlencode($msg));
	}
}
