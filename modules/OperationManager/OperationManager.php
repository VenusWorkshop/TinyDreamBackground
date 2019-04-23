<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);

LIB('db');
LIB('co');
LIB('ds');
LIB('us');
LIB('view');
define("DAY_TIME",86400);

class OperationManager extends DBManager{
    public function info()
    {
        /*echo json_encode(self::GetUserAttendence('172840897566','2019-04-10'));
        echo self::$LastSql;*/
       // echo json_encode(self::OperationInvitedUser('186523083443','oSORf5hkHfOy3Yo4FQIPdbHKQljM','oSORf5kn6hr_H5ZSRyYSHFUzyBd4'));
        //self::GenerateInvited("");
        return "OperationManager"; // TODO: Change the autogenerated stub
    }

	public function OperationManager(){

	}


	//生成行动ID
    public static function GenerateOperationID(){
        $OPM = new OperationManager();
        //生成订单号
        do{
            $newOrderID = 100000000000+((PRC_TIME()%999999).(rand(10000,99999)));
        }while($OPM->SelectDataFromTable('tOperation',['oid'=>$newOrderID,'_logic'=>' ']));
        return $newOrderID;
    }

	//生成打卡ID
    public static function GenerateAttendenceID($opid,$timeStamp){
        $OPM = new OperationManager();
        $date = date("YmdHis",DAY_START_FLOOR($timeStamp));
        $counttoday = $OPM->CountTableRowByQuery($OPM->TName('tAttend'),self::FieldLikeValue('atid',$date.'%'));
        //生成订单号
        $AttendenceID = date("YmdHis",$timeStamp).$counttoday;
        if($OPM->SelectDataFromTable('tAttend',['atid'=>$AttendenceID,'_logic'=>' '])){
			return "";
		}
        return $AttendenceID;
    }

	//创建合约实例
	public static function CreateContractInstance($cid,$uid,$theme,$opid=""){
        $tContract = ContractManager::GetContractInfo($cid);
        if(empty($tContract)){//判断存在合约
            return [];
        }
        $OPM = new OperationManager();
        if($opid == ""){
            $opid = self::GenerateOperationID();
        }
		$timeStamp = PRC_TIME();
        $operation = [
            "opid"=>$opid,
            "uid"=>$uid,
            "cid"=>$cid,
            "starttime"=>DAY_START_CELL($timeStamp),
            "lasttime"=>0,
            "theme"=>$theme,
            "alrday"=>0,
            "conday"=>0,
            "misday"=>0,
            "menday"=>0,
            "menchance"=>0,
            "invcount"=>0,
            "state"=>"DOING",
        ];
		$OPM->InsertDataToTable($OPM->TName('tOperation'),$operation);
		return $operation;
    }

    //生成邀请记录
    public static function GenerateInvited($iuid,$tuid,$opid){
        $currentTime = PRC_TIME();
        $date = date('Y-m-d',$currentTime);
        $OPM = new OperationManager();
        $count = $OPM->CountTableRowByQuery($OPM->TName('tInvite'),$OPM->FieldIsValue('date',$date));
        $inid =  date('Ymd',$currentTime).(1000+$count);
        $inviteArray = [
            "inid"=>$inid,
            "iuid"=>$iuid,
            "tuid"=>$tuid,
            "opid"=>$opid,
            "time"=>$currentTime,
            "date"=>$date,
        ];
        $OPM->InsertDataToTable($OPM->TName('tInvite'),$inviteArray);
    }
    //创建打卡记录实例
    public static function CreateAttendenceInstance($opid,$uid,$currentTimeStamp,$dateString="",$state="NOTRELAY"){
        $OPM = new OperationManager();
        $dateString = ($dateString=="")?date("Y-m-d",$currentTimeStamp):$dateString;//时间戳时间
        $atid = self::GenerateAttendenceID($opid,$currentTimeStamp);
        //生成打卡记录数据
        $attendanceArray = [
            "atid"=>$atid,
            "opid"=>$opid,
            "uid"=>$uid,
            "time"=>$currentTimeStamp,
            "date"=>$dateString,
            "state"=>$state,
        ];
        $result = $OPM->InsertDataToTable($OPM->TName('tAttend'),$attendanceArray);
        return ['result'=>$result,'value'=>$attendanceArray];
    }

	//获取用户正在参加的行动
	public static function UserDoingOperation($uid){
        $OPM = new OperationManager();
        $targetOperation = DBResultToArray(
            $OPM->SelectDataByQuery(
                $OPM->TName('tOperation'),
                self::C_And(
                    self::FieldIsValue('uid',$uid),
                    self::FieldIsValue('state','DOING')
                )
            ),true
        );
		if(!empty($targetOperation)){
			$targetOperation = $targetOperation[0];
		}
		return $targetOperation;
    }

    //通过行动id获取行动信息
    public static function GetOperationByID($opid){
        $OPM = new OperationManager();
        $targetOperation = DBResultToArray(
            $OPM->SelectDataByQuery(
                $OPM->TName('tOperation'),
                self::FieldIsValue('opid',$opid)
            ),true
        );
        if(!empty($targetOperation)){
            $targetOperation = $targetOperation[0];
        }
        return $targetOperation;
    }

    //行动邀请用户
    public static function OperationInvitedUser($opid,$iuid,$tuid){
        $OPM = new OperationManager();
        $count = $OPM->CountTableRowByQuery($OPM->TName('tInvite'),
            self::FieldIsValue('tuid',$tuid)
        );
        //echo self::$LastSql;
        if($count>0){
            return false;
        }
        $OPM->UpdateDataByQuery($OPM->TName('tOperation'),
            self::SqlField('menchance').'='. self::SqlField('menchance').'+1,'.
            self::SqlField('invcount').'='. self::SqlField('invcount').'+1',
            self::FieldIsValue('opid',$opid)
            );
        self::GenerateInvited($iuid,$tuid,$opid);
        return true;
    }

    //通过日期获取用户打卡记录(日期格式Y-m-d)
    public static function GetUserAttendence($opid,$date){
        $OPM = new OperationManager();
        $attendance = DBResultToArray($OPM->SelectDataByQuery($OPM->TName('tAttend'),
            self::C_And(
                self::FieldIsValue('opid',$opid),
                self::FieldIsValue('date',$date)
            )
        ),true);
		if(!empty($attendance)){
			$attendance = $attendance[0];
		}
		return $attendance;
    }

    //通过打卡记录id获取打卡记录
    public static function GetAttendenceById($atid){
        $OPM = new OperationManager();
        $attendance = DBResultToArray($OPM->SelectDataByQuery($OPM->TName('tAttend'),
            self::FieldIsValue('atid',$atid)
        ),true);
        if(!empty($attendance)){
            $attendance = $attendance[0];
        }
        return $attendance;
    }

    //用户补卡条件判断
    public static function DoOperationPatchAttendence($opid){
        $OPM = new OperationManager();
        $menchanceOperation = DBResultToArray($OPM->SelectDataByQuery($OPM->TName('tOperation'),
            self::Limit(
                self::OrderBy(
                    self::C_And(
                        self::FieldIsValue('opid',$opid),
                        self::FieldIsValue('menchance',0,'>')
                    ),
                    'starttime','ASC'
                ),0,1
            )
        ),true);//查找有补卡机会的Operation
        if(empty($menchanceOperation)){
            return false;
        }
		$menchanceOperation = $menchanceOperation[0];
        $OPM->UpdateDataByQuery($OPM->TName('tOperation'),
            self::LogicString(
				[
					self::SqlField('menchance').'='.self::Symbol(self::SqlField('menchance'),'1','-'),
					self::SqlField('menday').'='.self::Symbol(self::SqlField('menday'),'1','+'),
					self::SqlField('misday').'='.self::Symbol(self::SqlField('misday'),'1','-'),
					self::SqlField('alrday').'='.self::Symbol(self::SqlField('alrday'),'1','+'),
				],','),
			self::FieldIsValue('opid',$menchanceOperation['opid']));
        return true;
    }

    //行动退款
    public static function OperationRefund($operation,$contract,$attendid){
        $OPM = new DreamServersManager();
        $tOrder = DBResultToArray(
            $OPM->SelectDataByQuery(
                $OPM->TName('tOrder'),
                self::FieldIsValue('pid',$operation['opid'])
            ),true
        );
        if(empty($tOrder)){
            return false;
        }

        $attendence = self::GetAttendenceById($attendid);

        $tOrder = $tOrder[0];
        $attendenceIndex = (DAY_START_CELL($attendence['time']) - $operation['starttime'])/86400;
        $bill = 0;
		//echo $tOrder['bill'].'<==>'.$contract['price']." ".$tOrder['bill'].'</br>';
        if($tOrder['bill'] != $contract['price'] || $tOrder['bill']<100){
            return RESPONDINSTANCE('96',":订单金额不匹配或金额过低");
        }
		//echo $attendenceIndex.'<==>'.$contract['durnation'];
        if($contract['backrule'] == 'EVERYDAY'){
			if($attendenceIndex==$contract['durnation']){
                $bill = $contract['refund'] - ($attendenceIndex-1)*100;
				//echo 'bill:'.$bill;
            }else if(DAY_START_CELL($attendence['time']) == DAY_START_CELL($operation['lasttime'])){
                $bill = 100;
            }
        }
        if($contract['backrule'] == 'END'){
            if($attendenceIndex==$contract['durnation']){
                $bill = $contract['refund'];
            }
        }
		if($bill == 0){
			return RESPONDINSTANCE('110');
		}
        return DreamServersManager::Refund($tOrder['oid'],$bill,$attendid,$attendence['date']."日行动打卡返还");
    }

	//进入行动派首页
	public function EnterOperationMainPage($uid){
		$doingOperation = self::UserDoingOperation($uid);
		if(!empty($doingOperation)){
			return RESPONDINSTANCE('82');
		}
		$orders = DreamServersManager::GetOrderLikeTypeByIndex("CO%",0,8);
		$uidList = [];
		foreach($orders as $order){
			if(!in_array($order['uid'],$uidList))
				array_push($uidList,$order['uid']);
		}
		$nicknames = UserManager::GetUserNickname(self::LogicString($uidList));
		
		foreach($orders as $key=>$order){
			$orders[$key]['nickname'] = $nicknames[$order['uid']]['nickname'];
			unset($orders[$key]['oid']);
			unset($orders[$key]['uid']);
			unset($orders[$key]['pid']);
			unset($orders[$key]['ctime']);
			unset($orders[$key]['ptime']);
			unset($orders[$key]['traid']);
			unset($orders[$key]['dcount']);
		}
        $cPersonField="COUNT(DISTINCT `uid`)";
        $cPerson = DBResultToArray($this->SelectDataByQuery($this->TName('tOperation'),1,false,$cPersonField),true);
        if(!empty($cPerson)){
            $cPerson = $cPerson[0];
        }
        $cAttendence = $this->CountTableRowByQuery($this->TName('tAttend'),1);
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['orders'] = $orders;
        $backMsg['feedback'] = SnippetManager::GetAttributeFromData('OperationData','feedback');
        $backMsg['cPerson'] = $cPerson[$cPersonField];
        $backMsg['cAttendence'] = $cAttendence;
		return $backMsg;
	}
	
    //参加合约，点击参加按钮时调用
	public function JoinContract($cid,$uid){
        $tContract = ContractManager::GetContractInfo($cid);
        if(empty($tContract)){//判断存在合约
            return RESPONDINSTANCE('81',$cid);
        }

        $userOperation = self::UserDoingOperation($uid);
        if(!empty($userOperation)){//判断无正在进行的行动
            return RESPONDINSTANCE('82');
        }

        if(!UserManager::IdentifyTeleUser($uid)){//判断用户绑定手机
            return RESPONDINSTANCE('11');
        }

        DreamServersManager::ClearSubmitOrder($uid);//清除用户未支付订单

        $orderInfo = DreamServersManager::GenerateEmptyOrder($uid,"",$tContract['cid'],$tContract['price'],3);//创建空订单

        $unifiedInfo = DreamServersManager::UnifiedOrder($orderInfo['oid'],$tContract['price'],$uid);//统一下单

        if($unifiedInfo['result'] != 0){
            return $unifiedInfo;
        }

        $backMsg = RESPONDINSTANCE('0');
        $backMsg['order'] = $orderInfo;
        $backMsg['pay'] = $unifiedInfo;
        return $backMsg;
    }

    //完成支付后成功参与合约，创建行动实例
    public function JoinContractComplete($cid,$oid,$uid,$theme){
        $opid = self::GenerateOperationID();
        //完成订单
        if(!DreamServersManager::OrderFinished($oid,['pid'=>$opid,'state'=>'SUCCESS'])){
            return RESPONDINSTANCE('20');
        }

        //创建行动实例
        $operation = self::CreateContractInstance($cid,$uid,$theme,$opid);

        if(empty($operation)){
            return RESPONDINSTANCE('83');
        }

		$backMsg = RESPONDINSTANCE('0');

        $inviteResult = [];
        if(isset($_REQUEST['icode'])){//包含邀请的行动id
            $inviteOperation = self::GetOperationByID($_REQUEST['icode']);
            if(empty($inviteOperation)){
                $inviteResult = false;
            }else{
                $inviteResult = self::OperationInvitedUser($inviteOperation['opid'],$inviteOperation['uid'],$uid);
            }
        }

		$backMsg['operation'] = $operation;
        if(!empty($inviteResult)){
            $backMsg['invite'] = $inviteResult;
        }
		return $backMsg;
    }
	
	//打开分享页面
	public function OnShareOpen($opid){
		$operation = self::GetOperationByID($opid);
		$uid = $operation['uid'];
		$contractRefund = ContractManager::GetContractInfo($operation['cid'])['refund'];
		$uinfo = $this->UserOperationInfo($uid);
		$userInfo = UserManager::GetUserInfo($uid);
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['alrday'] =  $uinfo['info']['alrday'];
		$backMsg['nickname'] = $userInfo['nickname'];
		$backMsg['headicon'] = $userInfo['headicon'];
		$backMsg['refund'] = $contractRefund;
		$backMsg['contract'] = ContractManager::MakeContractList();
		return $backMsg;
	}

    //获取行动日历
    public function OperationCalendar($uid){
        $currentOperation = self::UserDoingOperation($uid);//获取用户正在进行的行动
        if(empty($currentOperation)){//行动结束或为找到行动
            return RESPONDINSTANCE('87');
        }
        $seek = -1;
        if(isset($_REQUEST['seek'])){
            $seek = $_REQUEST['seek'];
        }
        $calendar = ContractManager::GetMonthList($currentOperation['starttime']-DAY_TIME,$currentOperation['cid'],$seek);
        $dateList = [];
        $calendarDateIndexList = [];
        foreach ($calendar['days'] as $key=>$item) {
            $calendar['days'][$key]['state'] = 'NONE';
            $calendarDateIndexList[$item['date']] = $key;
            array_push($dateList,$item['date']);
        }

        $attendenceList = DBResultToArray($this->SelectDataByQuery($this->TName('tAttend'),
            self::C_And(
                self::FieldIsValue('uid',$uid),
                self::FieldIsValue('date',self::LogicString($dateList))
            ),false,
            self::LogicString(
                [
                    self::SqlField('atid'),
                    self::SqlField('state'),
                    self::SqlField('date')
                ],
                ','
            )
        ),true);
        foreach ($attendenceList as $item) {
            if(isset($calendarDateIndexList[$item['date']])){
                $index = $calendarDateIndexList[$item['date']];
                $calendar['days'][$index]['atid'] = $item['atid'];
                $calendar['days'][$index]['state'] = $item['state'];
            }
        }
        $calendar['opid'] = $currentOperation['opid'];

        $backMsg = RESPONDINSTANCE('0');
        $calendar['days'] = (isset($_REQUEST['full']) && $_REQUEST['full']=="month")?ContractManager::FullMonthList($calendar['days']):$calendar['days'];
		foreach($calendar['days'] as $key=>$day){
			if(isset($day['id']) && $day['id']==0){
				$calendar['days'][$key]['state'] = $currentOperation['firstday'];
				//echo json_encode($day);
			}
		}
        $backMsg['calendar'] = $calendar;
        $backMsg['cid'] = $currentOperation['cid'];
        $backMsg['lastattend'] = $currentOperation['lasttime'];
        $backMsg['date'] = date('Y-m-d',PRC_TIME());
        return $backMsg;
    }

    //按日期补卡
    public function PatchAttendance($uid,$date){
        $currentTimeStamp = PRC_TIME();
        $todayStamp = DAY_START_FLOOR($currentTimeStamp);
        $currentOperation = self::UserDoingOperation($uid);//获取用户正在进行的行动
        if(empty($currentOperation)){//行动结束或为找到行动
            return RESPONDINSTANCE('87');
        }
        $startAttendanceTime = $currentOperation['starttime'];//起始日期时间戳
        $targetTime = strtotime($date);

        if($targetTime<$todayStamp && $targetTime>=$startAttendanceTime){//判断补卡时间范围
            $targetAttendence = self::GetUserAttendence($currentOperation['opid'],$date);
            $missAttendence = empty($targetAttendence);//当日漏打卡
            $missRelay = (!empty($targetAttendence) && $targetAttendence['state']=="NOTRELAY");//当日漏转发
            if($missAttendence || $missRelay){
                //在时间范围
                if(self::DoOperationPatchAttendence($currentOperation['opid'])){//有补卡次数并且成功扣除补卡次数
                    //执行补卡动作,
                    if($missAttendence){
                        //添加打卡记录
                        $supplyResult = self::CreateAttendenceInstance($currentOperation['opid'],$uid,$targetTime,$date,'SUPPLY');
                        if($supplyResult['result']){
                            $backMsg = RESPONDINSTANCE('0');
                            $backMsg['attendance'] = $supplyResult['value'];
                            return $backMsg;
                        }else{
                            return RESPONDINSTANCE('91');
                        }
                    }
                    if($missRelay){
                        //修改打卡记录状态
                        $this->UpdateDataToTableByQuery($this->TName('tAttend'),['state'=>'SUPPLY'],self::FieldIsValue('opid',$currentOperation['opid']));
                        $backMsg = RESPONDINSTANCE('0');
                        return $backMsg;
                    }
                }else{
                    return RESPONDINSTANCE('89');//补卡次数不足
                }
            }else {
                return RESPONDINSTANCE('90',$date);//当前日期无需补卡
            }
        }else{
            return RESPONDINSTANCE('88');//补卡不在时间范围
        }
    }
	
	//清理行动及打卡数据
	public function ClearAllOAInfo(){
		$operationCondition = 1;
		$inviteCondition = 1;
		$orderCondition = self::FieldLikeValue('did','co%');
		
		if(isset($_REQUEST['uid'])){
			$operationCondition = self::C_And($operationCondition,self::FieldIsValue('uid',$_REQUEST['uid']));
			$inviteCondition = self::C_OR(self::FieldIsValue('iuid',$_REQUEST['uid']),self::FieldIsValue('tuid',$_REQUEST['uid']));
			$orderCondition = self::C_And($orderCondition,self::FieldIsValue('uid',$_REQUEST['uid']));
		}
		
		$this->DeletDataByQuery($this->TName('tAttend'),$operationCondition);
		$this->DeletDataByQuery($this->TName('tOperation'),$operationCondition);
		$this->DeletDataByQuery($this->TName('tInvite'),$inviteCondition);
		$this->DeletDataByQuery($this->TName('tOrder'),$orderCondition);
		return RESPONDINSTANCE('0');
	}

    //转发成功
    public function Reply($opid,$date,$uid){
        $currentTimeStamp = PRC_TIME();//时间戳

        $currentOperation = self::UserDoingOperation($uid);//获取用户正在进行的行动
        if(empty($currentOperation)){//行动结束或为找到行动
            return RESPONDINSTANCE('87');
        }
        if($currentOperation['opid'] != $opid){//获取并验证行动数据
            return RESPONDINSTANCE('85');
        }
        $targetAttendence = self::GetUserAttendence($opid,$date);
        if(empty($targetAttendence)){
            if($currentOperation['firstday']=="NOTRELAY"){
                //更新数据
                $updateInfo = [
                    //"lasttime"=>-2,
					"firstday"=>"RELAY"
                ];
                //更新行动数据
                $this->UpdateDataToTableByQuery($this->TName('tOperation'),$updateInfo,
                    self::FieldIsValue('opid',$opid)
                );
                $firstResult = RESPONDINSTANCE('0');
                $firstResult['beforestart'] = true;
                $firstResult['firstday'] = "RELAY";
                return $firstResult;
            }
            if($currentOperation['lasttime']==-2){
                $firstResult = RESPONDINSTANCE('0');
                $firstResult['beforestart'] = true;
                $firstResult['firstday'] = "RELAY";
                return $firstResult;
            }
            return RESPONDINSTANCE('92',$date);
        }
        if($targetAttendence['state']!="NOTRELAY"){
            return RESPONDINSTANCE('84',$date);
        }
        $currentContract = ContractManager::GetContractInfo($currentOperation['cid']);//获取合约规则

        $attrule = $currentContract['attrule'];//打卡规则
        $alrday = $currentOperation['alrday'];//已经打卡天数
        $conday = $currentOperation['conday'];//连续打卡天数

        $deltaTime = $currentTimeStamp - DAY_START_FLOOR($targetAttendence['time']);

        //依据变化量判断打卡结果
        if($attrule=="RELAY") {
            if ($deltaTime <= DAY_TIME) {//在1天之内
                $alrday++;//已经打卡天数+1
                $conday++;//连续打卡天数+1
                /*依据规则退款*/
            }else{
                return RESPONDINSTANCE('93',$date);
            }
        }

        $backMsg = RESPONDINSTANCE('0');

        //更新打卡记录状态
        $this->UpdateDataToTableByQuery(
            $this->TName('tAttend'),
            ['state'=>"RELAY"],
            self::C_And(
                self::FieldIsValue('opid',$opid),
                self::FieldIsValue('date',$date)
            )
        );
        //更新打卡数据
        $updateInfo = [
            "alrday"=>$alrday,
            "conday"=>$conday
        ];
        //更新行动数据
        $this->UpdateDataToTableByQuery($this->TName('tOperation'),$updateInfo,
            self::FieldIsValue('opid',$opid)
        );

        return $backMsg;
    }

	//打卡
	public function MakeAttendance($opid,$uid){
		$currentTimeStamp = PRC_TIME()+DAY_TIME*ContractManager::Day_Offset();//时间戳
		$dateString = date("Y-m-d",$currentTimeStamp);//时间戳时间

		$currentOperation = self::UserDoingOperation($uid);//获取用户正在进行的行动
		if(empty($currentOperation)){//行动结束或为找到行动
			return RESPONDINSTANCE('87');
		}
		if($currentOperation['opid'] != $opid){//获取并验证行动数据
			return RESPONDINSTANCE('85');
		}

		$currentContract = ContractManager::GetContractInfo($currentOperation['cid']);//获取合约规则
		$backrule = $currentContract['backrule'];
		$attrule = $currentContract['attrule'];

		/*
		 * 从行动中获取数据
		 */
		$startAttendanceTime = $currentOperation['starttime'];//起始日期时间戳

		$endAttendanceTime = $startAttendanceTime+$currentContract['durnation']*DAY_TIME;//结束日期时间戳
		//echo date("Y-m-d",$startAttendanceTime).'/'.date("Y-m-d H:i:s",$endAttendanceTime);

		//最后一次打卡当天时间戳
        $nextAttendanceTime = DAY_START_CELL($currentOperation['lasttime']);//求下一次打卡日期的时间戳
		$alrday = $currentOperation['alrday'];//已经打卡天数
		$conday = $currentOperation['conday'];//连续打卡天数
		$misday = $currentOperation['misday'];//漏卡天数
		$state = $currentOperation['state'];//行动状态

		if($currentTimeStamp<$startAttendanceTime){//未到开始打卡时间
			$backMsg = RESPONDINSTANCE('86',date('Y-m-d H:i:s',$startAttendanceTime)."当前时间:".date('Y-m-d H:i:s',$currentTimeStamp));
			if($currentOperation['firstday'] == "NONE"){
				//更新数据
				$updateInfo = [
					//"lasttime"=>-1,
					"firstday"=>"NOTRELAY"
				];
				//更新行动数据
				$this->UpdateDataToTableByQuery($this->TName('tOperation'),$updateInfo,
					self::FieldIsValue('opid',$opid)
				);
				$backMsg['firstday'] = "NOTRELAY";
			}
            $backMsg['date'] = date('Y-m-d',$currentTimeStamp);
            return $backMsg;
		}

		//计算时间变化量
		if($currentOperation['lasttime']==0){//未打过卡
			$deltaTime = $currentTimeStamp - $startAttendanceTime;
		}else{//打过卡
			$deltaTime = $currentTimeStamp - $nextAttendanceTime;
		}
		if($deltaTime<0){
			//今日已打卡
			return RESPONDINSTANCE('84',$dateString);
		}
//		NORMAL类型数值直接处理
		if($attrule=="NORMAL"){
			//依据变化量判断打卡结果
			if($deltaTime <= DAY_TIME){
				//打卡成功,连续打卡+1
				$alrday++;//已经打卡天数+1
				$conday++;//连续打卡天数+1
				/*依据规则退款*/
			}else if($deltaTime>DAY_TIME){
				//打卡成功,中间有漏天
				$mis = floor($deltaTime/DAY_TIME);
				$alrday++;//已经打卡天数+1
				$conday=1;//重置连续打卡天数
				$misday=$misday+$mis;//增加漏卡天数
			}
		}else{
		    if($deltaTime>DAY_TIME){
                $mis = floor($deltaTime/DAY_TIME);
                $conday=0;//重置连续打卡天数
                $misday=$misday+$mis;//增加漏卡天数
            }
        }

		/*判断行动是否结束*/
		$nextWillAttendanceTime = DAY_START_CELL($currentTimeStamp);
		$end = [];
		if($nextWillAttendanceTime >= $endAttendanceTime){//打卡结束下一天的0点>=结束日期的0点
			$state = "SUCCESS";
			if($alrday >= $currentContract['durnation'] && $misday<=0){//连续打卡天数达到要求且无漏卡
				/*依据规则退款*/
				$state = "SUCCESS";//行动成功
			}else{
				$state = "FAILED";//行动失败
			}
            $end = $state;
		}


		//更新数据
		$updateInfo = [
			"alrday"=>$alrday,
			"conday"=>$conday,
			"misday"=>$misday,
			"lasttime"=>$currentTimeStamp,
			"state"=>$state
		];
		$currentOperation['lasttime'] = $currentTimeStamp;

		//更新行动数据
		$this->UpdateDataToTableByQuery($this->TName('tOperation'),$updateInfo,
			self::FieldIsValue('opid',$opid)
		);

        $atid = self::GenerateAttendenceID($opid,$currentTimeStamp);
		//生成打卡记录数据
		$attendanceArray = [
			"atid"=>$atid,
			"opid"=>$opid,
			"uid"=>$uid,
			"time"=>$currentTimeStamp,
			"date"=>$dateString,
			"state"=>"NOTRELAY",
		];
		$result = $this->InsertDataToTable($this->TName('tAttend'),$attendanceArray);

        $refundInfo = self::OperationRefund($currentOperation,$currentContract,$atid);


		if(!$result){//已经打卡
			return RESPONDINSTANCE('84',$dateString.",插入问题");
		}

		$backMsg = RESPONDINSTANCE('0');
		$backMsg['attendance'] = $attendanceArray;//打卡记录数据
		$backMsg['operation'] = $updateInfo;//行动更新数据
        $backMsg['refund'] = $refundInfo;
        if(!empty($end)){
            $backMsg['end'] = $end;
        }
		return $backMsg;
	}

	//行动概况
	public function OperationInfo($opid){

        $operation = self::GetOperationByID($opid);
        if(empty($operation)){
            return RESPONDINSTANCE('94');
        }
        $contract = ContractManager::GetContractInfo($operation['cid']);
        $durnation = $contract['durnation'];
        $user = UserManager::GetUserInfo($operation['uid']);
        $info = [
            "desday" => $durnation-$operation['alrday'],//距离目标天数
            "conday" => $operation['conday'],//连续打卡天数
            "alrday" => $operation['alrday'],//已经打卡天数
            "misday" => $operation['misday'],//缺卡天数
            "menday" => $operation['menday'],//补卡天数
            "precentage" => round($operation['alrday']/$durnation,2),//进度
			"theme"=>$operation['theme'],
            'nickname'=>$user['nickname']
        ];
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['info'] = $info;
        return $backMsg;
    }

    //获得用户所有行动列表
    public function OperationList($uid,$seek,$count){
        $backMsg = RESPONDINSTANCE('0');
        $CountOperations = $this->CountTableRowByQuery($this->TName('tOperation'),
            self::FieldIsValue('uid',$uid));
        $operationList = DBResultToArray($this->SelectDataByQuery(
            $this->TName('tOperation'),
            self::Limit(
                self::OrderBy(
                    self::FieldIsValue('uid',$uid)
                    ,'starttime','DESC'
                ),
                $seek,
                $count
            )
        ),true);
        $backMsg['count'] = $CountOperations;
        $backMsg['operations'] = $operationList;
        return $backMsg;
    }

    //用户行动信息
    public function UserOperationInfo($uid){
        $backMsg = RESPONDINSTANCE('0');
        $alrday = 0;
        $totaloperation = 0;
        $sumField = 'SUM('.self::SqlField('alrday').')';
        $menchanceField = 'SUM('.self::SqlField('menchance').')';
        $invcountField = 'SUM('.self::SqlField('invcount').')';
        $countField ='COUNT(*)';
        $info = DBResultToArray($this->SelectDataByQuery(
            $this->TName('tOperation'),
            self::FieldIsValue('uid',$uid),
            false,
            self::LogicString([$sumField,$countField,$menchanceField,$invcountField],',')
        ),true);
        if(!empty($info)){
            $info = $info[0];
            $alrday = $info[$sumField];
            $totaloperation = $info[$countField];
            $menchance = $info[$menchanceField];
            $invcount = $info[$invcountField];
			$backMsg['info'] = [
				'alrday'=>$alrday,
				'totaloperation'=>$totaloperation,
				'menchance'=>$menchance,
				'invcount'=>$invcount
			];
        }
        return $backMsg;
    }

    //获得被用户邀请的全部邀请者的头像
    public function UserInvitedUserHeadicons($uid){
        $invites = DBResultToArray($this->SelectDataByQuery($this->TName('tInvite'),self::FieldIsValue('iuid',$uid)),true);
        $uids = [];
        foreach ($invites as $item) {
            array_push($uids,$item['tuid']);
        }
        $headicons = DBResultToArray($this->SelectDataByQuery(
            $this->TName('tUser'),
            self::FieldIsValue(
                'uid',
                self::LogicString($uids)
            ),
            false,
            self::LogicString(
                [
                    self::SqlField('headicon')
                ],","
            )
        ),true);
        $resultArray = [];
        foreach ($headicons as $head) {
            array_push($resultArray,$head['headicon']);
        }
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['headicons'] = $resultArray;
        return $backMsg;
    }
}
?>