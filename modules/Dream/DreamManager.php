<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);

LIB('db');
LIB('us');


define("MAX_DREAMS_COUNT",5);

class DreamManager extends DBManager{
    public function info()
    {
        return "梦想模块"; // TODO: Change the autogenerated stub
    }
	public function DreamManager(){
		parent::__construct();
	}

	//生成梦想id号
	public static function GenerateDreamID(){
        $DRM = new DreamManager();
        return 'DR'.(1000000000 + $DRM->CountTableRow($DRM->TName('tDream')));
    }

    //统计用户提交的梦想数（未中奖，未实现即为提交）
    public static function CountSubmitedDream($uid){
        $condition = [
            'uid' => $uid,
            'state'=> 'SUBMIT',
            '_logic' =>'AND'
        ];
        $DRM = new DreamManager();
        return count(DBResultToArray($DRM->SelectDataFromTable($DRM->TName('tDream'),$condition),true));
    }

	//判断用户是否有未中奖的梦想，有即可直接选择，无则调用梦想编辑
	public static function HasSubmitedDream($uid){
        $condition = [
            'uid' => $uid,
            'state'=> 'SUBMIT',
            '_logic' =>'AND'
        ];
        $DRM = new DreamManager();
        return DBResultExist($DRM->SelectDataFromTable($DRM->TName('tDream'),$condition));
    }

    //打开梦想编辑页面
    public function PrepareEditDream($uid){
        if(DreamManager::CountSubmitedDream($uid)>=MAX_DREAMS_COUNT){//若已经提交的梦想数量超过上限（5个）
            return RESPONDINSTANCE('14');
        }
        return RESPONDINSTANCE('0');
    }

    //提交梦想信息
	public function OnEditDream($uid,$title,$content){

        if(UserManager::UserExist($uid)){
            return RESPONDINSTANCE('15');
        }

        if(DreamManager::CountSubmitedDream($uid)>=MAX_DREAMS_COUNT){//若已经提交的梦想数量超过上限（5个）
            return RESPONDINSTANCE('14');
        }

        $dreamArray = [
            "did"=>DreamManager::GenerateDreamID(),
            "uid"=>$uid,
            "dtypeid"=>"Enterprise|Learn|BodyBuild",
            "dserverid"=>"ENTSERVER01|LERSERVER01|BDBSERVER01",
            "title"=>$title,
            "content"=>$content,
            "videourl"=>"",
            "state"=>"SUBMIT",
        ];

        $insresult = $this->InsertDataToTable($this->TName('tDream'),$dreamArray);
        if($insresult){
            $backMsg = RESPONDINSTANCE('0');//梦想提交成功
            if(isset($_REQUEST['action'])){
                $actionList = json_decode($_REQUEST['action']);
                if(isset($actionList['editdream'])){
                    unset($actionList['editdream']);
                }
                $backMsg['action'] = $actionList;
            }

            return $backMsg;
        }else{
            return RESPONDINSTANCE('13');//梦想提交失败
        }
        //return DreamManager::GenerateDreamID();
    }

    //选择梦想信息(必须要有action，因为选择梦想操作只在购买梦想池时需要做，一定为过程性动作)
    public function OnDreamSelected($uid,$did,$action){
        try {
            $actionList = json_decode($action,true);
        }catch (Exception $err){
            return RESPONDINSTANCE('17',$err);
        }
        if(isset($actionList["selectdream"])){
            unset($actionList["selectdream"]);
        }else{
            return RESPONDINSTANCE('17',"未包含selectdream动作");
        }

        $targetDream = $this->GetSingleDream($uid,$did);//获取梦想

        if(isset($actionList["buy"])){
            $actionList["buy"]["dream"] = $targetDream;//设置选择的梦想信息
            $backMsg = RESPONDINSTANCE('0');
            $backMsg['action'] = $actionList;
            return $backMsg;
        }else{
            return RESPONDINSTANCE('17',"未包含buy动作");
        }

    }

    //获取用户的单个梦想
    public function GetSingleDream($uid,$did){
        $condition = [
            'uid' => $uid,
            'did' => $did,
            '_logic' =>'AND'
        ];
        $dreams = $this->SelectDataFromTable($this->TName('tDream'),$condition);
        $dreamArray = DBResultToArray($dreams,true);
        if(DBResultArrayExist($dreamArray)){
            $dreamArray = $dreamArray[0];
        }else{
            $dreamArray = [];
        }
        return $dreamArray;
    }

    //进入梦想列表
    public function OnDreamList($uid){
        $condition = [
            'uid' => $uid,
            '_logic' =>' '
        ];
        $dreams = $this->SelectDataFromTable($this->TName('tDream'),$condition);
        $dreamArray = DBResultToArray($dreams,true);
        if(DBResultArrayExist($dreamArray)){
            $dreamArray = $dreamArray;
        }else{
            $dreamArray = [];
        }

        $backMsg = RESPONDINSTANCE('0');
        $backMsg['dreams'] = $dreamArray;
        return $backMsg;
    }
}
?>