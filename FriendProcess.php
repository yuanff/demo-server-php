<?php
/**
* 按名称查询用户
* @UserFunction(method = GET)
* @CheckLogin
*/
function seach_name(String $username){
	$db = new DataBase(DB_DNS, DB_USER, DB_PASSWORD);
	return $db->fetchAll('SELECT `id`, `username`, `portrait` FROM `user` WHERE `username` like ? ORDER BY `username` LIMIT 20;', $username.'%');
}

/**
* 按邮箱查询用户
* @UserFunction(method = GET)
* @CheckLogin
*/
function seach_email(Email $email){
	$db = new DataBase(DB_DNS, DB_USER, DB_PASSWORD);
	return $db->fetch('SELECT `id`, `username`, `portrait` FROM `user` WHERE `email` = ?;', $email);
}

/**
* 发好友邀请
* @UserFunction(method = GET|POST)
* @CheckLogin
*/
function request_friend(Integer $id,String $message = null){
	if(getCurrentUserId() == $id){
		throw new ProException('connt add yourslef',301);
	}
	$currentUserId = getCurrentUserId();
	//status : 1 好友, 2 请求添加, 3 请求被添加, 4 请求被拒绝, 5 我被对方删除
	$db = new DataBase(DB_DNS, DB_USER, DB_PASSWORD);

	if ($db->fetchColumn('SELECT count(*) FROM `user` WHERE `id` = ?', $id) == 0) {
		throw new ProException('user is not exists', 300);
	}

	$my_friend_status = $db->fetch('SELECT `status` FROM `friend` WHERE `user_id` = ? AND `friend_id` = ?', $currentUserId, $id);
	$other_friend_status = $db->fetch('SELECT `status` FROM `friend` WHERE `user_id` = ? AND `friend_id` = ?', $id, $currentUserId);

	if ($my_friend_status && $other_friend_status) {
		if ($my_friend_status['status'] == 3) { //如果对方也加过我，则直接成为好友
			$db->exec('UPDATE `friend` SET `status` = 1 WHERE `user_id`=? AND `friend_id`=?;', $currentUserId, $id);
			$db->exec('UPDATE `friend` SET `status` = 1 WHERE `user_id`=? AND `friend_id`=?;', $id, $currentUserId);
		} else { //重复请求或已经是好友
			throw new ProException('repeat request', 301);
		}
	} else if ($my_friend_status){
		if ($my_friend_status['status'] == 1) { //已经是好友
			throw new ProException('repeat request', 302);
		} else if($my_friend_status['status'] == 4) { //请求被拒绝时，重新发情请求
			$db->exec('UPDATE `friend` SET `status` = 2 WHERE `user_id`=? AND `friend_id`=?;', $currentUserId, $id);
			$db->exec('INSERT INTO `friend` (`user_id`, `friend_id`, `status`) VALUES (?,?,3);', $id, $currentUserId);
		} else { 
			throw new ProException('unknow error', 303);
		}
	} else if($other_friend_status){
		if ($other_friend_status['status'] == 1) {//之前成为过好友，则直接成为好友
			$db->exec('INSERT INTO `friend` (`user_id`, `friend_id`, `status`) VALUES (?,?,1);', $currentUserId, $id);
		} else if($other_friend_status['status'] == 4){ //之前被我拒绝过，则直接成为好友
			$db->exec('INSERT INTO `friend` (`user_id`, `friend_id`, `status`) VALUES (?,?,1);', $currentUserId, $id);
			$db->exec('UPDATE `friend` SET `status` = 1 WHERE `user_id`=? AND `friend_id`=?;', $id, $currentUserId);
		} else { 
			throw new ProException('unknow error', 304);
		}
	} else { //发出好友请求
		$db->exec('INSERT INTO `friend` (`user_id`, `friend_id`, `status`) VALUES (?,?,2);', $currentUserId, $id);
		$db->exec('INSERT INTO `friend` (`user_id`, `friend_id`, `status`) VALUES (?,?,3);', $id, $currentUserId);
		//向融云IM server发送消息
		ServerAPI::getInstance()->messageSystemPublish('10000',array($id->val),'RC:ContactNtf',
			'{"sourceUserId":"'.$currentUserId.'","targetUserId":"'.$id.'","operation":"Request","message":"'.$message.'"}',
			'你收到一条好友邀请');
	}
}

/**
* 处理好友邀请
* @UserFunction(method = GET|POST)
* @CheckLogin
*/
function process_request_friend(Integer $id, Boolean $is_access){
	$currentUserId = getCurrentUserId();
	$db = new DataBase(DB_DNS, DB_USER, DB_PASSWORD);
	$my_friend_status = $db->fetch('SELECT `status` FROM `friend` WHERE `user_id` = ? AND `friend_id` = ?', $currentUserId, $id);
	$other_friend_status = $db->fetch('SELECT `status` FROM `friend` WHERE `user_id` = ? AND `friend_id` = ?', $id, $currentUserId);

	if ($my_friend_status && $other_friend_status && $my_friend_status['status'] == 3 && $other_friend_status['status'] == 2){
		if($is_access->val){ 
			$db->exec('UPDATE `friend` SET `status` = 1 WHERE `user_id`=? AND `friend_id`=?;', $currentUserId, $id);
			$db->exec('UPDATE `friend` SET `status` = 1 WHERE `user_id`=? AND `friend_id`=?;', $id, $currentUserId);

			$user_name_1 = $db->fetchColumn('SELECT `username` FROM `user` WHERE `id` = ?', $currentUserId);
			$user_name_2 = $db->fetchColumn('SELECT `username` FROM `user` WHERE `id` = ?', $id);

			//向融云IM server发送消息
			//$other_id = $id->val;
			ServerAPI::getInstance()->messagePublish($currentUserId, array($id->val),'RC:InfoNtf',
				json_encode(array('message'=>'你已添加了'.$user_name_1.'，现在可以开始聊天了。')));
			ServerAPI::getInstance()->messagePublish($id->val, array($currentUserId), 'RC:InfoNtf',
				json_encode(array('message'=>'你已添加了'.$user_name_2.'，现在可以开始聊天了。')));

		} else { 
			$db->exec('DELETE FROM `friend` WHERE `user_id`=? AND `friend_id`=?;', $currentUserId, $id);
			$db->exec('UPDATE `friend` SET `status` = 4 WHERE `user_id`=? AND `friend_id`=?;', $id, $currentUserId);
		}
	} else { 
		throw new ProException('unknow error', 306);
	}
}

/**
* 删除好友数据
* @UserFunction(method = GET|POST)
* @CheckLogin
*/
function delete_friend(Integer $id){
	$db = new DataBase(DB_DNS, DB_USER, DB_PASSWORD);
	$my_friend_status = $db->fetch('SELECT `status` FROM `friend` WHERE `user_id` = ? AND `friend_id` = ?', getCurrentUserId(), $id);
	$other_friend_status = $db->fetch('SELECT `status` FROM `friend` WHERE `user_id` = ? AND `friend_id` = ?', $id, getCurrentUserId());

	if ($my_friend_status && $other_friend_status){
		if($my_friend_status['status'] == 1){ //我们是好友
			$db->exec('DELETE FROM `friend` WHERE `user_id`=? AND `friend_id`=?;', getCurrentUserId(), $id);
			$db->exec('UPDATE `friend` SET `status` = 5 WHERE `user_id`=? AND `friend_id`=?;', $id, getCurrentUserId());
		} else if($my_friend_status['status'] == 2){// 删除请求
			$db->exec('DELETE FROM `friend` WHERE `user_id`=? AND `friend_id`=?;', getCurrentUserId(), $id);
			$db->exec('DELETE FROM `friend` WHERE `user_id`=? AND `friend_id`=?;', $id, getCurrentUserId());
		} else if($my_friend_status['status'] == 3){// 删除请求
			$db->exec('DELETE FROM `friend` WHERE `user_id`=? AND `friend_id`=?;', getCurrentUserId(), $id);
			$db->exec('UPDATE `friend` SET `status` = 4 WHERE `user_id`=? AND `friend_id`=?;', $id, getCurrentUserId());
		} else { 
			throw new ProException('unknow error', 305);
		}
	} else if ($my_friend_status){
		$db->exec('DELETE FROM `friend` WHERE `user_id`=? AND `friend_id`=?;', getCurrentUserId(), $id);
	} else{
		throw new ProException('unknow error', 306);
	}
}

/**
* 获取好友列表
* @UserFunction(method = GET|POST)
* @CheckLogin
*/
function get_friend(){
	$db = new DataBase(DB_DNS, DB_USER, DB_PASSWORD);
	$friends = $db->fetchAll('SELECT A.`id`,`email`,`username`,`portrait`,`status` FROM `friend` INNER JOIN `user` AS A ON `friend_id`=A.`id` WHERE `user_id`=?;'
	, getCurrentUserId());
	return $friends;
}