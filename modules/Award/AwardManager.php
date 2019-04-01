<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);
LIB('db');
LIB('ds');
LIB('dp');
LIB('dr');
LIB('us');
LIB('no');
LIB('va');
LIB('tr');

class AwardManager extends DBManager{

    public function info()
    {
       // self::GetAwardTradeByUid('oSORf5kkXvHNxhIx8lQVe3DFRFvw');
        //self::AwardedDreamVailid('DR1000000029');
        //echo json_encode(self::DrawTheWinnerLottery(),JSON_UNESCAPED_UNICODE);
        //echo  json_encode($this->GetLottoryInfo('p01-10000001'));
        return "开奖模块"; // TODO: Change the autogenerated stub
    }


    //通过uid获取用户中奖的小生意信息
    public static function GetAwardTradeByUid($uid){
        //SELECT * FROM `award` WHERE `uid`="oSORf5kkXvHNxhIx8lQVe3DFRFvw" AND `did` LIKE "TR%"
        $AWM = new AwardManager();
        $tradeAwardArray =DBResultToArray(
            $AWM->SelectDataByQuery($AWM->TName('tAward'),
            self::C_And(
                self::FieldIsValue('uid',$uid),
                self::FieldLikeValue('did','TR%')
            )
        ),true);

        $tradeBack = [];
        foreach ($tradeAwardArray as $key=>$item) {
            $trade = TradeManager::GetTradeInfoByTid($item['did']);
            $tradeBack[$key]['title'] = $trade['title'];
            $tradeBack[$key]['state'] = 'SUCCESS';
            $tradeBack[$key]['lottery'] = self::GetAwardLotteryByDreamID($item['did']);
            $tradeBack[$key]['pool'] = DreamPoolManager::Pool($trade['pid']);
        }

        return $tradeBack;
        //echo json_encode($tradeAwardArray);
    }

    //通过pid获取中奖用户信息
    public static function GetAwardUserByPid($pid){
        $AWM = new AwardManager();
        $awrad = DBResultToArray($AWM->SelectDataByQuery($AWM->TName('tAward'),
            self::FieldIsValue('pid',$pid)
            ),true);
        if(!empty($awrad)){
            $awrad = $awrad[0];
            return $awrad['uid'];
        }else{
            return null;
        }
    }

    //获取用户在某参与梦想池的编号数量
    public static function GetUserLottery($pid,$uid){
        $sql = 'SELECT COUNT(*) FROM `lottery` WHERE `uid`="'.$uid.'" AND `pid`="'.$pid.'"';
        return mysql_fetch_array(mysql_query($sql))[0];
    }

    //通过梦想id获取中奖编号
    public static function GetAwardLotteryByDreamID($did){
        $AWM = new AwardManager();
        $Lottery = DBResultToArray($AWM->SelectDataFromTable($AWM->TName('tLottery'),
            [
                'did'=>$did,
                'state'=>'GET',
                '_logic'=>'AND'
            ]
            ),true);
        if(!empty($Lottery)){
            return $Lottery[0];
        }
        return $Lottery;
    }

    //开奖
    public static function DrawTheWinnerLottery(){
        $data = json_decode(file_get_contents("http://f.apiplus.net/ssq.json"),true)['data'];

        if(empty($data)){
            return RESPONDINSTANCE('22');
        }

        $data = $data[0];
        $result =[];

        //foreach ($data as $value){
            $codes = explode(',',$data['opencode']);
            $tcode = '';
            if(substr( $codes[0] , 0, 1 ) == '0') {
                $codes[0] = str_replace(array("0"), "", $codes[0]);
            }
            for($i=0;$i<5;$i++){
                $tcode = $tcode.$codes[$i];
            }

            $result[$data['expect'].'期']['num'] = $data['expect'] + $tcode;
            $result[$data['expect'].'期']['expect'] = $data['expect'];
            $result[$data['expect'].'期']['code'] = $tcode;

            $AW = new AwardManager();

            if(DBResultExist($AW->SelectDataFromTable($AW->TName('tAward'),['expect'=>$data['expect'],'code'=> $tcode,'_logic'=>'OR']))){
                return RESPONDINSTANCE('23',$data['expect']);//该期双色球已开奖
            }

            $backMsg = RESPONDINSTANCE('0');
            $result[$data['expect'].'期']['target'] = $AW->DoneAlottery( $result[$data['expect'].'期']['num'],$data['expect'],$tcode);
            $backMsg['info'] = $result;
        //}
        return $backMsg;
    }
	
	//尝试开奖
    public static function TryWinnerLottery(){
        $data = json_decode(file_get_contents("http://f.apiplus.net/ssq.json"),true)['data'];

        if(empty($data)){
            return RESPONDINSTANCE('22');
        }

        $data = $data[0];
        $result =[];

        //foreach ($data as $value){
            $codes = explode(',',$data['opencode']);
            $tcode = '';
            if(substr( $codes[0] , 0, 1 ) == '0') {
                $codes[0] = str_replace(array("0"), "", $codes[0]);
            }
            for($i=0;$i<5;$i++){
                $tcode = $tcode.$codes[$i];
            }

            $result[$data['expect'].'期']['num'] = $data['expect'] + $tcode;
            $result[$data['expect'].'期']['expect'] = $data['expect'];
            $result[$data['expect'].'期']['code'] = $tcode;

            $AW = new AwardManager();

            if(DBResultExist($AW->SelectDataFromTable($AW->TName('tAward'),['expect'=>$data['expect'],'code'=> $tcode,'_logic'=>'OR']))){
                $backMsg['done'] = true;
                //return RESPONDINSTANCE('23',$data['expect']);//该期双色球已开奖
            }

            $backMsg = RESPONDINSTANCE('0');
            $result[$data['expect'].'期']['target'] = $AW->Trylottery($result[$data['expect'].'期']['num'],$data['expect'],$tcode);
            $backMsg['info'] = $result;
        //}
        return $backMsg;
    }

    //中奖的梦想生效
    public static function AwardedDreamVailid($did){
    $AW = new AwardManager();

    $awardResult = DBResultToArray(
        $AW->SelectDataByQuery($AW->TName('tAward'),
            self::Limit(
                self::OrderBy(
                    self::FieldIsValue('did',$did),
                    'atime',
                    'DESC'
                ),0,1
            )
        )

        /*$AW->SelectDataFromTable($AW->TName('tAward'),
            [
                'did'=>$did,
                '_logic'=>' '
            ]
        )*/
        ,true);
    //$dream = $awardResult;
    if(!empty($awardResult)){
        $dream = $awardResult[0];
        return !(($dream['atime'] + 86400*7) < PRC_TIME());//返回是否在有效期（7天）内
    }
    return false; //请求梦想未中奖
}

    public function AwardManager(){
        parent::__construct();
    }

	//生成开奖编号
	public static function GenerateLotteryID($pid,$index){
        return ($pid.'-'.(10000000+$index));
    }

    public static function GetPoolLotteryCount($pid){
        $AWM = new AwardManager();
        $result = DBResultToArray($AWM->SelectDataByQuery($AWM->TName('tLottery'),self::FieldIsValue('pid',$pid),false,'COUNT(*)'),true);
        if(empty($result)){
            return 0;
        }else{
            return $result[0]['COUNT(*)'];
        }
    }

	//支付后生成的中奖号
	public static function PayOrderAndCreateLottery($pid,$uid,$did,$oid,$startIndex,$endIndex){
        $AWM = new AwardManager();
        $Numbers = [];

        for($i=$startIndex;$i<$endIndex;$i++) {
            $lid = self::GenerateLotteryID($pid,$i);
            $awardArray = [
                "lid" => $lid,
                "pid" => $pid,
                "uid" => $uid,
                "index" => $i,
                "oid" => $oid,
                "did" => $did,
                'state'=>'WAITTING'
            ];
            if($AWM->InsertDataToTable($AWM->TName('tLottery'),$awardArray)){
                $Numbers[$lid] = $awardArray;
            }
        }
        return $Numbers;
    }

    public function UpdateLottery($lotteryId){
        $result = DBResultToArray($this->SelectDataFromTable($this->TName('tLottery'),['lid'=>$lotteryId,'_logic'=>' '],false,'did,uid'),true);
        if(empty($result)){
            return $result;
        }else{
            $did = $result[0]['did'];
            $uid = $result[0]['uid'];
            $count = DBResultToArray($this->SelectDataByQuery($this->TName('tLottery'),
                self::C_And(
                    self::FieldIsValue('did',$did),
                    self::FieldIsValue('state','GET')
                ),false,'COUNT(*)'),true)[0]['COUNT(*)'];
            $result['count'] = $count;
            if($count>1){
                $otherDreamID = DBResultToArray($this->SelectDataByQuery($this->TName('tDream'),
                    self::Limit(
                        self::C_And(
                            self::C_And(
                                self::FieldIsValue('state','SUBMIT|FAILED'),
                                self::FieldIsValue('did',$did,'!=')
                            ),
                            self::FieldIsValue('uid',$uid)
                        ),
                        0,1
                    ),
                    false,
                    'did'
                ),true);

                if(!empty($otherDreamID)){
                    $otherDreamID = $otherDreamID[0]['did'];
                    $this->UpdateDataToTableByQuery($this->TName('tLottery'),
                        ['did'=>$otherDreamID],
                        self::FieldIsValue('lid',$lotteryId)
                    );
                    $result['exchangeDream'] = true;
                }else{
                    $result['exchangeDream'] = false;
                }
            }
            return $result;
        }
    }

//ds=sver
    public function AwardUserByPid($pid){
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['awardUser'] = self::GetAwardUserByPid($pid);
        return $backMsg;
    }

    //通过id获取开奖编号信息
    public function GetLottoryInfo($lotteryId){
        $result = DBResultToArray($this->SelectDataFromTable($this->TName('tLottery'),['lid'=>$lotteryId,'_logic'=>' ']),true,false);
        if(empty($result)){
            return $result;
        }else{
            return $result[0];
        }
    }

    //批量设置梦想池中的编号状态
    public function SetPoolsAwardLottery($pid,$awardlid){
        $this->UpdateDataToTable(
            $this->TName('tLottery'),
            ['state'=>'MISS'],
            ['pid'=>$pid,
            '_logic'=>' '
            ]
        );
        $this->UpdateDataToTable(
            $this->TName('tLottery'),
            ['state'=>'GET'],
            ['pid'=>$pid,
             'lid'=>$awardlid,
             '_logic'=>'AND'
            ]
        );
    }

    public function GetUnawardPools(){
        return DreamPoolManager::GetAllUnAwardPools();
    }

    //自动开奖
    public function AutoLottery(){
        return self::DrawTheWinnerLottery();
    }
	
	//尝试自动开奖
	public function AutoTryLottery(){
		return self::TryWinnerLottery();
	}


    public function SendShortMsgToUser($pid,$awardUid,$awardLid){
        $list = DBResultToArray($this->SelectDataByQuery($this->TName('tLottery'),
            self::C_And(
                self::FieldIsValue('pid',$pid),
                self::C_And(
                    self::FieldIsValue('uid',$awardUid,'!='),
                    self::FieldIsValue('lid',$awardLid,'!=')
                )
            ),
            false,'`uid`')
            ,true
        );
        $key = [];
        $uidlist = [];
        foreach ($list as $value) {
            if(isset($key[$value['uid']])){
                continue;
            }
            $key[$value['uid']] = true;
            array_push($uidlist,$value['uid']);
        }
        $teleResultlist = UserManager::GetTelesByUidList($uidlist);
        $telekey = [];
        $telelist = [];
        $index = 0;
        $seek = 0;
        $telelist[$seek] = [];
        foreach ($teleResultlist as $value) {
            if(isset($telekey[$value['tele']])){
                continue;
            }
            $key[$value['tele']] = true;
            if($index>98){
                $seek++;
                $telelist[$seek] = [];
                $index = 0;
            }else{
                array_push($telelist[$seek],$value['tele']);
            }
            $index++;
        }

        $awardTele = UserManager::GetUserTele($awardUid);
        $awardTeleBack = ValidateManager::SendAwardMsg([$awardTele],$pid,$awardLid);
        $missTeleBack = [];
        foreach ($telelist as $key => $value) {
            $missTeleBack[$key] = ValidateManager::SendMissMsg($value,$pid);
        }
        return ['teles'=>$telelist,'awardTeleBack'=>$awardTeleBack,'missTeleBack'=>$missTeleBack];
    }

    //梦想池开奖
    public function DoneAlottery($DoalBallNum,$expect,$code){

        $result = DreamPoolManager::UpdateAllPools();//更新所有梦想池的状态
        $backMsg = RESPONDINSTANCE('0');

        $resultArray = [];
        $count = 0;
        $time = PRC_TIME();

        $pools = DreamPoolManager::GetAllUnAwardPools();


        foreach ($pools as $key => $item) {
            if($item['state'] == 'FINISHED'){
                if($item['pcount'] <=0){
                    $cResult = "未中奖";
                }
                else {
                    $cResult = $key . '-' . (10000000+ (($DoalBallNum+$item['pid'] ) % $item['pcount']));

                    $this->UpdateLottery($cResult);//更新编号对应梦想
                    $targetLottery = $this->GetLottoryInfo($cResult);
                    if(empty($targetLottery)){
                        $cResult = "没有编号:".$cResult;
                        $backMsg['DonePools'][$key] = $cResult;
                        continue;
                    }

                    if(isset($item['ptype']) && $item['ptype'] == "STANDARD")
                        DreamManager::OnDreamDoing($targetLottery['did']);//更新梦想表——梦想实现
                    if(isset($item['ptype']) && $item['ptype'] == "STANDARD")
                        UserManager::OnUserReward($targetLottery['uid'],$item['cbill']);//更新用户表——用户中奖总额修改
                    $this->SetPoolsAwardLottery($item['pid'],$targetLottery['lid']);//更新编号信息（中奖/未中奖）
					if(!$GLOBALS['options']['debug'] && !isset($_REQUEST['dblink'])){
						$this->SendShortMsgToUser($item['pid'],$targetLottery['uid'],$targetLottery['lid']);
					}
                    $resultArray[$count][0] = $item['pid'];//梦想池id
                    $resultArray[$count][1] = $targetLottery['uid'];//中奖用户id
                    $resultArray[$count][2] = $targetLottery['lid'];//开奖编号
                    $resultArray[$count][3] = $expect;//期号
                    $resultArray[$count][4] = $code;//球号
                    $resultArray[$count][5] = $targetLottery['index'];//梦想编号
                    $resultArray[$count][6] = $time;//开奖时间
                    $resultArray[$count][7] = $targetLottery['did'];//中奖梦想id
                    $resultArray[$count][8] = $item['cbill'];//金额
                    NoticeManager::CreateNotice(//创建通知——开奖
                        $targetLottery['uid'],NOTICE_GET,
                        [
                            'ptitle'=>'梦想互助'.$item['pid'].'期',
                            'lid'=>$targetLottery['lid']
                        ],
                        NoticeManager::CreateAction('lucky',
                        [
                            'did'=>$targetLottery['index'],
                            'lid'=>$targetLottery['lid']
                        ]
                    ));

                }
                $backMsg['DonePools'][$key] = $cResult;
                $count++;
            }

        }

        /*
         *
         * 小生意互助潜在修改位置
         *
         * */

        $this->UpdateDataToTableByQuery($this->TName('tPool'),['award'=>'YES'],
            self::C_And(
                self::FieldIsValue('award','NO'),
                self::ExpressionIsValue(
                    self::Symbol(
                        self::SqlField('ptime'),
                        self::SqlField('duration'),
                        '+'
                    ),
                    PRC_TIME(),
                    '<'
                )
            )
        );
        //$this->UpdateDataToTable($this->TName('tPool'),
        //    ['award'=>'YES'],['award'=>'NO','_logic'=>' ']);
      //  echo json_encode($resultArray);
        /*for($i=0;$i<$pCount;$i++){
            $resultArray[$i][0] = sha1("ghosteum_".$password."_".(1000000+$i));
            $resultArray[$i][1] = "player".($i+1);
            $resultArray[$i][2] = "null";
            $resultArray[$i][3] = "0.0.0.0";
            $resultArray[$i][4] = "NONE";
            $resultArray[$i][5] = floor($i/($pCount/$fCount));
        }*/

        if(empty($resultArray)){
            $this->InsertDataToTable($this->TName('tAward'),
                [
                    "pid"=>DAY($time),
                    "uid"=>'无开奖',
                    "lid"=>'无开奖',
                    "expect"=>$expect,
                    "code"=>$code,
                    "index"=>0,
                    "atime"=>PRC_TIME(),
                    "did"=>'无开奖',
                    "abill"=>0,
                    "imgurl"=>''
                ]);
        }else {
            $this->InsertDatasToTable($this->TName('tAward'),
                [
                    "key" => ["pid", "uid", "lid", "expect", "code", "index", "atime", "did", "abill"],
                    "values" => $resultArray
                ]);
        }
        if(!isset($backMsg['DonePools'])){
            $backMsg['DonePools'] = '无符合条件梦想池';
        }

        return $backMsg;
    }
	
	public function Trylottery($DoalBallNum,$expect,$code){

        $result = DreamPoolManager::UpdateAllPools();//更新所有梦想池的状态
        $backMsg = RESPONDINSTANCE('0');

        $resultArray = [];
        $count = 0;
        $time = PRC_TIME();

        $pools = DreamPoolManager::GetAllUnAwardPools();

        foreach ($pools as $key => $item) {
            if($item['state'] == 'FINISHED'){
                if($item['pcount'] <=0){
                    $cResult = "未中奖";
                }
                else {
                    $cResult = $key . '-' . (10000000+ (($DoalBallNum+$item['pid'] ) % $item['pcount']));
					//更新编号对应梦想
                   /* $this->UpdateLottery($cResult);
                    
                    */
					$targetLottery = $this->GetLottoryInfo($cResult);
					if(empty($targetLottery)){
                        $cResult = "没有编号:".$cResult;
                        $backMsg['DonePools'][$key] = $cResult;
                        continue;
                    }

                    //更新梦想表——梦想实现
                    //更新用户表——用户中奖总额修改
                    //更新编号信息（中奖/未中奖）
					//发送中奖消息
                    $resultArray[$count][0] = $item['pid'];//梦想池id
                    $resultArray[$count][1] = $targetLottery['uid'];//中奖用户id
                    $resultArray[$count][2] = $targetLottery['lid'];//开奖编号
                    $resultArray[$count][3] = $expect;//期号
                    $resultArray[$count][4] = $code;//球号
                    $resultArray[$count][5] = $targetLottery['index'];//梦想编号
                    $resultArray[$count][6] = $time;//开奖时间
                    $resultArray[$count][7] = $targetLottery['did'];//中奖梦想id
                    $resultArray[$count][8] = $item['cbill'];//金额
					//创建通知——开奖
                }
                $backMsg['DonePools'][$key] = $cResult;
                $count++;
            }

        }

        /*
         *
         * 小生意互助潜在修改位置
         *
         * */

        /*$this->UpdateDataToTableByQuery($this->TName('tPool'),['award'=>'YES'],
            self::C_And(
                self::FieldIsValue('award','NO'),
                self::ExpressionIsValue(
                    self::Symbol(
                        self::SqlField('ptime'),
                        self::SqlField('duration'),
                        '+'
                    ),
                    PRC_TIME(),
                    '<'
                )
            )
        );*/
		//更新梦想池开奖状态
		

        if(empty($resultArray)){
			 $backMsg['insertData'] = [
                    "pid"=>DAY($time),
                    "uid"=>'无开奖',
                    "lid"=>'无开奖',
                    "expect"=>$expect,
                    "code"=>$code,
                    "index"=>0,
                    "atime"=>PRC_TIME(),
                    "did"=>'无开奖',
                    "abill"=>0,
                    "imgurl"=>''
                ];
            /*$this->InsertDataToTable($this->TName('tAward'),
                [
                    "pid"=>DAY($time),
                    "uid"=>'无开奖',
                    "lid"=>'无开奖',
                    "expect"=>$expect,
                    "code"=>$code,
                    "index"=>0,
                    "atime"=>PRC_TIME(),
                    "did"=>'无开奖',
                    "abill"=>0,
                    "imgurl"=>''
                ]);*/
        }else {
			 $backMsg['insertData'] = $resultArray;
           /* $this->InsertDatasToTable($this->TName('tAward'),
                [
                    "key" => ["pid", "uid", "lid", "expect", "code", "index", "atime", "did", "abill"],
                    "values" => $resultArray
                ]);*/
        }
        if(!isset($backMsg['DonePools'])){
            $backMsg['DonePools'] = '无符合条件梦想池';
        }

        return $backMsg;
    }

    //获取订单的抽奖号
    public function GetLotteryByOrder($oid){
        $sResult = $this->SelectDataFromTable($this->TName('tLottery'),['oid'=>$oid,'_logic'=>' ']);
        $orderArray = DBResultToArray($sResult,true);
        if(!empty($orderArray)){
            $orderArray = $orderArray;
        }
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['nums'] = $orderArray;
        return $backMsg;
    }

    //获取梦想池开奖号
    public function GetLotteryFromPid($pid){
        $sResult = $this->SelectDataFromTable($this->TName('tAward'),['pid'=>$pid,'_logic'=>' '],false,'`lid`');
        $lottery = DBResultToArray($sResult,true);
        if(!empty($lottery)){
            $lid = $lottery[0]['lid'];
            $backMsg = RESPONDINSTANCE('0');
            $backMsg['lid'] = $lid;
        }else{
            $backMsg = RESPONDINSTANCE('59');
        }
        return $backMsg;
    }



	//获取往期幸运者数量
    public function CountPreviousLucky(){
        $link = $this->DBLink();

        $condition = self::FieldIsValue('uid','无开奖','!=');

        if(isset($_REQUEST['awardtype'])){
            $condition = self::C_And($condition,self::SqlField('did')." LIKE '".$_REQUEST['awardtype']."%'");
        }

        $sql = "SELECT COUNT(*) FROM `".$this->TName("tAward")."` WHERE ".$condition;

        mysql_query($sql,$link);

        $cResult = DBResultToArray(mysql_query($sql,$link),true);

        $backMsg = RESPONDINSTANCE('0');
        $backMsg['count'] = $cResult[0]["COUNT(*)"];
        return $backMsg;
    }

	//获取需往期幸运者
	public function GetPreviousLuckyByRange($seek,$count){
        //未实现
        $link = $this->DBLink();

        $condition = self::FieldIsValue('uid','无开奖','!=');

        if(isset($_REQUEST['awardtype'])){
            $condition = self::C_And($condition,self::SqlField('did')." LIKE '".$_REQUEST['awardtype']."%'");
        }

        $sql = "SELECT * FROM `".$this->TName('tAward')."` WHERE ".$condition." ORDER BY `atime` DESC LIMIT $seek,$count";

       // echo $sql;

        $cResult = DBResultToArray(mysql_query($sql,$link),true);

		$condition = "";
		$dcondition = "";
		$tcondtion = "";

		$ptypeList = [];
		foreach($cResult as $key=>$value){
			$condition = $condition.$value['uid'].'|';
			if(DreamServersManager::DidFlag($value['did'],"DR")){
			    $dcondition = $dcondition.$value['did'].'|';
				$ptypeList[$key] = "STANDARD";
            }
            if(DreamServersManager::DidFlag($value['did'],"TR")){
                $tcondtion = $tcondtion.$value['did'].'|';
				$ptypeList[$key] = "TRADE";
            }
		}

		$userInfo = UserManager::GetUsersInfoByString($condition);

		$dreamsInfo = DreamManager::GetDreamsByConditionStr($dcondition);

		$tradesInfo = TradeManager::GetDreamsByConditionStr($tcondtion);

		foreach($cResult as $i=>$value){
			if(array_key_exists($value['uid'],$userInfo)){
				$cResult[$i]['nickname'] =  $userInfo[$value['uid']]['nickname'];
				$cResult[$i]['headicon'] =  $userInfo[$value['uid']]['headicon'];
			}

			if(array_key_exists($value['did'],$dreamsInfo)){
				$cResult[$i]['title'] =  $dreamsInfo[$value['did']]['title'];
				$cResult[$i]['content'] =  $dreamsInfo[$value['did']]['content'];
				$cResult[$i]['state'] =  $dreamsInfo[$value['did']]['state'];
			}

			if(array_key_exists($value['did'],$tradesInfo)){
                $cResult[$i]['title'] =  $tradesInfo[$value['did']]['title'];
                $cResult[$i]['tinfoid'] =  $tradesInfo[$value['did']]['url'];
                $cResult[$i]['content'] =  "小生意互助";
                $cResult[$i]['state'] =  "SUCCESS";
            }
			$cResult[$i]['ptype'] = isset($ptypeList[$i])?$ptypeList[$i]:"STANDARD";
		}

        $backMsg = RESPONDINSTANCE('0');
        $backMsg['awards'] = $cResult;
        return $backMsg;
	}

	//计算中奖步骤
	public function GetCalc($pid){
        $awardInfo = DBResultToArray($this->SelectDataByQuery($this->TName('tAward'),self::FieldIsValue('pid',$pid)),true);
        if(!empty($awardInfo)){
            $awardInfo = $awardInfo[0];
        }else{
			return RESPONDINSTANCE('62');
		}

        /*
         *
         * 小生意互助潜在修改位置
         *
         * */

        $pcount = DBResultToArray($this->SelectDataByQuery($this->TName('tPool'),
            self::FieldIsValue('pid',$pid),false,'pcount'),true);
        if(!empty($pcount)){
            $pcount = $pcount[0]['pcount'];
        }
        $awardInfo['pcount'] = $pcount;
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['awardInfo'] = $awardInfo;
        return $backMsg;
    }

    public function ActivityStart($pid){
        $awardResult = DBResultToArray($this->SelectDataByQuery(
            $this->TName('tAward'),
            self::FieldIsValue('pid',$pid)
        ),true);
        if(empty($awardResult)){
            return RESPONDINSTANCE('66');
        }else{
            $awardResult = $awardResult[0];
            $backMsg = RESPONDINSTANCE('0');
            $backMsg['token'] = UserManager::GenerateActivityPhoto($awardResult['uid']);
            return $backMsg;
        }
    }

    public function ActivityEnd($pid,$url){
        $this->UpdateDataToTableByQuery($this->TName('tAward'),['imgurl'=>$url], self::FieldIsValue('pid',$pid));
        return RESPONDINSTANCE('0');
    }

    public function ActivityLive(){
        return DBResultToArray($this->SelectDataByQuery($this->TName('tAward'),
            self::C_And(
                self::FieldIsValue('uid','无开奖','!='),
                self::ExpressionIsValue(
                    self::SqlValue(PRC_TIME()).'-'.self::SqlField('atime'),
                    86400*7,
                    "<"
                )
           )
        ),true);
    }

    public function GetTradeAwardInfo($pid,$uid){
        $precent = TradeManager::GetTradeProfitPercent($uid,$pid);
        if(empty($precent)){
            return RESPONDINSTANCE('80');
        }
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['precent'] = $precent;
        return $backMsg;
    }

}
?>