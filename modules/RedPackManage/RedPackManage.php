<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);
LIB("ds");//
LIB("dr");//
LIB("dp");//
LIB("no");//通知
LIB("us");//用户
class RedPackManage extends DBManager {

    public function info()
    {
        return "RedPackManage"; // TODO: Change the autogenerated stub
    }

    public static function GenerateRedPackageID(){
        $RPM = new RedPackManage();
        //生成订单号
        do{
            $newOrderID = 300000000000+((PRC_TIME()%999999).(rand(10000,99999)));
        }while($RPM->SelectDataFromTable('tROrder',['rid'=>$newOrderID,'_logic'=>' ']));
        return $newOrderID;
        return "1111123447465765757653";
    }
    //生成领取红包时的订单号
    public static function GenerateRedPackageOrderID(){
        $RPM = new RedPackManage();
        //生成订单号
        do{
            $newOrderID = 900000000000+((PRC_TIME()%999999).(rand(10000,99999)));
        }while($RPM->SelectDataFromTable('tROrder',['rid'=>$newOrderID,'_logic'=>' ']));
        return $newOrderID;
    }

    public static function GenerateRedPackageOrder($uid,$pid,$bill,$did){
        $RPM = new RedPackManage();
        $orderArray = [
            "oid"=>self::GenerateRedPackageOrderID(),
            "uid"=>$uid,
            "pid"=>$pid,
            "bill"=>$bill,
            "ctime"=>PRC_TIME(),
            "ptime"=>PRC_TIME(),
            "state"=>"SUCCESS",
            "dcount"=>1,
            "did"=>$did,
        ];
        $RPM->InsertDataToTable($RPM->TName('tOrder').$orderArray);

    }

    //获取红包的领取信息
    public static function GenerateRedPackageRecInfo($rid){
        return [
            "rpid"=>"",
            "index"=>0,
        ];
    }

    //删除所有用户的Payment订单
    public static function RemoveAllPaymentRedOrder($uid){
        $RPM = new RedPackManage();
        $RPM->DeletDataByQuery($RPM->TName('tROrder'),
            self::C_And(
                self::FieldIsValue('uid',$uid),
                self::FieldIsValue('state',"PAYMENT")
            )
        );
    }

    //获取红包剩余数量
    public static function GetRedPackLessCount($rid){
        $RPM = new RedPackManage();
        $less = DBResultToArray($RPM->SelectDataByQuery($RPM->TName('tROrder'),
                self::FieldIsValue('rid',$rid)
        ));
        if(empty($less)){
            return 0;
        }
        return $less['rcount'] - $less['gcount'];
    }

    //发红包统一下单准备支付
    public function CreateRedPackae($pid,$rcount,$content,$bill,$uid){
        /*
         *
         * 前提条件的判断
         *
         * */
        //用户绑定手机号
            //未绑定，返回需绑定
        if(!UserManager::IdentifyTeleUser($uid)){
            return RESPONDINSTANCE('11');//若未绑定手机即会提示先绑定手机
        }

        //梦想互助期号是否存在，未完成互助
            //梦想互助失效，返回失败
        if(!DreamPoolManager::IsPoolRunning($pid)){
            return RESPONDINSTANCE('5');
        }

        //rcount小于200，
            //rcount大于200，返回错误
        if($rcount>200){
            return RESPONDINSTANCE('67');
        }

        //rcount小于剩余，
            //rcount大于剩余，返回错误
        $lessCount = DreamPoolManager::GetLessLotteryCount($pid);

        //当rcount大于梦想互助剩余梦想数量
        if($rcount>$lessCount){
            return RESPONDINSTANCE('69',$lessCount);
        }

        //删除用户有PAYMENT的订单
        self::RemoveAllPaymentRedOrder($uid);

        $rid = self::GenerateRedPackageID();
        $gcount = 0;
        $acount = 1;
        $rtype = "STANDARD";
        $ctime = PRC_TIME();
        $ptime = 0;
        $state = "PAYMENT";


        //统一下单 crp
        $DSM = new DreamServersManager();
        $orderInfo = $DSM->WxPayWeb($rid,$bill,$uid);
        if($orderInfo['code'] != "0"){
            //统一下单错误
//            return RESPONDINSTANCE('');
            return RESPONDINSTANCE('68',$orderInfo['code']);
        }else{
            //插入红包信息进入数据库，redpackorder数据表
            $this->InsertDataToTable(
                $this->TName('tROrder'),
                [
                    "rid"=>$rid,
                    "uid"=>$uid,
                    "pid"=>$pid,
                    "bill"=>$bill,
                    "rcount"=>$rcount,
                    "gcount"=>$gcount,
                    "acount"=>$acount,
                    "content"=>$content,
                    "rtype"=>$rtype,
                    "ctime"=>$ctime,
                    "ptime"=>$ptime,
                    "state"=>$state,
                ]
            );
        }
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['order'] = $orderInfo;
        $backMsg['rid'] = $rid;
        return $backMsg;
    }

    //红包支付创建成功 cprs
    public function CreateRedPackSuccess($uid,$rid){
        //判断用户是否创建了该红包
            //用户不拥有该红包，为错误
        $existPackage = DBResultToArray($this->SelectDataByQuery($this->TName('tROrder'),
            self::C_And(
                self::FieldIsValue('uid',$uid),
                self::C_And(
                    self::FieldIsValue('rid',$rid),
                    self::FieldIsValue('state','PAYMENT')
                )
            )
        ),false,self::SqlField('rid'));
        if(empty($existPackage)){
            return RESPONDINSTANCE('70');
        }

        $this->UpdateDataToTableByQuery($this->TName('tROrder'),
            [
                'state'=>"RUNNING",
                'ptime'=>PRC_TIME()
            ]
        );

        //订单状态修改为RUNNINNG
        //ptime修改为当前时间
        $backMsg = RESPONDINSTANCE('0');
        return $backMsg;
    }
    //获取红包信息,打开领取红包页面 grp
    public function GetRedPack($rid){
        //从rid获取红包信息，
        $redpack = DBResultToArray($this->SelectDataByQuery($this->TName('tROrder'),
            self::FieldIsValue('rid',$rid)
        ),false,self::SqlField('rid'));
        if(empty($redpack)){
            return RESPONDINSTANCE('70');
        }
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['redpack'] = $redpack;
        return $backMsg;
    }
    //获取用户红包列表,红包记录页面（发出）gurps
    public function GetUserRedPacksSend($uid,$seek,$count){
        //通过uid获取用户发出的红包信息
        $backMsg = RESPONDINSTANCE('0');
        return $backMsg;
    }
    //获取用户红包列表,红包记录页面（收到）gurpr
    public function GetUserRedPacksRecive($uid,$seek,$count){
        //通过uid获取用户收到的红包信息
        $backMsg = RESPONDINSTANCE('0');
        return $backMsg;
    }
    //领取红包 orp
	public function OpenRedPack($uid,$rid){
        //判断用户当日购买份数是否到达5次
        $dayLimit = UserManager::CheckDayBoughtLimit($uid);
        if($dayLimit<=0){
            return RESPONDINSTANCE('18');//用户当日购买量超过上限
        }
        //判断红包是否有效，红包存在未领取份数，对应的梦想互助未结束
        $packLess = self::GetRedPackLessCount($rid);
        if(!$packLess<=0){

            return RESPONDINSTANCE('5');
        }

        //判断用户是否绑定手机号
        if(!UserManager::IdentifyTeleUser($uid)){
            return RESPONDINSTANCE('11');//若未绑定手机即会提示先绑定手机
        }

        //判断用户是否提交过梦想
        $firstDream = DreamManager::UserFirstSubmitedDream($uid)[0];
        if(empty($firstDream)){
            return RESPONDINSTANCE('71');//用户未提交梦想
        }


        //获取红包信息
        $redInfo = DBResultToArray($this->SelectDataByQuery($this->TName('tROrder'),self::FieldIsValue('rid',$rid)),true)[0];

        $unitBill = $redInfo['bill']/$redInfo['rcount'];

        //生成红包购买订单
        self::GenerateRedPackageOrder($uid,$redInfo['pid'],$unitBill,$firstDream['did']);

        //用户当日购买份数+1,参与数量+1
        $DSM = new DreamServersManager();
        UserManager::UpdateUserOrderInfo($uid,$DSM->CountUserJoinedPool($uid),1);


        if($packLess-1<=0) {
            $this->UpdateDataToTableByQuery(
                $this->TName('tUser'),
                ['dayBuy'=>'FINISHED'],
                self::FieldIsValue('uid',$uid));
        }

        //创建编号

        $this->UpdateDataToTableByQuery(
                $this->TName('tROrder'),
                ['state'=>"FINISHED"],
                self::FieldIsValue('rid',$rid)
            );
        //红包订单已领份数+1

        //生成红包领取记录


        //发送通知给用户


        $backMsg = RESPONDINSTANCE('0');
        return $backMsg;
    }
	public function RedPackManage(){

    }
}
?>