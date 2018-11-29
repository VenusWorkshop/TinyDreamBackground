<?php
	header("Content-Type: text/html;charset=utf-8"); 
	LIB($_GET['act']);

	Responds($_GET['act'],(new DreamServersManager()),
    [
        'inf'=>R('info'),//模块信息
        'info'=>R('ListInfo'),
        'buy'=>R('PlaceOrderInADreamPoolStart',['uid','pid']),//购买梦想份数,可更改ACTION)
        'orp'=>R('PlaceOrderInADreamPoolPrepare',['pid']),//准备下单
        'ord'=>R('PlaceOrderInADreamPoolCreate',['action']),//开始下单【修改Action】
        'pay'=>R('PlaceOrderInADreamPoolPay',['uid','oid','bill','pcount','action']),//支付完成【修改action】
        'gap'=>R('GetAllPoolsInfo',['uid']),//玩家获取全部梦想池信息及参与信息
        'oinfo'=>R('GetAllOrdersUser',['uid']),//获取玩家的全部订单
        'precs'=>R('ShowOrdersInPoolStart',['pid']),//进入参与记录页面调用
        'preco'=>R('GetOrdersInPoolByRange',['pid','min','max']),//通过范围获取订单
        'plists'=>R('ShowPoolsInfoStart'),//进入梦想池页面调用,获取梦想池总数
        'plistg'=>R('GetPoolsInfoByRange',['uid','min','max']),//用户获取全部梦想池信息及参与信息,可选参数type(RUNNING,FINISHED,JOIN)
        'cup'=>R('CountUserJoinedPool',['uid']),
    ]);
?>