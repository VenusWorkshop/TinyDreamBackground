<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);

LIB('all');

class TestManager extends DBManager {
    public function info()
    {
        return "TestManager"; // TODO: Change the autogenerated stub
    }

	public function TestManager(){
		
	}

	public static function GenerateTestUserID(){
        return rand(10000000,99999999);
    }

    public static function GenerateTestUserNickName(){
        return "用户".rand(1000,9999);
    }

    public static function GenerateTestUserHeadIconUrl(){
        return "http://cloud.antit.top".sha1(rand(1000,9999));
    }

    public static function GenerateTestTeleNumber(){
        return '1'.array_rand(['3','5','7','8'],1).rand(10000,99999).rand(1000,9999);
    }

    //测试梦想池
    public function PoolTest(){


        $this->CreateDreamPool(10);

        $this->CreateUserAndDream(25);

    }

    public function RandomBuyPool(){
        $DSM = new DreamServersManager();

        $BackMsg = [];

        //随机用户
        $users = $DSM->SelectDataFromTable($DSM->TName('tUser'),['identity'=>'USER'],false,'uid');
        $result = DBResultToArray($users,true);
        $uid = $result[rand(0,count($result)-1)]['uid'];

        //随机梦想池
        $pool = $DSM->SelectDataFromTable($DSM->TName('tPool'),['state'=>'RUNNING'],false,'pid,ubill');
        $presult = DBResultToArray($pool,true);

        if(empty($presult)){//没有正在运行的梦想池
            return RESPONDINSTANCE('5');
        }

        $poolIndex = rand(0,count($presult)-1);
        $pid = $presult[$poolIndex]['pid'];
        $ubill = $presult[$poolIndex]['ubill'];

        //开始下单
        $OrderStart = $DSM->PlaceOrderInADreamPoolStart($uid,$pid);
        $DRM = new DreamManager();

        if($OrderStart['code']!='0'){
           return $OrderStart;
        }

        $backMsg['OrderStart'] = $OrderStart;


        $dream = $DSM->SelectDataFromTable($DSM->TName('tDream'),['state'=>'SUBMIT','uid'=>$uid,'_logic'=>'AND'],false,'did');
        $dresult = DBResultToArray($dream,true);
        $did = $dresult[rand(0,count($dresult)-1)]['did'];

        //梦想选择
        $DreamSelected = $DRM->OnDreamSelected($uid,$did,json_encode($OrderStart['actions']));

        if($DreamSelected['code']!='0'){
            return $DreamSelected;
        }
        $backMsg['DreamSelected'] = $DreamSelected;
        //订单创建
        $OrderCreated = $DSM->PlaceOrderInADreamPoolCreate(json_encode($DreamSelected['actions']));

        if($OrderCreated['code']!='0'){
            return $OrderCreated;
        }
        $backMsg['OrderCreated'] = $OrderCreated;

        $buyCount = rand(1,$DreamSelected['actions']['buy']['dayLim']);
        $bill = $buyCount*$ubill;

        //订单支付
        $OrderPaied = $DSM->PlaceOrderInADreamPoolPay($uid,$OrderCreated['actions']['pay']['oid'],$bill,$buyCount,json_encode($OrderCreated['actions']));

        if($OrderPaied['code']!='0'){
            return $OrderPaied;
        }

        $backMsg['OrderPaied'] = $OrderPaied;

        return $backMsg;
    }

    //创建虚拟用户并生成手机号及梦想
    public function CreateUserAndDream($count){
        $USM = new UserManager();
        $VAM = new ValidateManager();

        //创建10个测试用户
        for($i=0;$i<$count;$i++) {
            $result['cuser'][$i]=$USM->EnterApp(self::GenerateTestUserID(), self::GenerateTestUserNickName(), self::GenerateTestUserHeadIconUrl());//创建用户
            $uid = $result['cuser'][$i]['selfinfo']['uid'];
            //echo json_encode($result['cuser'][$i]);
            $VAM->ForceBindTele($result['cuser'][$i]['selfinfo']['uid'],$this->GenerateTestTeleNumber());//绑定手机号
            $this->CreateDream($uid,rand(1,5));//生成梦想
        }
    }

    public function CreateDreamPool($count){
        $DPM = new DreamPoolManager();
        for($i=0;$i<$count;$i++) {
            $DPM->Add("反人累梦想池".rand(1000,9999),"a01",rand(1,30)*1000000,1000,86400*rand(2,5));
        }
    }

    public function CreateDream($uid,$count){
        $DRM = new DreamManager();
        for($i=0;$i<$count;$i++) {
            $DRM->OnEditDream($uid, $uid . "的梦想", "我的梦想是挣" . $uid . "块钱");
        }
    }
	
	//检查订单中梦想编号未定义
	public function FixOrderDreamUndefine(){
		$fixResult = DBResultToArray($this->SelectDataFromTable($this->TName('tOrder'),
		[
			'did'=>'undefined'
		]));
		
		foreach($fixResult as $key=>$value){
			$tUid = $value['uid'];
			$tdreams = $this->SelectDataFromTable($this->TName('tDream'),['uid'=>$tUid]);
			$dresult = DBResultToArray($tdreams,true);
			$did = $dresult[rand(0,count($dresult)-1)]['did'];//随机梦想id
			$this->UpdateDataToTable($this->TName('tOrder'),['did'=>$did],['oid'=>$value['oid'],'did'=>'undefined','_logic'=>'AND']);
			echo "修复订单:".$value['oid'].',梦想变为:'.$did.'</br>';
		}
	}
	
	//修复编号梦想undefined问题
	public function FixLottery(){
		$LotteryResult = DBResultToArray($this->SelectDataFromTable($this->TName('tLottery'),['did'=>'undefined']));
		
		foreach($LotteryResult as $key=>$value){
			$tOid = $value['oid'];
			$tOrder = $this->SelectDataFromTable($this->TName('tOrder'),['oid'=>$tOid]);
			$tOrder = DBResultToArray($tOrder,true)[0];
			
			$this->UpdateDataToTable($this->TName('tLottery'),['did'=>$tOrder['did']],['lid'=>$value['lid'],'did'=>'undefined','_logic'=>'AND']);
			echo "修复编号:".$value['lid'].',梦想变为:'.$tOrder['did'].'</br>';
		}
	}
	
	public function RebuildDreamState(){
		$awards = $this->SelectDataByQuery($this->TName('tAward'),'1',false,'pid,did');
		$awardList = DBResultToArray($awards,true);
		$condition = "";
		$conditionReject = "";
		foreach($awardList as $key=>$value){
			if($condition == ""){
				$condition = $condition.self::FieldIsValue('did',$value['did']);
			}else{
				$condition = self::C_Or($condition,self::FieldIsValue('did',$value['did']));
			}
			
			if($conditionReject == ""){
				$conditionReject = self::FieldIsValue('did',$value['did'],'!=');
			}else{
				$conditionReject = self::C_And($conditionReject,self::FieldIsValue('did',$value['did'],'!='));
			}
			
			//if($conditionReject == ""){
//				$conditionReject = $conditionReject.self::C_And(self::FieldIsValue('did',$value['did'],'!='),self::FieldIsValue('state','SUBMIT','!='));
			//}else{
//				$conditionReject = self::C_Or($conditionReject,self::C_And(self::FieldIsValue('did',$value['did'],'!='),self::FieldIsValue('state','SUBMIT','!=')));
			//}
		}
		$conditionReject = self::C_And($conditionReject,self::FieldIsValue('state','SUBMIT','!='));
		
		
		$dream = $this->SelectDataByQuery($this->TName('tDream'),$condition,false,'did,state');
		$dreamList = DBResultToArray($dream,true);
		$dreamReject = $this->SelectDataByQuery($this->TName('tDream'),$conditionReject,false,'did,state');
		$dreamRejectList = DBResultToArray($dreamReject,true);
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['award'] = $awardList;
		$backMsg['dList'] = $dreamList;
		$backMsg['dListReject'] = $dreamRejectList;
		$this->UpdateDataToTableByQuery($this->TName('tDream'),['state'=>'SUBMIT'],$conditionReject);
		//$backMsg['lListReject'] = $lotteryRejectList;
		return $backMsg;
	}
	
	public function RebuildLotteryState(){
		$awards = $this->SelectDataByQuery($this->TName('tAward'),'1',false,'pid,lid');
		$awardList = DBResultToArray($awards,true);
		$condition = "";
		$conditionReject = "";
		foreach($awardList as $key=>$value){
			if($condition == ""){
				$condition = $condition.self::C_And(self::FieldIsValue('pid',$value['pid']),self::FieldIsValue('lid',$value['lid']));
			}else{
				$condition = self::C_Or($condition,self::C_And(self::FieldIsValue('pid',$value['pid']),self::FieldIsValue('lid',$value['lid'])));
			}
			if($conditionReject == ""){
				$conditionReject = $conditionReject.self::C_And(self::C_And(self::FieldIsValue('pid',$value['pid']),self::FieldIsValue('lid',$value['lid'],'!=')),self::FieldIsValue('state','GET'));
			}else{
				$conditionReject = self::C_Or($conditionReject,self::C_And(self::C_And(self::FieldIsValue('pid',$value['pid']),self::FieldIsValue('lid',$value['lid'],'!=')),self::FieldIsValue('state','GET')));
			}
		}
		$lottery = $this->SelectDataByQuery($this->TName('tLottery'),$condition);
		$lotteryList = DBResultToArray($lottery,true);
		
		$lotteryReject = $this->SelectDataByQuery($this->TName('tLottery'),$conditionReject);
		$lotteryRejectList = DBResultToArray($lotteryReject,true);
		$this->UpdateDataToTableByQuery($this->TName('tLottery'),['state'=>'GET'],$condition);
		$this->UpdateDataToTableByQuery($this->TName('tLottery'),['state'=>'MISS'],$conditionReject);
		$backMsg = RESPONDINSTANCE('0');
		$backMsg['award'] = $awardList;
		$backMsg['lList'] = $lotteryList;
		$backMsg['lListReject'] = $lotteryRejectList;
		return $backMsg;
	}

    //检查梦想池非正常结束记录
    public function FixDreamPoolUnrightbleFinished(){
        $DPM = new DreamPoolManager();
        echo "现在时间:".date("Y-m-d H:i",PRC_TIME()).'</br>';
        $condition = "";
        $fcondition = "";
        $array = DBResultToArray($DPM->SelectDatasFromTable($DPM->TName('tPool'),
            []));
        foreach ($array as $key => $value) {
            if((PRC_TIME()>$value['ptime']+$value['duration']) || ($value['cbill']>$value['tbill'])){
                //echo '持续时间:'.$value['duration'].'</br>';
                //echo '结束时间:'.date("Y-m-d H:i",($value['ptime']+$value['duration'])).'</br>';
                $fcondition = $fcondition.'`pid`="'.$value['pid'].'" OR ';
            }else{
                echo $value['pid'].' 开始时间:'.date("Y-m-d H:i",$value['ptime']).' 结束时间:'.date("Y-m-d H:i",($value['ptime']+$value['duration']))."  目标款项目:".$value['tbill']."  已筹金额:".$value['cbill']."  [未结束]</br>";
                $condition = $condition.'`pid`="'.$value['pid'].'" OR ';
                //array_push($fixID,$value['pid']);
            }
        }
        $condition = $condition.' `pid`=""';
        $fcondition = $fcondition.' `pid`=""';
        //echo $fcondition;
        $DPM->UpdateDataToTableByQuery($DPM->TName('tPool'),['state'=>'RUNNING'],$condition);
        $DPM->UpdateDataToTableByQuery($DPM->TName('tPool'),['state'=>'FINISHED'],$fcondition);
    }

    //修复中奖获得金额信息
    public function FixUserAwardMoney(){
        $AWM = new AwardManager();
        $result= $AWM->SelectDataFromTable($AWM->TName('tLottery'),['state'=>'GET']);
        $lots = DBResultToArray($result,true);
        $rewards = [];
        foreach ($lots as $lot) {
            $user = DBResultToArray($AWM->SelectDataFromTable($AWM->TName('tUser'),['uid'=>$lot['uid']]),true)[0];
            $pool = DBResultToArray($AWM->SelectDataFromTable($AWM->TName('tPool'),['pid'=>$lot['pid']]),true)[0];
            echo $pool['pid'].'  '.$user['nickname'].'  '.$pool['cbill'].'</br>';
            $rewards[$user['nickname']]['totalReward'] = $user['totalReward'];
            $rewards[$user['nickname']]['cacuReward'] = isset($rewards[$user['nickname']]['cacuReward'])?($rewards[$user['nickname']]['cacuReward']+$pool['cbill']):$pool['cbill'];
        }

        echo json_encode($rewards,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }


    public function TestNotice(){
        NoticeManager::CreateNotice(
            'on8W94tv5jTTiItf1uJCBdLJPyic',
            NOTICE_BUY,
            [
                'ptitle'=>'梦想互助20190101期',
                'lids'=>PublicTools::ConnectArrayByChar(['001','002'],'、')
            ],
            NoticeManager::CreateAction(
                'buy',
                [
                    'pid'=>'20190101'
                ]
            )
        );
    }

    //将旧版实名认证转换位新版本数据
    public function ConvertRealNameToNewVersion(){
        $aResult = DBResultToArray($this->SelectDataByQuery($this->TName('tId'),'1'),true);
        foreach($aResult as $key=>$value){
            $result = $this->InsertDataToTable($this->TName('tIdx'),
                [
                    "uid"=>$value['uid'],
                    "realname"=>'缺省',
                    "icardnum"=>$value['icardnum'],
                    "ccardnum"=>$value['ccardnum'],
                    "bank"=>'缺省',
                    "openbank"=>'缺省',
                    "icardfurl"=>$value['icardfurl'],
                    "ftime"=>$value['ftime'],
                    "state"=>$value['state'],
                ]
            );
            if($result){
                echo '已更新'.$value['uid'].'</br>';
            }else{
                echo '已存在'.$value['uid'].',无需更新</br>';
            }
        }
    }

    public function TryWrongLottery(){
        $AWM = new AwardManager();
        $tryResult = $AWM->SelectDataByQuery($AWM->TName('tDream'),self::FieldIsValue('state','SUBMIT&FAILED','!='));
        //echo self::FieldIsValue('state','SUBMIT&FAILED','!=');
        $tryList = DBResultToArray($tryResult);
        foreach ($tryList as $key => $value) {
            $lotteryResult = $AWM->SelectDataByQuery($AWM->TName('tLottery'),
                self::C_And(
                    self::FieldIsValue('did',$key),
                    self::FieldIsValue('state','GET')
                )
            );
            $tryList[$key]['lottery'] = DBResultToArray($lotteryResult,true);
        }
        return $tryList;
       /* $AWM->SelectDataByQuery($AWM->TName('tLottery'),['state'=>'MISS'],
            self::C_And(
                self::FieldIsValue('state','GET'),
                self::FieldIsValue('did',$did)
            )
        );*/

    }

    public function DebugUsers(){
        $configUserList = json_encode("defaultUser");
        if(isset($_REQUEST['uconfig'])){
            $configUserList = file_get_contents($_REQUEST['uconfig']);
        }
        echo include_once "userlog.php";
    }

    public function RefreashFunc(){
        /*
         * UPDATE `dream` SET `uid`="MissUser" WHERE `uid`="oSORf5kkXvHNxhIx8lQVe3DFRFvw"
         * UPDATE `user` SET `totalJoin` = '0' WHERE `user`.`uid` = 'oSORf5kkXvHNxhIx8lQVe3DFRFvw';
         * UPDATE `user` SET `tele` = '' WHERE `user`.`uid` = 'oSORf5kkXvHNxhIx8lQVe3DFRFvw';
         *
         * */
        $this->UpdateDataToTableByQuery($this->TName('tDream'),['uid'=>'MissUser'],self::FieldIsValue('uid',"oSORf5kkXvHNxhIx8lQVe3DFRFvw"));
        $this->UpdateDataToTableByQuery($this->TName('tUser'),['totalJoin'=>'0','tele'=>''],self::FieldIsValue('uid',"oSORf5kkXvHNxhIx8lQVe3DFRFvw"));

        return RESPONDINSTANCE('0');
    }
	
	public function TestBat(){
		file_put_contents(time().'.txt',time());
	}
	
	//创建时间戳
	public function GenerateTimeStamp(){
		return PRC_TIME();
	}
}
?>