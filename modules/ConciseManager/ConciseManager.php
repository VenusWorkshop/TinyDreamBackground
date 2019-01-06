<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);
LIB('db');
LIB('ds');
class ConciseManager extends DBManager{
    public function info()
    {
        //echo self::GenerateConciseServerID();
        return "ConciseManager"; // TODO: Change the autogenerated stub
    }

    //生成服务id号
    public static function GenerateConciseServerID(){
        $CCM = new ConciseManager();
        $count = $CCM->CountTableRow($CCM->TName('dServer'));
        return 'dSrv_'.(1000000000+$count);
    }

	public function ConciseManager(){
		
	}

	public function UserDream($uid){
        $dream = DBResultToArray($this->SelectDataByQuery($this->TName('dServer'),
            self::FieldIsValue('uid',$uid)
            ),true);
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['dream'] = $dream;
        return $backMsg;
    }


	public function SubmitDreamAndRequestPayment($uid,$title,$content,$server,$bill){
        $hid = self::GenerateConciseServerID();
        $this->InsertDataToTable($this->TName('dServer'),
            [
                "hid"=>$hid,
                "uid"=>$uid,
                "title"=>$title,
                "content"=>$content,
                "server"=>$server,
                "bill"=>$bill,
                "ctime"=>PRC_TIME(),
                "ptime"=>0,
                "state"=>'SUBMIT',
            ]
        );
        $DSM = new DreamServersManager();
        return $DSM->WxPay($hid,$bill,$uid);
    }


    public function OrderPaid($uid,$hid){
        $this->UpdateDataToTableByQuery(
            $this->TName('dServer'),
            [
                'state'=>'PAYMENT',
                'ptime'=>PRC_TIME()
            ],
            self::C_And(
                self::FieldIsValue('uid',$uid),
                self::FieldIsValue('hid',$hid)
            )
        );
        return RESPONDINSTANCE('0');
    }
}
?>