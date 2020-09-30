<?php
##### ВНИМАНИЕ
// Этот файл для того, чтобы можно было в форме заносить пользователей с токенами
// Спрячьте этот файл в папку, где требуется особый доступ, а затем уберите блокирующий "return" (он строчкой ниже)
//return;
ini_set("display_errors" , 1);
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
header('Cache-Control: no-store, no-cache, must-revalidate', true);
header('Content-Type: text/html; charset=utf-8', true);
header('X-UA-Compatible: IE=edge', true); /* 4 MSIE */
echo '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru"><head>
<meta http-equiv="X-UA-Compatible" content="IE=edge;chrome=1" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="initial-scale=1,width=device-width" />
<style type="text/css">
	html, body, table, * { margin: 0 auto; }
	body, table { background: transparent; margin: 0 auto; max-width: 800px; }
	html { background: transparent; margin: 0; padding: 0; text-align: center; }
</style></head><body style="margin:0px auto;max-width:800px;min-widht:100px;">
';

    function passgen($len = 32) {
    $password = '';
    $small = 'abcdefghijklmnopqrstuvwxyz';
    $large = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '1234567890';
    for ($i = 0; $i < $len; $i++) {
        switch (mt_rand(1, 3)) {
            case 3 :
                $password .= $large [mt_rand(0, 25)];
                break;
            case 2 :
                $password .= $small [mt_rand(0, 25)];
                break;
            case 1 :
                $password .= $numbers [mt_rand(0, 9)];
                break;
        }
    }
    return $password;
    }

	require_once("classes/base.php");
	require_once(CLASSES_PATH . "Ub/DbUtil.php");
	require_once(CLASSES_PATH . 'Ub/VkApi.php');
	require_once(CLASSES_PATH . "Ub/Util.php");

	function token($data = ''){
	$token = false;
	if (preg_match('#([a-z0-9]{85})#', $data, $t)) {
	$token = (string)$t[1]; }
	return $token ? $token:'';
	}

if (isset($_POST['token']) || isset($_POST['mtoken'])) {
	$token =  token(isset($_POST['token'])?@$_POST['token']:@$_POST['mtoken']);
	$mtoken = token(isset($_POST['mtoken'])?@$_POST['mtoken']:@$_POST['token']);
	$btoken = token(isset($_POST['btoken'])?@$_POST['btoken']:'');
	$ctoken = token(isset($_POST['ctoken'])?@$_POST['ctoken']:'');
	$secret = isset($_POST['secret'])?(string)@$_POST['secret']:passgen(mt_rand(8, 32));
	if(!$token && !$mtoken) {
		echo '<h1>Ошибище</h1>';
		echo '<p>Введенные вами данные не похожи на токены</p>';
		return;
	} elseif (strlen($secret) < 8) {
		echo '<h1>Ошибище</h1>';
		echo UB_ICON_NOTICE . ' Длина секрета сообщения должна быть не менее 8 символов';
		return;
	} elseif (strlen($secret) > 50) {
		echo '<h1>Ошибище</h1>';
		echo UB_ICON_NOTICE . ' Длина секрета сообщения должна быть не более 50 символов';
		return;
	}
	$bptime = (int)time();
	$vk = new UbVkApi($token);
	$me = $vk->usersGet();
	if (isset($me['error'])) {
		echo '<h1>Ошибище</h1>';
		echo '<p>' . $me['error']['error_msg'] . ' (' . $me['error']['error_code'] . ')</p>';
		return;
	}
	$userId = (int)@$me['response'][0]['id'];
	if(!$userId) {
		echo '<h1>Ошибище</h1>';
		echo '<p>id не получен</p>';
		return;
	}
	UbDbUtil::query('INSERT INTO userbot_data SET id_user = ' . UbDbUtil::intVal($userId) . ', token = ' . UbDbUtil::stringVal($token)
		 . ', btoken = ' . UbDbUtil::stringVal($btoken)
		 . ', mtoken = ' . UbDbUtil::stringVal($mtoken)
		 . ', bptime = ' . UbDbUtil::intVal($bptime)
		 . ', secret = ' . UbDbUtil::stringVal($_POST['secret'])
		 . ' ON DUPLICATE KEY UPDATE token = VALUES(token)'
			. ', btoken = VALUES(btoken)'
			. ', mtoken = VALUES(mtoken)'
			. ', bptime = VALUES(bptime)'
			. ', secret = VALUES(secret)'
	);
	$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".str_replace('adduser', 'callback', $_SERVER['SCRIPT_NAME']);
	$msg = '+api ' . htmlspecialchars($_POST['secret']) . ' ' . $actual_link ;
	$reg = $vk->vkRequest('messages.send', 'random_id=' . mt_rand(0, 2000000000) . '&user_id=' . -174105461 . "&message=".urlencode($msg));
	if (isset($reg['error'])) {
		echo '<h1>Ошибище</h1>';
		echo '<p>' . $reg['error']['error_msg'] . ' (' . $reg['error']['error_code'] . ')</p>';
		return;
	}
	echo UB_ICON_SUCCESS . ' Добавлено<br />'/*
	. 'Теперь в лс бота введите "+api ' . htmlspecialchars($_POST['secret']) . ' ' . $actual_link . '"'*/
	;
	return;
}
?>
<div style="margin: 0 auto; max-width: 600px; padding: 4% 0; border:#911 solid; opacity:0.9;"/>
<form action="" method="post">
<table>
<tr>
	<td>KM Токен</td>
	<td><input type="text" name="token" value="" placeholder="Токен" style="max-width:200px">
	<a href="https://oauth.vk.com/authorize?client_id=2685278&display=mobile&scope=notify,friends,photos,audio,video,docs,status,notes,pages,wall,groups,messages,offline,notifications&redirect_uri=https://api.vk.com/blank.html&response_type=token&v=5.92" 
	  target="_blank" rel="external">»</a>
	</td>
</tr>
<tr>
	<td>ME Токен</td>
	<td><input type="text" name="mtoken" value="" placeholder="Токен" style="max-width:200px">
	<a href="https://oauth.vk.com/authorize?client_id=6146827&display=mobile&scope=notify,friends,photos,audio,video,docs,status,notes,pages,wall,groups,messages,offline,notifications&redirect_uri=https://api.vk.com/blank.html&response_type=token&v=5.92"
	  target="_blank" rel="external">»</a>
	</td>
</tr>
<tr>
	<td>БП Токен</td>
	<td><input type="text" name="btoken" value="" placeholder="Токен" style="max-width:200px">
	<a href="https://oauth.vk.com/authorize?client_id=6441755&redirect_uri=https://api.vk.com/blank.html&display=mobile&response_type=token&revoke=1"
	  target="_blank" rel="external">»</a>
	</td>
</tr>
<!-- <tr>
	<td>Covid-19</td>
	<td><input type="text" name="ctoken" value="" placeholder="Токен" style="max-width:200px">
	<a href="https://oauth.vk.com/authorize?client_id=7362610&redirect_uri=https://api.vk.com/blank.html&display=mobile&response_type=token&revoke=1"
	  target="_blank" rel="external">»</a>
	</td>
</tr> -->
<tr>
	<td>Секретка</td>
	<td><input type="text" name="secret" value="<?php echo passgen(mt_rand(8, 16)); ?>" placeholder="Секретная фраза" style="max-width:200px">
	<u title="Секретный код может содержать только латинские символы и цифры.">?</u>
	</td>
</tr>
<tr>
	<td></td>
	<td><input type="submit" value="Добавить"></td>
</tr>
</table>
</form>
<?php echo UB_ICON_WARN; ?> Для получения любого из токенов, достаточно нажать на стрелочку после формы ввода и скопировать необходимую часть, которая выделена жирным ниже <p>https://api.vk.com/blank.html#access_token=<strong>net25713724013023tokenexampled949763123<br />d80afa87fc9320c6tokenexamplee7506atokenexample</strong>&amp;expires_in=0&amp;user_id=</p><br />
</div>
</body></html>