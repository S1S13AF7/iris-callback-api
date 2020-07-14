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
		#time = $vk->getTime(); // ServerTime
		$time = time(); # время этого сервера


		if ($in == 'ping' || $in == 'пинг'  || $in == 'пінг'  || $in == 'пінґ') {
				$time = $vk->getTime(); /* ServerTime — текущее время сервера ВК */
				$vk->chatMessage($chatId, "PONG\n" .($time - $message['date']). " сек");
				return;
		}

		if ($in == 'др' || $in == '+др' || $in == '+друг' || $in  == 'дружба' || $in  == '+дружба') {
				$ids = $vk->GetUsersIdsByFwdMessages($chatId, $object['conversation_message_id']);
				$ids[$id] = $id; /*+дружба с самим юзером, независимо от наличия "fwd_messages" */

				if(count($ids) > 6) {
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
						if ($are["error"]["error_code"] == 175) $fr = UB_ICON_WARN . " @id$id Удилите дежурного из ЧС!\n";
						if ($are["error"]["error_code"] == 176) $fr = UB_ICON_WARN . " @id$id Вы в ЧС у дежурного\n"; }
								sleep($cnt);
								$msg.=$fr;
						}

				if (isset($msg)) {
						$vk->chatMessage($chatId, $msg);
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
				$upd = "UPDATE `userbot_bind` SET `title` = '$chat[title]', `id_duty` = '". UbDbUtil::intVal($userbot['id_user']) ."' WHERE `code` = '$object[chat]';";
				UbDbUtil::query($upd);
				return;
		}

		if ($in == 'info' || $in == 'інфо' || $in == 'інфа' || $in == 'инфо' || $in == 'инфа') {
		$chat = UbDbUtil::selectOne('SELECT * FROM userbot_bind WHERE id_user = ' . UbDbUtil::intVal($userId) . ' AND code = ' . UbDbUtil::stringVal($object['chat']));
		$getChat = $vk->getChat($chatId);
		if(!$chat['title'] || $chat['id_duty'] != $userId) {
				$chat['title'] = (isset($getChat["response"]["title"]))?(string)@$getChat["response"]["title"]:'';
				$upd = "UPDATE `userbot_bind` SET `title` = '$chat[title]', `id_duty` = '". UbDbUtil::intVal($userbot['id_user']) ."' WHERE `code` = '$object[chat]';";
				UbDbUtil::query($upd); }

		$msg = "Chat id: $chatId\n";
		$msg.= "Iris id: $object[chat]\n";
		$msg.= "Chat title: $chat[title]\n";
		$msg.= "Дежурный: @id$userId\n";
		$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);
		return;
		}

		if ($in == 'check_dogs' || $in == 'чек_собак' || $in == 'kick_dogs' || $in == 'кик_собак') {
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
            if ($in == 'kick_dogs' || $in == 'кик_собак') {
                $kick=$vk->messagesRemoveChatUser($chatId, $user["id"]);
            if (isset($kick['error']))$dog = (string)@$kick['error']['error_msg']; }
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
				$GetHistory = $vk->messagesGetHistory(UbVkApi::chat2PeerId($chatId), 1, 200);
				$messages = $GetHistory['response']['items'];
				$ids = Array();
				foreach ($messages as $m) {
				$away = $time - $m["date"];
				if ((int)$m["from_id"] == $userId && $away < 84000 && !isset($m["action"])) {
				$ids[] = $m['id']; }
				}
				if (!count($ids)) {
				#$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл сообщений для удаления');
				return; }

				$res = $vk->messagesDelete($ids, true);

				return;
		}

		if (preg_match('#^-смс ([0-9]{1,3})#', $in, $c)) {
				$amount = (int)@$c[1];
				$GetHistory = $vk->messagesGetHistory(UbVkApi::chat2PeerId($chatId), 1, 200);
				$messages = $GetHistory['response']['items'];
				$ids = Array();
				foreach ($messages as $m) {
				$away = $time - $m["date"];
				if ((int)$m["from_id"] == $userId && $away < 84000 && !isset($m["action"])) {
				$ids[] = $m['id']; 
				if ($amount && count($ids) >= $amount) break;				}
				}
				if (!count($ids)) {
				#$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл сообщений для удаления');
				return; }

				$res = $vk->messagesDelete($ids, true);

				return;
		}

		if ($in == 'бпт' || $in == 'бптайм'  || $in == 'bptime') {
				$ago = time() - (int)@$userbot['bptime'];
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
				$vk->chatMessage($chatId, $msg);
				return;
		}

		if (preg_match('#^(Iris|Ирис) в ([0-9]+)#ui', $in, $c)) {
				$res = $vk->addBotToChat('-174105461', $c[2], @$userbot['btoken']);
				if (isset($res['error'])) {
				$error = UbUtil::getVkErrorText($res['error']);
				$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error); }
				return;
		}

		if (preg_match('#^(добавь|верни) в ([a-z0-9]{8})#ui', $in, $c)) {
				$toChat = UbDbUtil::selectOne('SELECT * FROM userbot_bind WHERE id_user = ' . UbDbUtil::intVal($userId) . ' AND code = ' . UbDbUtil::stringVal($c[2]));

		if(!$toChat) {
				$vk->chatMessage($chatId,  UB_ICON_WARN . ' no bind chat ' . $c[2]);
				return; }

				$res = $vk->messagesAddChatUser($object['from_id'], $toChat['id_chat'], @$userbot['btoken']);
		if (isset($res['error'])) {
				$error = UbUtil::getVkErrorText($res['error']);
				$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error);
				}

				return;

		}

		$vk->chatMessage($chatId, 'Мне прислали сигнал. От пользователя @id' . $object['from_id'], ['disable_mentions' => 1]);
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