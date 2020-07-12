<?php
class UbCallbackGroupInvited implements UbCallbackAction {

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
		$vk = new UbVkApi($userbot['token']);
		$group_id = (int) $object["group_id"];
		$bot_id = ($group_id > 0) ? "-$group_id" : $group_id;
		$result = $vk->messagesGetConversations(); sleep(1);
		if (isset($result['error'])) {
			return UbUtil::errorVkResponse($result['error']);
		}

		$goodChats = self::findChats($result, $message);
		$userChatId = 0;
		if ($goodChats['sure']) {
			$userChatId = UbVkApi::peer2ChatId($goodChats['items'][0]['peer_id']);
		} elseif(!count($goodChats['items'])) {
			UbUtil::echoJson(UbUtil::buildErrorResponse('error','не получено',0));
			return;
		} else {
			foreach ($goodChats['items'] as $chat) {
				$result = $vk->messagesGetHistory($chat['peer_id'],0,100); sleep(1);
				if (isset($result['error'])) {
					return UbUtil::errorVkResponse($result['error']);
				}
				foreach ($result['response']['items'] as $item) {
					if (self::isMessagesEqual($item, $message)) {
						$userChatId = UbVkApi::peer2ChatId($item['peer_id']);
					}
				}
				if ($userChatId)
					break;
			}
		}

		if ($userChatId) {
			self::closeConnection(); // echo 'ok';
			$t = $vk->messagesSetMemberRole($userChatId, $bot_id, $role = 'admin');
			$vk->chatMessage($userChatId, '!связать');
			return $userChatId;
		} else {
			UbUtil::echoJson(UbUtil::buildErrorResponse('error', 'БЕДЫ С API', 0));
			return;
		}
	}

	private static function findChats($result, $vkMessage) {
		$goodChats = [];
		$items = $result["response"]["items"];
		if(!count($items)) {
		return ['sure' => 0, 'items' => $goodChats];
		} /* ніхрнена */
		foreach ($items as $item) {
			$lm = $item['last_message'];
			$sLocal = $lm['conversation_message_id'];
			if ($sLocal > $vkMessage['conversation_message_id'] - 300 && $sLocal < $vkMessage['conversation_message_id'] + 300) {
				if (self::isMessagesEqual($vkMessage, $lm)/*$vkMessage['from_id'] == $lm['from_id'] && $vkMessage['conversation_message_id'] == $sLocal*//* && $lm['text'] == $vkMessage['text']*/)
					return ['sure' => 1, 'items' => [$item['last_message']]];
				$goodChats[] = $item['last_message'];
			}
		}
		return ['sure' => 0, 'items' => $goodChats];
	}

	private static function isMessagesEqual($m1, $m2) {
		return ($m1['from_id'] == $m2['from_id'] && $m1['conversation_message_id'] == $m2['conversation_message_id']/* && $m1['text'] == $m2['text']*/);
	}
}
