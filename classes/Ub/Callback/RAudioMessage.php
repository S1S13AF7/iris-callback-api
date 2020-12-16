<?php
class UbCallbackRAudioMessage implements UbCallbackAction {

	/*{
			"method":"messages.recogniseAudioMessage",
			"user_id":int,
			"secret":string,
			"message":null,
			"object":{
			"local_id":int,
			"chat":string
			}
		}
	*/


	function closeConnectionAndShowText($text) {
		@ob_end_clean();
		@header("Connection: close");
		@ignore_user_abort(true);
		@ob_start();
		echo json_encode(['response' => 'ok', 'transcript' => (string)@$text], JSON_UNESCAPED_UNICODE);
		$size = ob_get_length();
		@header("Content-Length: $size");
		@ob_end_flush(); // All output buffers must be flushed here
		@flush(); // Force output to client
	}

	function recogniseAudioMessage($userId, $object, $userbot, $message) {
		$chatId = UbUtil::getChatId($userId, $object, $userbot, $message);
		$localId = (int)@$object['local_id'];
		$vk = new UbVkApi($userbot['token']);

		if(!$localId) {
			UbUtil::echoError('no data', UB_ERROR_NO_DATA);
			return;
		}

		$message = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $localId);

		if (isset($message['error'])) {
				return UbUtil::echoErrorVkResponse($res['error']);
		}
		
		$message = $message['response']['items'][0];
		if(!isset($message['attachments'])) {
				return UbUtil::echoError('!isset($message[attachments])', UB_ERROR_NO_DATA);
		}
		$attach = $message['attachments'][0];
		$type = $attach['type'];
		if($type != 'audio_message') {
				return UbUtil::echoError("$type != audio_message", UB_ERROR_NO_DATA);
		}

		$_arr =$attach["$type"];
		if (isset($_arr['transcript']) && (string)@$_arr['transcript_state'] == 'done') {
				return self::closeConnectionAndShowText((string)@$_arr['transcript']);
		} else {
			sleep(1);
			self::recogniseAudioMessage($userId, $object, $userbot, $message);
		}

	}

	function execute($userId, $object, $userbot, $message) {
		$localId = (int)@$object['local_id'];

		if(!$localId) {
			UbUtil::echoError('no data', UB_ERROR_NO_DATA);
			return;
		} else {
			self::recogniseAudioMessage($userId, $object, $userbot, $message);
		}
	}
}