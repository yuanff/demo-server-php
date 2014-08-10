<?php
include('DataBase.php'); //数据操作类
include('BaseType.php'); //基础数据类型
include('ProException.php'); //执行异常
include('Config.php'); //基本配置文件
include('Process.php'); //逻辑处理文件, 注：业务流程处理示例参看此文件


//定义允许执行的function，key为方法名，value为Request method(GET/POST)
$allow_func  = array("login"=>"GET|POST", "reg"=>"GET|POST", "token"=>"GET|POST", "profile"=>"GET|POST", "friends"=>"GET|POST");

//获取function名称
if (preg_match("/\w+(?:$|(?=\?))/", $_SERVER["REQUEST_URI"], $matches)) {
	$func = $matches[0];
}

//查找并执行function
if (isset($func) && isset($allow_func) && array_key_exists($func, $allow_func) && stripos($allow_func[$func], $_SERVER['REQUEST_METHOD'])!== false && function_exists($func)){
	$func_ref = new ReflectionFunction($func);
	$params = array(); 
	//参数填充
	foreach ($func_ref->getParameters() as $param) {
		$param_key = $param->getName();
		if (isset($_REQUEST[$param_key])) {
			if ($param->getClass() != null) {
				$param_class_name = $param->getClass()->getName();
				try {
					$param_class = new $param_class_name($_REQUEST[$param_key]);
				} catch(Exception $e) {
					header("status: 403 Forbidden");
					die("Parameter '$param_key' is error, message is '".$e->getMessage()."'.");
				}
				array_push($params, $param_class);
			} else {
				array_push($params, $_REQUEST[$param_key]);
			}
		} else {
			if ($param->isDefaultValueAvailable()) {
				array_push($params, $param->getDefaultValue());
			} else { 
				header("status: 403 Forbidden");
				die("Missing $param_key parameter.");
			}
		}
	}
	//执行function
	try {
		$result = call_user_func_array($func, $params);
	} 
	catch (ProException $pe) {
		die(json_encode(array("code" => $pe->getCode(), "message" => $pe->getMessage())));
	}
	catch (Exception $e) {
		header("status: 500 Error");
		die($e->getMessage());
	}
	if (isset($result)) {
		echo json_encode(array("code" => 200,"result" => $result));
	} else {
		echo json_encode(array("code" => 200));
	}
} else {
	header("status: 404 Not Found");
	die("Missing $func Function.");
}