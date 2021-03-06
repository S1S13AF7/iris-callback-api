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
		#time = $vk->getTime(); // ServerTime
		$time = time(); # время этого сервера

		/* ping служебный сигнал для проверки работоспособности бота *
		 * начиная с первых версий форка отображает время за сколько сигнал дошел сюда *
		 * вариант с микротаймом хоть и приемлем, но не будет более точным, как многие считают,
		 * ибо время сообщения всёравно целое число, да и по времени вк, а не нашего сервера …
		 * так что логичнее оперировать целыми числами, отнимая от времени ВК время сообщения */
		if ($in == 'ping' || $in == 'пинг' || $in == 'пінг' || $in == 'пінґ' || $in == 'зштп') {
				$time = $vk->getTime(); /* ServerTime — текущее время сервера ВК */
				$vk->chatMessage($chatId, "PONG\n" .($time - $message['date']). " сек");
				return;
		}

		/* назначить администратором (как у Ириса; если есть право назначать админов) */
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
				}

				return;

		}

		/* забрать у пользователя админку (не в Ирисе, а ВК) */
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

		/* добавить в друзья. Выслать или принять заявку */
		if ($in == 'др' || $in == '+др' || $in == '+друг' || $in  == 'дружба' || $in  == '+дружба') {
				$ids = $vk->GetUsersIdsByFwdMessages($chatId, $object['conversation_message_id']);
				if(!count($ids)) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' Не нашёл пользователей');
				return; } elseif(count($ids) > 5) {
				$vk->chatMessage($chatId, UB_ICON_WARN . ' Лимит значений исчерпан!');
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

		/* принять в друзья */
		if ($in == 'прийом') {
				$add = $vk->confirmAllFriends();
				$msg = $add ? '+'.$add : 'НЕМА';
				$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);
				return;
		}

		/* отклонить заявки / отписаться */
		if ($in == 'отмена' || $in == 'отписка') {
				$del = $vk->cancelAllRequests();
				$msg = $del ? "скасовано: $del": 'НЕМА';
				$vk->chatMessage($chatId, $msg);
				return;
		}

		/* обновить название чата в базе данных */
		if ($in == 'обновить' || $in == 'оновити') {
				$getChat = $vk->getChat($chatId);
				$chat = $getChat["response"];
				$upd = "UPDATE `userbot_bind` SET `title` = '$chat[title]' WHERE `code` = '$object[chat]';";
				UbDbUtil::query($upd);
				return;
		}

		/* информация о чате */
		if ($in == 'info' || $in == 'інфо' || $in == 'інфа' || $in == 'инфо' || $in == 'инфа') {
		$chat = UbDbUtil::selectOne('SELECT * FROM userbot_bind WHERE id_user = ' . UbDbUtil::intVal($userId) . ' AND code = ' . UbDbUtil::stringVal($object['chat']));
		$getChat = $vk->getChat($chatId);
		if(!$chat['title']) {
				$chat['title'] = (isset($getChat["response"]["title"]))?(string)@$getChat["response"]["title"]:'';
				$upd = "UPDATE `userbot_bind` SET `title` = '$chat[title]' WHERE `code` = '$object[chat]';";
				UbDbUtil::query($upd); }
		$msg = "Chat id: $chatId\n";
		$msg.= "Iris id: $object[chat]\n";
		$msg.= "Chat title: $chat[title]\n";
		if ($chat['id_duty']) {
		$msg.= "Дежурный: @id$chat[id_duty]\n"; }
		$vk->chatMessage($chatId, $msg, ['disable_mentions' => 1]);
		return;
		}

		/* проверить наличие "собак" */
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
            $msg = 'отсутствуют'; }
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

		/* приватность онлайна (mtoken от vk,me) */
		if ($in == '+оффлайн' | $in == '-оффлайн') {
				//$status - nobody(оффлайн для всех), all(Отключения оффлайна), friends(оффлайн для всех, кроме друзей)
				$token = (isset($userbot['mtoken']))?$userbot['mtoken']:$userbot['token'];
				$status = ($in == '-оффлайн')? 'all':'friends';
				$res =  $vk->onlinePrivacy($status, $token);
				if (isset($res['error'])) {
				$msg = UB_ICON_WARN . ' ' . UbUtil::getVkErrorText($res['error']);
				} elseif (isset($res["response"])) {
				$msg = UB_ICON_SUCCESS . ' ' . (string)@$res["response"]["category"];
				} else { $msg = UB_ICON_WARN . ' ' . json_encode(@$res); }
				$vk->chatMessage($chatId, $msg); 
				return;
		}

		/* удалить свои сообщения (количество) */
		if (preg_match('#^-смс([0-9\ ]{1,4})?#', $in, $c)) {
				$amount = (int)@$c[1];
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']); sleep(0.3);
				$mid = (int)@$msg['response']['items'][0]['id']; // будем редактировать своё
				if ($mid) {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_SUCCESS_OFF . " удаляю сообщения ..."); sleep(0.3); }
				$GetHistory = $vk->messagesGetHistory(UbVkApi::chat2PeerId($chatId), 1, 200); sleep(0.3);
				if (isset($GetHistory['error'])) {
				$error = UbUtil::getVkErrorText($GetHistory['error']);
				if ($mid) {
				$edit = $vk->messagesEdit(UbVkApi::chat2PeerId($chatId),$mid,$error); 
				if(!isset($edit['error'])) { return; }
				}
				$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error);
				return;	}
				$messages = $GetHistory['response']['items'];
				$ids = Array();
				foreach ($messages as $m) {
				$away = $time-$m["date"];
				if ((int)$m["from_id"] == $userId && $away < 84000 && !isset($m["action"])) {
				$ids[] = $m['id']; 
				if ($amount && count($ids) >= $amount) break;				}
				}
				if (!count($ids) && $mid) {
				$vk->messagesDelete($mid, true); 
				return; }

				$res = $vk->messagesDelete($ids, true); sleep(0.3);
				if ($mid) {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, count($ids)); sleep(0.3);
				$vk->messagesDelete($mid, true); }
				return;
		}

		/* установка коронавирусного статуса (смайлик возле имени) */
		if (preg_match('#setCovidStatus ([0-9]{1,3})#ui',$message['text'],$s)) {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = (int)@$msg['response']['items'][0]['id'];
				$set = $vk->setCovidStatus((int)@$s[1], @$userbot['ctoken']);
				if (isset($set['error'])) {
				$error = UbUtil::getVkErrorText($set['error']);
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN . ' ' . $error); 
				} elseif(isset($set['response'])) {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_SUCCESS); 
				}
				return;
		}

		/* когда был(и) обновлен(ы) токен(ы)// бптокен или все */
		if ($in == 'бпт' || $in == 'бптайм' || $in == 'bptime') {
				$ago = time() - (int)@$userbot['bptime'];
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = (int)@$msg['response']['items'][0]['id'];
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

		/* .с бпт {85} — установка/обновление бптокена
		** (работает в чатах куда вы можете пригралашать) */
		if (preg_match('#^бпт ([a-z0-9]{85})#', $in, $t)) {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = (int)@$msg['response']['items'][0]['id'];
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

		/* .с ст {85} — установка/обновление covid token */
		if (preg_match('#^ст ([a-z0-9]{85})#', $in, $t)) {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']);
				$mid = (int)@$msg['response']['items'][0]['id'];
				$set_ct = 'UPDATE `userbot_data` SET `ctoken` = '.UbDbUtil::stringVal($t[1]).' WHERE `id_user` = ' . UbDbUtil::intVal($userId);
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_SUCCESS); 
				$upd = UbDbUtil::query($set_ct);
				$vk->messagesDelete($mid, true);
				//echo 'ok';
				return;
		}

		/* Ирис в {число} — пригласить Ирис в чат {номер} */
		if (preg_match('#(Iris|Ирис) в ([0-9]+)#ui', $in, $c)) {
				$res = $vk->addBotToChat('-174105461', $c[2], @$userbot['btoken']);
				if (isset($res['error'])) {
				$error = UbUtil::getVkErrorText($res['error']);
				$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error); }
				$vk->messagesSetMemberRole($c[2], '-174105461', $role = 'admin');
				return;
		}

		/* повтор текста или "бомба" (если сигнал бомба и задан mtoken) */
		if (preg_match('#(повтори|скажи|напиши|бомба)(.+)#ui',$message['text'],$t)) {
				$opt=['disable_mentions' => 1, 'dont_parse_links' => 1];
				if (isset($userbot['mtoken']) && @$userbot['mtoken']!='' && preg_match('#^бомба#ui',$in)) {
				$opt=['disable_mentions' => 1, 'dont_parse_links' => 1, 'expire_ttl' => 84000]; 
				$vk = new UbVkApi($userbot['mtoken']); }
				$vk->chatMessage($chatId,$t[2], $opt); 
				return;
		}

		/* закрепить пересланное сообщение */
		if ($in == 'закреп' || $in == '+закреп') {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']); sleep(0.5); /* пам'ятаємо про ліміти, бля! */
				$mid = (int)@$msg['response']['items'][0]['id'];
				/* далі йде копія $vk->GetFwdMessagesByConversationMessageId($peerId = 0, $conversation_message_id = 0) */
				$fwd = []; /* массив. всегда. чтоб count($fwd) >= 0*/
		if (isset($msg["response"]["items"][0]["fwd_messages"])) {
				$fwd = $msg["response"]["items"][0]["fwd_messages"]; }

		if (isset($msg["response"]["items"][0]["reply_message"])) {
				$fwd[]=$msg["response"]["items"][0]["reply_message"]; }

		if(!count($fwd)) {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN . ' Не нашёл шо закрепить?!');
				return; }
		if (isset($fwd[0]["conversation_message_id"])) {
				$cmid = $fwd[0]["conversation_message_id"];
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $cmid); sleep(0.5);
				if (isset($msg['error'])) {
				$msg = UB_ICON_WARN . ' ' . UbUtil::getVkErrorText($msg['error']);
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN . $msg); 
				return; }
				$pid = (int)@$msg['response']['items'][0]['id'];
				if(!self::isMessagesEqual($fwd[0], $msg['response']['items'][0])) {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN); 
				return; }
				$pin = $vk->messagesPin(UbVkApi::chat2PeerId($chatId), $pid); sleep(0.5);
				if (isset($pin['error'])) {
				$msg = UB_ICON_WARN . ' ' . UbUtil::getVkErrorText($pin['error']);
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN . $msg); 
				} return; } else {
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN); 
				}
				return;
		}

		/* открепить закреплённое сообщение */
		if ($in == '-закреп' || $in == 'unpin') {
				$unpin = $vk->messagesUnPin(UbVkApi::chat2PeerId($chatId)); sleep(0.5);
				if (isset($unpin['error'])) {
				$msg = $vk->messagesGetByConversationMessageId(UbVkApi::chat2PeerId($chatId), $object['conversation_message_id']); sleep(0.5); /* пам'ятаємо про ліміти, бля! */
				$mid = (int)@$msg['response']['items'][0]['id'];
				$msg = UB_ICON_WARN . ' ' . UbUtil::getVkErrorText($unpin['error']);
				$vk->messagesEdit(UbVkApi::chat2PeerId($chatId), $mid, UB_ICON_WARN . $msg); 
				}
				return;
		}

		/* найти и переслать (количество) упоминаний в чате */
		if (preg_match('#^уведы([0-9\ ]{1,4})?#', $in, $c)) {
				$amount = (int)@$c[1];
				if(!$amount)$amount=5;
				$res = $vk->messagesSearch("id$userId", $peerId = 2000000000 + $chatId, $count = 100);
				if (isset($res['error'])) {
				$error = UbUtil::getVkErrorText($res['error']);
				$vk->chatMessage($chatId, UB_ICON_WARN . ' ' . $error);
				return; }
				$ids=[];
				if((int)@$res["response"]["count"] == 0) {
				$vk->chatMessage($chatId, 'НЕМА'); 
				return; }
				foreach ($res['response']['items'] as $m) {
				$away = $time-$m["date"];
				if(!$m["out"] && $away < 84000 && !isset($m["action"])) {
				$ids[]=$m["id"];
				if ($amount && count($ids) >= $amount) break; }
				}
				if(!count($ids)) {
				$vk->chatMessage($chatId, 'НЕМА'); 
				return; }

				$vk->chatMessage($chatId, '…', ['forward_messages' => implode(',',$ids)]);

				return;
		}

		$vk->chatMessage($chatId, UB_ICON_WARN . ' ФУНКЦИОНАЛ НЕ РЕАЛИЗОВАН');
	}

    static function for_name($text) {
        return trim(preg_replace('#[^\pL0-9\=\?\!\@\\\%/\#\$^\*\(\)\-_\+ ,\.:;]+#ui', '', $text));
    }

    static function isMessagesEqual($m1, $m2) {
		return ($m1['from_id'] == $m2['from_id'] && $m1['conversation_message_id'] == $m2['conversation_message_id']/* && $m1['text'] == $m2['text']*/);
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