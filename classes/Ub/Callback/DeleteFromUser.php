<?php
class UbCallbackDeleteFromUser implements UbCallbackAction {

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
		$user_id = (int)$object['user_id'];
		$amount = (int)@$object['amount'];
		$isSpam = (bool)@$object['is_spam'];
		$vk = new UbVkApi($userbot['token']);
		$time = $vk->getTime(); // ServerTime

		//echo 'ok';
		self::closeConnection();

		$GetHistory = $vk->messagesGetHistory(UbVkApi::chat2PeerId($chatId), 1, 200);
		$messages = $GetHistory['response']['items'];
		$ids = Array();
		foreach ($messages as $m) {
		$away = $time - $m["date"];
		if ((int)$m["from_id"] === $user_id && $away < 84000 && !isset($m["action"])) {
		$ids[] = $m['id']; 
		if ($amount && count($ids) >= $amount) break;		 }
		}
		if (!count($ids)) {
		$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл сообщений для удаления');
		return; }

		$res = $vk->messagesDelete($ids, true, $isSpam);

		if (isset($res['error'])) {
		$error = UbUtil::getVkErrorText($res['error']);
		$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error);
		}

		return;
	}
}