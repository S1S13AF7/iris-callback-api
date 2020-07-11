<?php
class UbCallbackSendMySignal implements UbCallbackAction {

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
		$in = $object['value']; // наш сигнал
		$time = $vk->getTime(); // ServerTime

		if ($in == 'ping' || $in == 'пинг' || $in == 'пінг' || $in == 'пінґ' || $in == 'зштп') {
				$vk->chatMessage($chatId, "PONG\n" .($time - $message['date']). " сек");
				return;
		}

		if ($in == '+admin' || $in == '+адмін' || $in == '+админ' || $in == '+фвьшт') {
				$ids = $vk->GetUsersIdsByFwdMessages($chatId, $object['conversation_message_id']);
				if(!count($ids)) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл пользователей');
				return; } elseif(count($ids) > 3) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' может не стоит делать много админов?');
				return; }
				foreach($ids as $id) {
				$res=$vk->messagesSetMemberRole($chatId, $id, $role = 'admin');
				if(isset($res['error'])) { $vk->chatMessage($chatId,UB_ICON_WARN.$res["error"]["error_msg"]); }
				sleep(1);
				}

				return;

		}

		if ($in == '-admin' || $in == '-адмін' || $in == '-админ' || $in == '-фвьшт' || $in == 'снять') {
				$ids = $vk->GetUsersIdsByFwdMessages($chatId, $object['conversation_message_id']);
				if(!count($ids)) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл пользователей');
				return; }
				foreach($ids as $id) {
				$res=$vk->messagesSetMemberRole($chatId, $id, $role = 'member');
				if(isset($res['error'])) { $vk->chatMessage($chatId,UB_ICON_WARN.$res["error"]["error_msg"]); }
				sleep(1);
				}

				return;

		}

		if ($in == 'др' || $in == '+др' || $in == '+друг' || $in  == 'дружба' || $in  == '+дружба') {
				$ids = $vk->GetUsersIdsByFwdMessages($chatId, $object['conversation_message_id']);
				if(!count($ids)) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл пользователей');
				return; } elseif(count($ids) > 5) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' Многабукаф,ниасилил');
				return; }

				$msg = '';
				$cnt = 0;

				foreach($ids as $id) {
								$fr='';
								$cnt++;
				$are = $vk->AddFriendsById($id);
				if ($are == 3) {
								$fr = UB_ICON_SUCCESS . " @id$id ok\n";
				} elseif ($are == 1) {
								$fr =  UB_ICON_INFO . " отправлена заявка/подписка пользователю @id$id\n";
				} elseif ($are == 2) {
								$fr =  UB_ICON_SUCCESS . " заявка от @id$id одобрена\n";
				} elseif ($are == 4) {
								$fr =  UB_ICON_WARN . " повторная отправка заявки @id$id\n";
				} elseif(is_array($are)) {
								$fr = UB_ICON_WARN . " $are[error_msg]\n"; 
						if ($are["error"]["error_code"] == 174) $fr = UB_ICON_WARN . " ВК не разрешает дружить с собой\n";
						if ($are["error"]["error_code"] == 175) $fr = UB_ICON_WARN . " @id$id Удилите меня из ЧС!\n";
						if ($are["error"]["error_code"] == 176) $fr = UB_ICON_WARN . " @id$id в чёрном списке\n"; }
								sleep($cnt);
								$msg.=$fr;
						}

				if (isset($msg)) {
				$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);
				}

				return;
		}

		if ($in == 'прийом') {
				$add = $vk->confirmAllFriends();
				$msg = $add ? '+'.$add : 'НЕМА';
				$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);
				return;
		}

		if ($in == 'отмена' || $in == 'отписка') {
				$del = $vk->cancelAllRequests();
				$msg = $del ? "скасовано: $del": 'НЕМА';
				$vk->chatMessage($chatId, $msg);
				return;
		}

		if ($in == 'обновить' || $in == 'оновити') {
				$getChat = $vk->getChat($chatId);
				$chat = $getChat["response"];
				$upd = "UPDATE `userbot_bind` SET `title` = '$chat[title]' WHERE `code` = '$object[chat]';";
				UbDbUtil::query($upd);
				return;
		}

		if ($in == 'info' || $in == 'інфо' || $in == 'інфа' || $in == 'инфо' || $in == 'инфа') {
		$chat = UbDbUtil::selectOne('SELECT * FROM userbot_bind WHERE id_user = ' . UbDbUtil::intVal($userId) . ' AND code = ' . UbDbUtil::stringVal($object['chat']));
		$getChat = $vk->getChat($chatId);
		if(!$chat['title']) {
				$chat['title'] = (isset($getChat["response"]["title"]))?(string)@$getChat["response"]["title"]:'';
				$upd = "UPDATE `userbot_bind` SET `title` = '$chat[title]' WHERE `code` = '$object[chat]';";
				UbDbUtil::query($upd); }
		$isD = ($chat['id_duty']==$userId);
		$msg = "Chat id: $chatId\n";
		$msg.= "Iris id: $object[chat]\n";
		$msg.= "Chat title: $chat[title]\n";
		$msg.= 'Я дежурный?: '.($isD?'да':'нет')."\n";
		if (!$isD && $chat['id_duty']) {
		$msg.= "Дежурный: @id$chat[id_duty]\n"; }
		$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);
		return;
		}

		if ($in == 'check_dogs' || $in == 'чек_собак') {
		$res = $vk->getChat($chatId, 'deactivated');
		$all = $res["response"]["users"];
		$msg ='';
		$dogs= 0;

        foreach ($all as $user) {
            
            $name= (string)@$user["first_name"] .' ' . (string) @$user["last_name"];
            $dog = (string)@$user["deactivated"];

            if ($dog) {
                $dogs++; 
                $del = $vk->DelFriendsById($user["id"]);

                $msg.= "$dogs. [id$user[id]|$name] ($dog)\n";
            }

         }

         if(!$dogs) {
            $msg = 'НЕМА'; }
		$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);

		$friends = $vk->vkRequest('friends.get', "count=5000&fields=deactivated");
		$count = (int)@$friends["response"]["count"];
				$dogs = 0;
				$msg = '';
		if ($count && isset($friends["response"]["items"])) {
				$items = $friends["response"]["items"];

        foreach ($items as $user) {
            
            $name= (string) @$user["first_name"] .' ' . (string) @$user["last_name"];
            $dog = (string)@$user["deactivated"];

            if ($dog) {
                $dogs++; 
                $del = $vk->DelFriendsById($user["id"]);
                $msg.= "$dogs. [id$user[id]|$name] ($dog)\n";
            }
         }
    }

		if ($dogs) { $vk->SelfMessage($msg); }
				return;
		}

		if ($in == '-смс') {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = $msg['response']['items'][0]['id']; // будем редактировать своё
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, "... удаляю сообщения ...");
				$GetHistory = $vk->messagesGetHistory(UbVkApi::chat2PeerId($chatId), 1, 200);
				$messages = $GetHistory['response']['items'];
				$ids = Array();
				foreach ($messages as $m) {
				$away = $time-$m["date"];
				if ((int)$m["from_id"] == $userId && $away < 84000 && !isset($m["action"])) {
				$ids[] = $m['id']; }
				}
				if (!count($ids)) {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, ' Не нашёл сообщений для удаления');
				$vk->messagesDelete($mid, true); 
				return; }

				$res = $vk->messagesDelete($ids, true);

				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, count($ids));
				$vk->messagesDelete($mid, true); 
				return;
		}

		if (preg_match('#^-смс ([0-9]{1,3})#', $in, $c)) {
				$amount = (int)@$c[1];
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = $msg['response']['items'][0]['id']; // будем редактировать своё
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, "... удаляю сообщения ...");
				$GetHistory = $vk->messagesGetHistory(UbVkApi::chat2PeerId($chatId), 1, 200);
				$messages = $GetHistory['response']['items'];
				$ids = Array();
				foreach ($messages as $m) {
				$away = $time-$m["date"];
				if ((int)$m["from_id"] == $userId && $away < 84000 && !isset($m["action"])) {
				$ids[] = $m['id']; 
				if ($amount && count($ids) >= $amount) break;				}
				}
				if (!count($ids)) {
				$vk->messagesDelete($mid, true); 
				return; }

				$res = $vk->messagesDelete($ids, true);
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, count($ids));
				$vk->messagesDelete($mid, true); 
				return;
		}

		if ($in == 'бпт' || $in == 'бптайм'  || $in == 'bptime') {
				$ago = time() - (int)@$userbot['bptime'];
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = $msg['response']['items'][0]['id'];
				if(!$userbot['bptime']) { 
				$msg = UB_ICON_WARN . ' не задан';
				} elseif($ago < 59) {
				$msg = "$ago сек. назад";
				} elseif($ago / 60 > 1 and $ago / 60 < 59) {
				$min = floor($ago / 60 % 60);
				$msg = $min . ' минут' . self::number($min, 'а', 'ы', '') . ' назад';
				} elseif($ago / 3600 > 1 and $ago / 3600 < 23) {
				$min = floor($ago / 60 % 60);
				$hour = floor($ago / 3600 % 24);
				$msg = $hour . ' час' . self::number($hour, '', 'а', 'ов') . ' и ' .
				$min . ' минут' . self::number($min, 'а', 'ы', '') . ' тому назад';
				} else {
				$msg = UB_ICON_WARN . ' более 23 часов назад';
				$vk->SelfMessage("$msg"); sleep(1); }
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, $msg);
				return;
		}

		if (preg_match('#^бпт ([a-z0-9]{85})#', $in, $t)) {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = $msg['response']['items'][0]['id'];
				$res = $vk->addBotToChat('-174105461', $chatId, $t[1]);
				#res = $vk->addBotToChat('-182469235', $chatId, $t[1]);
				if (isset($res['error'])) {
				$error = UbUtil::getVkErrorText($res['error']);
				if ($error == 'Пользователь уже в беседе') {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_SUCCESS); 
				$setbpt = 'UPDATE `userbot_data` SET `btoken` = '.UbDbUtil::stringVal($t[1]).', `bptime` = ' . UbDbUtil::intVal(time()).' WHERE `id_user` = ' . UbDbUtil::intVal($userId);
				$upd = UbDbUtil::query($setbpt);
				$vk->messagesDelete($mid, true); } else 
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN . ' ' . $error); }
				return;
		}

		if (preg_match('#(Iris|Ирис) в ([0-9]+)#ui', $in, $c)) {
				$res = $vk->addBotToChat('-174105461', $c[2], @$userbot['btoken']);
				if (isset($res['error'])) {
				$error = UbUtil::getVkErrorText($res['error']);
				$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error); }
				return;
		}

		$vk->chatMessage($chatId, UB_ICON_WARN . ' ФУНКЦИОНАЛ НЕ РЕАЛИЗОВАН');
	}

    static function number($num, $one, $two, $more) {
        $num = (int)$num;
        $l2 = substr($num, strlen($num) - 2, 2);

        if ($l2 >= 5 && $l2 <= 20)
            return $more;
        $l = substr($num, strlen($num) - 1, 1);
        switch ($l) {
            case 1:
                return $one;
                break;
            case 2:
                return $two;
                break;
            case 3:
                return $two;
                break;
            case 4:
                return $two;
                break;
            default:
                return $more;
                break;
        }
    }

}