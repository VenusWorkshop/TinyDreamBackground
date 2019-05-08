<?php
//引用此页面前需先引用conf.php
LIB('db');
error_reporting(E_ALL ^ E_DEPRECATED);

class Table{
	public $fieldsArray = [];
	public $datas = [];
	public $indexObject = [];
	public $seek = 0;
	public $total = 1;
	public $size = 1;
	
	public function LoadField($fields){
		foreach($fields as $key){
			array_push($this->fieldsArray,$key);
		}
		return $this;
	}
	
	public function LoadDatas($tData,$seek,$count,$total){
		$this->$datas = $tData;
		$this->$seek = $seek;
		$this->$size = $count;
		$this->$total = $total;
		return $this;
	}
	
	public function DataHandle($func){
		if(empty($this->$datas)){
			return $this;
		}
		foreach($this->$datas as $key=>$value){
			$this->$datas[$key] = $func($key,$value);
		}
		return $this;
	}
	
	public function DataFinished(){
		$this->$indexObject = UtilsManager::BuildPageIndex($this->$seek,$this->$count,$this->$size);
		return $this;
	}
	
	public function ToRespond(){
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['data'] = $this->$datas;
		$backMsg['index'] = $this->$indexObject;
		$backMsg['fields'] = $this->$fieldsArray;
		$backMsg['count'] = $this->$total;
		return $backMsg;
	}
	
	public function Table(){
		
	}
}
class UtilsManager extends DBManager{
    public function info()
    {
        return "UtilsManager"; // TODO: Change the autogenerated stub
    }

	public function UtilsManager(){
		
	}
	
	//创建目录导航
	public static function BuildPageIndex($seek,$count,$size,$HalfPageMax = 3){
		$pageIndex = [];
		$currentPage = Ceil($seek/$size);
		
		
		$totalPage = Ceil($count/$size);
		
		$startIndex = ($currentPage - $HalfPageMax)<0?0: ($currentPage - $HalfPageMax);
		$endIndex = ($currentPage + $HalfPageMax)>$totalPage?$totalPage: ($currentPage + $HalfPageMax);
		
		$pageIndex['allowLast'] = $startIndex>0;
		$pageIndex['allowNext'] = $endIndex<$totalPage;
		
		for($i=$startIndex;$i<$endIndex;$i++){
			$pageIndex['list'][$i] = $i*$size;
		}
		$pageIndex['current'] = $currentPage;
		$pageIndex['count'] = $count;
		$pageIndex['size'] = $size;
		return $pageIndex;
	}
	
	public static function CreateTable(){
		return new Table();
	}
	
	public function TryTable(){
		return self::CreateTable();
	}
}
?>