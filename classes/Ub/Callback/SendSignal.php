<?php
class UbCallbackSendSignal implements UbCallbackAction {

	function closeConnection() {
		@ob_end_clean();
		@header("Connection: close");
		@ignore_user_abort(true);
		@ob_start();
		echo 'ok';
		$size = ob_get_length();
		@header("Content-Length: $size");
		@ob_end_flush(); // All output buffers must be flushed here
		@flush(); // Force output to client
	}

	function execute($userId, $object, $userbot, $message) {
		$chatId = UbUtil::getChatId($userId, $object, $userbot, $message);
		if (!$chatId) {
			UbUtil::echoError('no chat bind', UB_ERROR_NO_CHAT);
			return;
		}

		self::closeConnection();

		$vk = new UbVkApi($userbot['token']);
		$in = $object['value']; // сам сигнал
		$id = $object['from_id']; // от кого
		$time = $vk->getTime(); // ServerTime

		if ($in == 'ping' || $in == 'пинг'  || $in == 'пінг'  || $in == 'пінґ') {
				$vk->chatMessage($chatId, "PONG\n" .($time - $message['date']). " сек");
				return;
		}

		$vk->chatMessage($chatId, 'Мне прислали сигнал. От пользователя @id' . $object['from_id'], ['disable_mentions' => 1]);
	}

}