<?php
//引用此页面前需先引用conf.php
error_reporting(E_ALL ^ E_DEPRECATED);

LIB('db');
define('NOTICE_BUY','buy');
define('NOTICE_MISS','miss');
define('NOTICE_GET','get');
define('NOTICE_FAIL','fail');
define('NOTICE_PAID','paid');
class NoticeManager extends DBManager {
    public function info()
    {
        //self::NoticeMiss(['a01','a02','a03','a04'],"[互助]");
        return "NoticeManager"; // TODO: Change the autogenerated stub
    }
    public function NoticeManager(){

    }

    /*
     *
     *  1、购买通知
        2、未中奖通知
        3、中奖通知
        4、实名认证未审核通过通知
        5、打款通知
     * */
    public $template = [
        'buy'=>[
            'pars'=>['ptitle','lids'],
            'context'=>'感谢您参与了{ptitle}，您本期的编号为{lids}。',
            'action'=>[]
        ],
        'miss'=>[
            'pars'=>['ptitle'],
            'context'=>'很遗憾，您参与的{ptitle}未成为幸运者。',
            'action'=>[]
        ],
        'get'=>[
            'pars'=>['ptitle','lid'],
            'context'=>'恭喜您！您参与的{ptitle}成为幸运者，幸运编号为{lid}。',
            'action'=>[]
        ],
        'fail'=>[
            'pars'=>[],
            'context'=>'很抱歉，您提交的资料（小梦想/认证资料）审核未通过，请您重新完善提交',
            'action'=>[]
        ],
        'paid'=>[
            'pars'=>[],
            'context'=>'温馨提醒，您的梦想互助金已转款，注意查收，如有问题请联系客服。',
            'action'=>'view'
        ]
    ];


    public static function GenerateNoticeID($delta=0){
        //生成消息id号
        $NOM = new NoticeManager();
        return 'MSG'.(1000000000 + $NOM->CountTableRow($NOM->TName('tNotice'))+$delta);
    }


    //群发未中奖消息
    public static function NoticeMiss($uids,$ptitle){
        $NOM = new NoticeManager();
        $notice = $NOM->template[NOTICE_MISS];
        $content = $NOM->BuildContent($notice['context'],NOTICE_MISS,["ptitle"=>$ptitle]);

        $infos = [];
        $seek = 0;
        $timeStamp = time();
        foreach ($uids as $uid) {
            $infos[$seek] = [];
            $infos[$seek][0] = self::GenerateNoticeID($seek);
            $infos[$seek][1] = $uid;
            $infos[$seek][2] = $content;
            $infos[$seek][3] = $NOM->template[NOTICE_MISS]['action'];
            $infos[$seek][4] = $timeStamp;
            $infos[$seek][5] = 'UNREAD';
            $seek++;
        }
        $NOM->InsertDatasToTable($NOM->TName('tNotice'),[
            "key" => ["nid","uid","content","action","ptime","state"],
            "values" => $infos
        ]);
    }

    public static function CreateAction($type,$pars){
        $action = [];
        $action['type'] = $type;
        foreach ($pars as $key => $value) {
            $action[$key] = $value;
        }
        return str_replace('"',"'",json_encode($action));
    }

    //创建通知
    public static function CreateNotice($uid,$noticeKey,$pars,$action='{"type":view}'){
        $NOM = new NoticeManager();
        $NOM->NoticeUser($uid,$noticeKey,$pars,$action);
    }


    public function BuildContent($formate,$noticeKey,$pars){
        $content = $formate;
        $parlist = $this->template[$noticeKey]['pars'];
        foreach ($parlist as $p) {
            $current = '{'.$p.'}';
            $content = str_replace($current,$pars[$p],$content);
        }
        return $content;
    }

    public function NoticeCount($uid){
        $result = $this->SelectDataByQuery($this->TName('tNotice'),
            self::C_And(
                self::FieldIsValue('uid',$uid),
                self::FieldIsValue('state','UNREAD')
            )
            ,false,'COUNT(*)');
        $result = DBResultToArray($result,true)[0]['COUNT(*)'];
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['ncount'] = $result;
        return $backMsg;
    }

    public function ReadNotice($nid){
        $this->UpdateDataToTableByQuery($this->TName('tNotice'),['state'=>'READ'],
            self::C_And(
                self::FieldIsValue('nid',$nid),
                self::FieldIsValue('state','UNREAD')
            )
        );
        return RESPONDINSTANCE('0');
    }

    public function GetUserUnReadNotice($uid,$seek,$count){
        $result = $this->SelectDataByQuery($this->TName('tNotice'),
            self::Limit(
                self::C_And(
                    self::FieldIsValue('uid',$uid),
                    self::FieldIsValue('state','UNREAD')
                ),
                $seek,
                $count
            )
        );
        $result = DBResultToArray($result,true);
        $backMsg = RESPONDINSTANCE('0');
        $backMsg['msgs'] = $result;
        return $backMsg;
    }

    public function TestAction(){
        echo  NoticeManager::CreateAction(
            'buy',
            [
                'pid'=>'20190102'
            ]
        );
    }


	public function NoticeUser($uid,$noticeKey,$pars,$action="view"){
        if(!isset($this->template[$noticeKey])){
	        return;//没有通知模板
        }
        $notice = $this->template[$noticeKey];
	    $parinfo = $notice['pars'];
	    foreach ($parinfo as $var){
            if(!isset($pars[$var])){
                return;//参数格式有误
            }
        }
        $content = $this->BuildContent($notice['context'],$noticeKey,$pars);

	    $this->InsertDataToTable($this->TName('tNotice'),
            [
                "nid"=>self::GenerateNoticeID(),
                "uid"=>$uid,
                "content"=>$content,
                "action"=>$action,
                "ptime"=>time(),
                "state"=>'UNREAD',
            ]);
			file_put_contents('notice.txt',json_encode([
                "nid"=>self::GenerateNoticeID(),
                "uid"=>$uid,
                "content"=>$content,
                "action"=>$action,
                "ptime"=>time(),
                "state"=>'UNREAD',
            ]));
    }


}
?>