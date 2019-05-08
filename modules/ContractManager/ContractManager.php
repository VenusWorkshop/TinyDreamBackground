<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);

LIB('db');
LIB('view');

class ContractManager extends DBManager{
    public function info()
    {
        echo json_encode(self::GetMonthList(PRC_TIME(),'CO0000000001'));
        return "ContractManager"; // TODO: Change the autogenerated stub
    }

	public function ContractManager(){
		
	}


	static function RemoveSimlarDay($dayArray){
        $indexArray = [];
        $removeArray = [];
        foreach($dayArray as $key=>$value){
            if(!isset($indexArray[$value['date']])) {
                $indexArray[$value['date']] = $key;
            }else{
                array_push($removeArray,$key);
            }
        }
        foreach ($removeArray as $index) {
            unset($dayArray[$index]);
        }
        return $dayArray;
    }

	//将月份信息首尾补充完整,参数需要接收GetMonthList处理过的对象
	public static function FullMonthList($days){
        //return $days;
        //echo json_encode($days);
        $weekarray=["日","一","二","三","四","五","六"];

        $optionDateStamp = $days[0]['dateStamp'];
        $beginDate=date('Y-m-01', $optionDateStamp);
        $seekDay = $beginDate;
        $forwardDays = [];
        $addedFirst = ($seekDay != $days[0]['date']);
        while ($seekDay != $days[0]['date']){
            //echo $seekDay.'<-->'.$days[0]['date'];
            $dayTimeStamp = strtotime($seekDay);
            $year = date('Y',$dayTimeStamp);
            $month = date('m',$dayTimeStamp);
            $day = date('d',$dayTimeStamp);
            $weekSeek = date('w',$dayTimeStamp);
            array_push($forwardDays,
                [
                    "dateStamp"=>$dayTimeStamp,
                    "date"=>$seekDay,
                    "weekDay"=>$weekarray[$weekSeek],
                    "Year"=>$year,
                    "Month"=>$month,
                    "Day"=>$day
                ]
            );
            $seekDay = date('Y-m-d',strtotime("$seekDay +1 day"));
        }


        if(empty($forwardDays) && $addedFirst){
            $forwardDays = [
                [
                    "dateStamp"=>$days[0]['dateStamp'],
                    "date"=>$days[0]['date'],
                    "weekDay"=>$days[0]['weekDay'],
                    "Year"=>$days[0]['Year'],
                    "Month"=>$days[0]['Month'],
                    "Day"=>$days[0]['Day']
                ]
            ];
        }

        if(empty($forwardDays) && !$addedFirst && $days[0]['id']==1){

            $dayTimeStamp = $days[0]['dateStamp']-86400;
            $year = date('Y',$dayTimeStamp);
            $month = date('m',$dayTimeStamp);
            $day = date('d',$dayTimeStamp);
            $weekSeek = date('w',$dayTimeStamp);
            $date = date('Y-m-d', $dayTimeStamp);

            $forwardDays = [
                [
                    "dateStamp"=>$dayTimeStamp,
                    "date"=>$date,
                    "weekDay"=>$weekarray[$weekSeek],
                    "Year"=>$year,
                    "Month"=>$month,
                    "Day"=>$day
                ]
            ];
        }




       /* $daySeek = $forwardDays[0];

        $weekSeek = date('w',$forwardDays[0]['dateStamp']);
        $weekForwardArray = [];
        for($i=($weekSeek-1);$i>=0;$i--){
            $daySeek['dateStamp'] = $daySeek['dateStamp']-86400;
            $daySeek['date'] = date('Y-m-d',$daySeek['dateStamp']);
            $daySeek['weekDay'] = $weekarray[date('w',$daySeek['dateStamp'])];
            $daySeek['Year'] = date('Y',$daySeek['dateStamp']);
            $daySeek['Month'] = date('m',$daySeek['dateStamp']);
            $daySeek['Day'] = date('d',$daySeek['dateStamp']);
            array_push($weekForwardArray,$daySeek);
        }
        $weekForwardArray = array_reverse($weekForwardArray);*/


            //$forwardDays = array_merge($weekForwardArray,$forwardDays);
        //$forwardDays[count($forwardDays)-1]['id']=0;
        if(count($forwardDays)>0) {
            $forwardDays[count($forwardDays) - 1]['id'] = '0';
        }
        //echo json_encode($forwardDays[count($forwardDays)-1]).'</br>';
        $days = array_merge($forwardDays,$days);


        $endSeek = count($days)-1;

        $optionDateStamp = $days[count($days)-1]['dateStamp'];
        $endDateFirst=date('Y-m-01', $optionDateStamp);
        $endDate = date('Y-m-d', strtotime("$endDateFirst +1 month -1 day"));
        $seekDay = $endDate;
        $backwardDays = [];
        while ($seekDay != $days[$endSeek]['date']){
            $dayTimeStamp = strtotime($seekDay);
            $year = date('Y',$dayTimeStamp);
            $month = date('m',$dayTimeStamp);
            $day = date('d',$dayTimeStamp);
            $weekSeek = date('w',$dayTimeStamp);
            array_push($backwardDays,
                [
                    "dateStamp"=>$dayTimeStamp,
                    "date"=>$seekDay,
                    "weekDay"=>$weekarray[$weekSeek],
                    "Year"=>$year,
                    "Month"=>$month,
                    "Day"=>$day
                ]
            );
            $seekDay = date('Y-m-d',strtotime("$seekDay -1 day"));
        }
        if(empty($backwardDays)){
            $bseek =count($days)-1;
            $backwardDays = [
                [
                    "dateStamp"=>$days[$bseek]['dateStamp'],
                    "date"=>$days[$bseek]['date'],
                    "weekDay"=>$days[$bseek]['weekDay'],
                    "Year"=>$days[$bseek]['Year'],
                    "Month"=>$days[$bseek]['Month'],
                    "Day"=>$days[$bseek]['Day']
                ]
            ];
        }
            $daySeek = $backwardDays[count($backwardDays)-1];

            $weekSeek = date('w',$backwardDays[count($backwardDays)-1]['dateStamp']);
            $weekBackwardArray = [];
            for($i=($weekSeek+1);$i<=6;$i++){
                $daySeek['dateStamp'] = $daySeek['dateStamp']+86400;
                $daySeek['date'] = date('Y-m-d',$daySeek['dateStamp']);
                $daySeek['weekDay'] = $weekarray[date('w',$daySeek['dateStamp'])];
                $daySeek['Year'] = date('Y',$daySeek['dateStamp']);
                $daySeek['Month'] = date('m',$daySeek['dateStamp']);
                $daySeek['Day'] = date('d',$daySeek['dateStamp']);
                array_push($weekBackwardArray,$daySeek);
            }
            //$backwardDays = array_merge($backwardDays,$weekBackwardArray);
        $backwardDays = array_reverse($backwardDays);

        $days = array_merge($days,$backwardDays);
        $days = self::RemoveSimlarDay($days);
        return $days;
    }
	
	public static function Day_Offset(){
		return isset($_REQUEST['dfs'])?$_REQUEST['dfs']:0;
	}
	//计算日历(购买时间,合约ID)
	public static function GetMonthList($buyTime,$cid,$needIndex=-1){
		$currentTimeStamp = PRC_TIME()+86400*self::Day_Offset();
        $showtime=date("Y-m-d H:i:s", DAY_START_CELL($buyTime));
        $contract = self::GetContractInfo($cid);
        $startDayStamp = DAY_START_CELL($buyTime);
        $day = [];
        $year = "";
        $month = "";
        $monthCount = 0;
        $monthIndex = [];
        $totalCount = 0;
		$weekarray=["日","一","二","三","四","五","六"]; 

        for($i = 0;$i<$contract['durnation'];$i++){
            $currentDayValue = $startDayStamp+86400*$i;
            $todayYear = date("Y",$currentDayValue);
            $todayMonth = date("m",$currentDayValue);

            $currentIndex = $year.$month;
            $todayIndex = $todayYear.$todayMonth;

            if($todayIndex != $currentIndex){
                $month = $todayMonth;
                $year = $todayYear;
                $monthCount++;
                if(!in_array($todayIndex,$monthIndex)){
                    array_push($monthIndex,$todayIndex);
                }
            }

            if(!isset($day[$todayYear.$todayMonth])){
                $day[$todayYear.$todayMonth] = [];
            }

            $totalCount++;
			
			$dayObject = [
                'id'=>$i+1,
                'dateStamp' =>$currentDayValue,
                'date' => date("Y-m-d",$currentDayValue),
                'weekDay' => $weekarray[date("w",$currentDayValue)],
                'Year' => $todayYear,
                'Month' => $todayMonth,
                'Day' => date("d",$currentDayValue),
                'needReply'=> ($contract['attrule']=="RELAY")
            ];
			
			
			if(DAY_START_FLOOR($currentTimeStamp)==$currentDayValue){
				$dayObject['today'] = true;
			}
			
            array_push($day[$todayYear.$todayMonth],$dayObject);

        }

        $monthCount = count($monthIndex);
        $month = date("Ym",PRC_TIME());

        if($needIndex==-1) {
            $needIndex = array_search($month, $monthIndex);
        }

        if($needIndex > ($monthCount-1)){
            $needIndex = ($monthCount-1);
        }
        $currentMonth = $needIndex==-1?"all":$monthIndex[$needIndex];


        if($needIndex==-1){
            $dayValue = [];
            foreach ($day as $key=>$value) {
                $dayValue = array_merge($dayValue,$value);
            }
        }else{
            $dayValue = $day[$currentMonth];
        }

        return [
            'realMonth'=>$month,
            'monthIndex'=>$monthIndex,
            'monthCount'=>$monthCount,
            'currentIndex'=>($needIndex==-1?"all":$needIndex),
            'currentMonth'=>$currentMonth,
            'totalDay'=>$totalCount,
            'currentDayCount'=>count($dayValue),
            'days'=>$dayValue
        ];
    }

	//获取合约主题列表
	public static function ThemeList(){
        return SnippetManager::GetAttributeFromData('OperationData','theme');
    }

    //通过合约id获取合约信息
	public static function GetContractInfo($cid){
        $COM = new ContractManager();
        return $COM->ContractInfo($cid)['contract'];
    }

	//获取合约类型表（信息）
	public static function MakeContractList($NumKey = true){
        $backMsg = RESPONDINSTANCE('0');
		$CM = new ContractManager();
        $result = DBResultToArray(
            $CM->SelectDataByQuery($CM->TName('tContract'),"1"),
            $NumKey
        );
        return $result;
    }

    //设置合约信息
    public function SetContract($cid){
        //$cid = isset($_REQUEST['cid'])?$_REQUEST['cid']:"";
        $paras = $_REQUEST;
        unset($paras['cid']);
        $fields = $this->GetTableFields($this->TName('tContract'));
        array_shift($fields);
        $backMsg = RESPONDINSTANCE('0');
        $updateArray = [];
        foreach ($paras as $key => $value) {
            if(in_array($key,$fields)){
                $updateArray[$key] = $value;
            }
        }

        if(empty($cid) || empty($paras) || empty($updateArray)){
            $backMsg['fields'] =$fields;
            return $backMsg;
        }

        if(!empty($updateArray))
            $this->UpdateDataToTableByQuery($this->TName('tContract'),$updateArray,self::FieldIsValue('cid',$cid));
        $backMsg['updated'] = $updateArray;
        return $backMsg;
    }

	//获取合约类型表（信息）
	public function ContractList(){
        $backMsg = RESPONDINSTANCE('0');
        $result = DBResultToArray(
            $this->SelectDataByQuery($this->TName('tContract'),"1"),
            true
        );
        $backMsg['contracts'] = $result;
        $backMsg['themes'] = self::ThemeList();
        $backMsg['cattention'] = SnippetManager::GetAttributeFromData('OperationData','cattention');
        return $backMsg;
    }

    //通过合约id获取合约信息
    public function ContractInfo($cid){
        $backMsg = RESPONDINSTANCE('0');
        $result = DBResultToArray(
            $this->SelectDataByQuery($this->TName('tContract'),self::FieldIsValue('cid',$cid)),
            true
        );
        if(!empty($result)){
            $result = $result[0];
        }
        $backMsg['contract'] = $result;
        return $backMsg;
    }
}
?>