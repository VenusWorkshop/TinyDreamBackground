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
}
?>