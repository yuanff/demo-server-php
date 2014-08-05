<?php
abstract class BaseType{
	public function __construct($val){
		$this->val=$val;
	}
	public $val;
	public function __toString(){
		return strval($this->val);
	}
	public function __invoke(){
		return $this->val;
	}
}

final class Integer extends BaseType{
	public function __construct($val){
		if(preg_match("/^\d+$/", $val)) {
			$val = (int)$val;
			parent::__construct($val);
		} else
			throw new Exception("this value is not Integer");
	}
}

final class Email extends BaseType{
	public function __construct($val){
		$val = trim($val);
		if(preg_match("/^[\w.-]+@[\w.-]+$/", $val)) {
			parent::__construct($val);
		} else
			throw new Exception("this value is not Email");
	}
}