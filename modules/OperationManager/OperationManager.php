<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);

LIB('db');
LIB('co');
LIB('ds');
LIB('us');

class OperationManager extends DBManager{
    public function info()
    {
        return "OperationManager"; // TODO: Change the autogenerated stub
    }

	public function OperationManager(){
		
	}

    public static function GenerateOperationID(){
        $OPM = new OperationManager();
        //生成订单号
        do{
            $newOrderID = 100000000000+((PRC_TIME()%999999).(rand(10000,99999)));
        }while($OPM->SelectDataFromTable('tOperation',['oid'=>$newOrderID,'_logic'=>' ']));
        return $newOrderID;
    }
	
	//生成打卡ID
    public static function GenerateAttendenceID($opid){
        $OPM = new OperationManager();
        //生成订单号
        $AttendenceID = (999999+($opid%1000000))."-".date("Ymd",DAY_START_FLOOR(PRC_TIME()));
        if($OPM->SelectDataFromTable('tAttend',['atid'=>$AttendenceID,'_logic'=>' '])){
			return "";
		}
        return $AttendenceID;
    }

	//创建合约实例
	public static function CreateContractInstance($cid,$uid,$theme){
        $tContract = ContractManager::GetContractInfo($cid);
        if(empty($tContract)){//判断存在合约
            return [];
        }
        $OPM = new OperationManager();
		
		$timeStamp = PRC_TIME();
        $operation = [
            "opid"=>self::GenerateOperationID(),
            "uid"=>$uid,
            "cid"=>$cid,
            "starttime"=>DAY_START_CELL($timeStamp),
            "lasttime"=>$timeStamp,
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

        $orderInfo = DreamServersManager::GenerateEmptyOrder($uid,"",$tContract['cid'],3);//创建空订单

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
        //完成订单
        if(!DreamServersManager::OrderFinished($oid,['state'=>'SUCCESS'])){
            return RESPONDINSTANCE('20');
        }

        //创建行动实例
        $operation = self::CreateContractInstance($cid,$uid,$theme);

        if(empty($operation)){
            return RESPONDINSTANCE('83');
        }
		
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['operation'] = $operation;
		return $backMsg;
    }
	
	//打卡
	public function MakeAttendance($opid,$uid){
		$currentTimeStamp = PRC_TIME();
		$dateString = date("Y-m-d",$currentTimeStamp);
		
		$currentOperation = self::UserDoingOperation($uid);

		/*
		 *
		 * 从行动中获取数据
		 *
		 */
		$startAttendanceTime = $currentOperation['starttime'];//起始日期时间戳
		//最后一次打卡当天时间戳
        $lastAttendanceTime = DAY_START_FLOOR($currentOperation['lasttime']);//求上次打卡日期的时间戳
		$alrday = $currentOperation['alrday'];//已经打卡天数
		$conday = $currentOperation['conday'];//连续打卡天数
		$misday = $currentOperation['misday'];//漏卡天数

        $currentTimeStamp = $currentTimeStamp+86400*0;

		$delta = ($currentTimeStamp - DAY_START_FLOOR($lastAttendanceTime));//当前时间和上次打卡日期时间戳做差值
		if($delta<86400){//小于零是因为未过第一天
            return RESPONDINSTANCE('86',date('Y-m-d H:i:s',$startAttendanceTime));
        }
		if($delta<=86400*2){//判断条件
			$conday++;
		}

		if($delta>86400*2){
            $misday += floor(($delta-86400)/86400);//计算漏卡天数
            $conday = 1;
        }

		$alrday++;

		echo "currentTimeStamp:".$currentTimeStamp.' delta:'.$delta .',alrday:'.$alrday.',conday:'.$conday.',misday:'.$misday;

		return;
		if($currentOperation['opid'] != $opid){//获取并验证行动数据
			return RESPONDINSTANCE('85');
		}
		
		$attendanceArray = [
			"atid"=>self::GenerateAttendenceID($opid),
			"opid"=>$opid,
			"uid"=>$uid,
			"time"=>$currentTimeStamp,
			"date"=>$dateString,
			"state"=>"NOTRELAY",
		];
		$result = $this->InsertDataToTable($this->TName('tAttend'),$attendanceArray);
		if(!$result){//已经打卡
			return RESPONDINSTANCE('84',$dateString);
		}
		
/*      `opid` TEXT NOT NULL COMMENT '行动id' ,
        `uid` TEXT NOT NULL COMMENT '用户id' ,
        `cid` TEXT NOT NULL COMMENT '合约id' ,
        `starttime` INT NOT NULL COMMENT '开始时间' ,
        `lasttime` INT NOT NULL COMMENT '上次打卡时间' ,
        `theme` TEXT NOT NULL COMMENT '主题字符串' ,
        `alrday` INT NOT NULL COMMENT '已经打卡天数' ,
        `conday` INT NOT NULL COMMENT '连续打卡天数' ,
        `misday` INT NOT NULL COMMENT '漏卡天数' ,
        `menday` INT NOT NULL COMMENT '补卡天数' ,
        `menchance` INT NOT NULL COMMENT '补卡机会' ,
        `invcount` INT NOT NULL COMMENT '邀请人数' ,
        `state` ENUM('DOING','SUCCESS','FAILED') NOT NULL COMMENT '行动状态(进行，完成，失败)' */
		//
		//已经打卡、漏卡、连续打卡
		
		
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['attendance'] = $attendanceArray;
		return $backMsg;
	}
}
?>