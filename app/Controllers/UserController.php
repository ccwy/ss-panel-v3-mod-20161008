<?php

namespace App\Controllers;

use App\Services\Auth;
use App\Models\Node,App\Models\TrafficLog,App\Models\InviteCode,App\Models\CheckInLog,App\Models\Ann,App\Models\Speedtest,App\Models\Shop,App\Models\Coupon,App\Models\Bought,App\Models\Ticket;
use App\Services\Config;
use App\Utils\Hash,App\Utils\Tools,App\Utils\Radius,App\Utils\Wecenter,App\Models\RadiusBan,App\Models\DetectLog,App\Models\DetectRule;

use voku\helper\AntiXSS;

use App\Models\User;
use App\Models\Code;
use App\Models\Ip;
use App\Models\LoginIp;
use App\Models\BlockIp;
use App\Models\UnblockIp;
use App\Models\Payback;
use App\Utils\QQWry;
use App\Utils\GA;
use App\Utils\Geetest;
use App\Utils\Telegram;
use App\Services\Mail;






/**
 *  HomeController
 */
class UserController extends BaseController
{

    private $user;

    public function __construct()
    {
        $this->user = Auth::getUser();
    }

    public function index($request, $response, $args)
    {
		/*$Speedtest['Tping']=Speedtest::where("datetime",">",time()-6*3600)->orderBy("telecomping","desc")->get();
		$Speedtest['Uping']=Speedtest::where("datetime",">",time()-6*3600)->orderBy("unicomping","desc")->take(3);
		$Speedtest['Cping']=Speedtest::where("datetime",">",time()-6*3600)->orderBy("cmccping","desc")->take(3);
		$Speedtest['Tspeed']=Speedtest::where("datetime",">",time()-6*3600)->orderBy("telecomeupload","desc")->take(3);
		$Speedtest['Uspeed']=Speedtest::where("datetime",">",time()-6*3600)->orderBy("unicomupload","desc")->take(3);
		$Speedtest['Cspeed']=Speedtest::where("datetime",">",time()-6*3600)->orderBy("cmccupload","desc")->take(3);*/
		
		$nodes=Node::where('sort', 0)->where(
			function ($query) {
				$query->where("node_group","=",$this->user->node_group)
					->orWhere("node_group","=",0);
			}
		)->where("type","1")->where("node_class","<=",$this->user->class)->get();
		$android_add="";
		
		$user = $this->user;
		
		
		$mu_nodes = Node::where('sort',9)->get();
		
		foreach($nodes as $node)
		{
			$ary['server'] = $node->server;
			$ary['server_port'] = $user->port;
			$ary['password'] = $user->passwd;
			$ary['method'] = $node->method;
			if ($node->custom_method) {
				$ary['method'] = $this->user->method;
			}
			
			if(Config::get('enable_rss')=='true'&&$node->custom_rss==1&&!($user->obfs=='plain'&&$user->protocol=='origin'))
			{
				$ssurl = $ary['server']. ":" . $ary['server_port'].":".str_replace("_compatible","",$user->protocol).":".$ary['method'].":".str_replace("_compatible","",$user->obfs).":".Tools::base64_url_encode($ary['password'])."/?obfsparam=".Tools::base64_url_encode($user->obfs_param)."&remarks=".Tools::base64_url_encode($node->name);
				$ssqr_s_new = "ssr://" . Tools::base64_url_encode($ssurl);
				$android_add .= $ssqr_s_new."|";
			}
			else
			{
				$ssurl = $ary['method'] . ":" . $ary['password'] . "@" . $ary['server'] . ":" . $ary['server_port'];
				$ssqr = "ss://" . base64_encode($ssurl);
				$android_add .= $ssqr."|";
			}
			
			
			if($node->custom_rss == 1)
			{
				foreach($mu_nodes as $mu_node)
				{
					$mu_user = User::where('port','=',$mu_node->server)->first();
					$mu_user->obfs_param = $user->getMuMd5().".".$user->id.".".Config::get("mu_suffix");
					
					$ary['server_port'] = $mu_user->port;
					$ary['password'] = $mu_user->passwd;
					$ary['method'] = $node->method;
					if ($node->custom_method) {
						$ary['method'] = $mu_user->method;
					}
					
					$ssurl = $ary['server']. ":" . $ary['server_port'].":".str_replace("_compatible","",$mu_user->protocol).":".$ary['method'].":".str_replace("_compatible","",$mu_user->obfs).":".Tools::base64_url_encode($ary['password'])."/?obfsparam=".Tools::base64_url_encode($mu_user->obfs_param)."&remarks=".Tools::base64_url_encode($node->name." - ".$mu_node->server." 端口单端口多用户");
					$ssqr_s_new = "ssr://" . Tools::base64_url_encode($ssurl);
					$android_add .= $ssqr_s_new."|";
				}
			}
		}
		
		$ios_token = LinkController::GenerateIosCode("smart",0,$this->user->id,0,"smart");
		
		
		$uid = time().rand(1,10000) ;
		if(Config::get('enable_geetest_checkin') == 'true')
		{
			$GtSdk = Geetest::get($uid);
		}
		else
		{
			$GtSdk = null;
		}
		
		$Ann = Ann::orderBy('date', 'desc')->first();
		
		
        return $this->view()->assign('ann',$Ann)->assign('geetest_html',$GtSdk)->assign("ios_token",$ios_token)->assign("android_add",$android_add)->assign('enable_duoshuo',Config::get('enable_duoshuo'))->assign('duoshuo_shortname',Config::get('duoshuo_shortname'))->assign('baseUrl',Config::get('baseUrl'))->display('user/index.tpl');
    }
	
	
	
	public function lookingglass($request, $response, $args)
    {
		
		$Speedtest=Speedtest::where("datetime",">",time()-Config::get('Speedtest_duration')*3600)->orderBy('datetime','desc')->get();
		
        return $this->view()->assign('speedtest',$Speedtest)->assign('hour',Config::get('Speedtest_duration'))->display('user/lookingglass.tpl');
    }
	
	
	
	
	public function code($request, $response, $args)
    {
		
		if(Config::get('enable_paymentwall') == 'true')
		{
			\Paymentwall_Config::getInstance()->set(array(
				'api_type' => \Paymentwall_Config::API_VC,
				'public_key' => Config::get('pmw_publickey'),
				'private_key' => Config::get('pmw_privatekey')
			));
			
			$widget = new \Paymentwall_Widget(
				$this->user->id, // id of the end-user who's making the payment
				Config::get('pmw_widget'),      // widget code, e.g. p1; can be picked inside of your merchant account
				array(),     // array of products - leave blank for Virtual Currency API
				array(
					'email' => $this->user->email,
					'history'=>
						array(
						'registration_date'=>strtotime($this->user->reg_date),
						'registration_ip'=>$this->user->reg_ip,
						'payments_number'=>Code::where('userid','=',$this->user->id)->where('type','=',-1)->count(),
						'membership'=>$this->user->class),
					'customer'=>array(
						'username'=>$this->user->user_name
					)
				) // additional parameters
			);
			
			$pageNum = 1;
			if (isset($request->getQueryParams()["page"])) {
				$pageNum = $request->getQueryParams()["page"];
			}
			$codes = Code::where('userid','=',$this->user->id)->orderBy('id', 'desc')->paginate(15, ['*'], 'page', $pageNum);
			$codes->setPath('/user/code');
			return $this->view()->assign('codes',$codes)->assign('pmw_height',Config::get('pmw_height'))->assign('pmw',$widget->getHtmlCode(array("height"=>Config::get('pmw_height'),"width"=>"100%")))->display('user/code.tpl');
		
		}
		else
		{
			
			$pageNum = 1;
			if (isset($request->getQueryParams()["page"])) {
				$pageNum = $request->getQueryParams()["page"];
			}
			$codes = Code::where('userid','=',$this->user->id)->orderBy('id', 'desc')->paginate(15, ['*'], 'page', $pageNum);
			$codes->setPath('/user/code');
			return $this->view()->assign('codes',$codes)->assign('pmw_height',Config::get('pmw_height'))->assign('pmw','0')->display('user/code.tpl');
		
		}
		

		
	}
	
	
	
	
	public function donate($request, $response, $args)
    {
		if(Config::get('enable_donate') != 'true')
		{
			exit(0);
		}
		
		$pageNum = 1;
		if (isset($request->getQueryParams()["page"])) {
			$pageNum = $request->getQueryParams()["page"];
		}
		$codes = Code::where(
			function ($query) {
				$query->where("type","=",-1)
					->orWhere("type","=",-2);
			}
		)->where("isused",1)->orderBy('id', 'desc')->paginate(15, ['*'], 'page', $pageNum);
		$codes->setPath('/user/donate');
		return $this->view()->assign('codes',$codes)->assign('total_in',Code::where('type',-1)->sum('number'))->assign('total_out',Code::where('type',-2)->sum('number'))->display('user/donate.tpl');
		
	}
	
	
	public function code_check($request, $response, $args)
    {
		$time = $request->getQueryParams()["time"];
		$codes = Code::where('userid','=',$this->user->id)->where('usedatetime','>',date('Y-m-d H:i:s',$time))->first();
		if($codes!=null && strpos($codes->code,"充值") !== FALSE)
		{
			$res['ret'] = 1;
            return $response->getBody()->write(json_encode($res));
		}
		else
		{
			$res['ret'] = 0;
            return $response->getBody()->write(json_encode($res));
		}
		
    }
	
	public function codepost($request, $response, $args)
    {
		$code = $request->getParam('code');
		$user = $this->user;
		

		
		if ( $code == "") {
            $res['ret'] = 0;
            $res['msg'] = "请填好充值码";
            return $response->getBody()->write(json_encode($res));
        }
		
		$codeq=Code::where("code","=",$code)->where("isused","=",0)->first();
        if ( $codeq == null) {
            $res['ret'] = 0;
            $res['msg'] = "此充值码错误";
            return $response->getBody()->write(json_encode($res));
        }
		
		$codeq->isused=1;
		$codeq->usedatetime=date("Y-m-d H:i:s");
		$codeq->userid=$user->id;
		$codeq->save();
		
		if($codeq->type==-1)
		{
			$user->money=($user->money+$codeq->number);
			$user->save();
			
			if($user->ref_by!=""&&$user->ref_by!=0&&$user->ref_by!=NULL)
			{
				$gift_user=User::where("id","=",$user->ref_by)->first();
				$gift_user->money=($gift_user->money+($codeq->number*(Config::get('code_payback')/100)));
				$gift_user->save();
				
				$Payback=new Payback();
				$Payback->total=$codeq->number;
				$Payback->userid=$this->user->id;
				$Payback->ref_by=$this->user->ref_by;
				$Payback->ref_get=$codeq->number*(Config::get('code_payback')/100);
				$Payback->datetime=time();
				$Payback->save();
				
			}
			
			$res['ret'] = 1;
			$res['msg'] = "充值成功，充值的金额为".$codeq->number."元。";
			
			if(Config::get('enable_donate') == 'true')
			{
				if($this->user->is_hide == 1)
				{
					Telegram::Send("姐姐姐姐，一位不愿透露姓名的大老爷给我们捐了 ".$codeq->number." 元呢~");
				}
				else
				{
					Telegram::Send("姐姐姐姐，".$this->user->user_name." 大老爷给我们捐了 ".$codeq->number." 元呢~");
				}
			}
			
			return $response->getBody()->write(json_encode($res));
		}
		
		if($codeq->type==10001)
		{
			$user->transfer_enable=$user->transfer_enable+$codeq->number*1024*1024*1024;
			$user->save();
		}
		
		if($codeq->type==10002)
		{
			if(time()>strtotime($user->expire_in))
			{
				$user->expire_in=date("Y-m-d H:i:s",time()+$codeq->number*86400);
			}
			else
			{
				$user->expire_in=date("Y-m-d H:i:s",strtotime($user->expire_in)+$codeq->number*86400);
			}
			$user->save();
		}
		
		if($codeq->type>=1&&$codeq->type<=10000)
		{
			if($user->class==0||$user->class!=$codeq->type)
			{
				$user->class_expire=date("Y-m-d H:i:s",time());
				$user->save();
			}
			$user->class_expire=date("Y-m-d H:i:s",strtotime($user->class_expire)+$codeq->number*86400);
			$user->class=$codeq->type;
			$user->save();
		}
		
		
    }
	
	
	
	
	public function GaCheck($request, $response, $args)
    {
		$code = $request->getParam('code');
		$user = $this->user;
		

		
		if ( $code == "") {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
		$ga = new GA();
		$rcode = $ga->verifyCode($user->ga_token,$code);
        if (!$rcode) {
            $res['ret'] = 0;
            $res['msg'] = "测试错误";
            return $response->getBody()->write(json_encode($res));
        }
		
		
		$res['ret'] = 1;
		$res['msg'] = "测试成功";
        return $response->getBody()->write(json_encode($res));
    }
	
	
	public function GaSet($request, $response, $args)
    {
		$enable = $request->getParam('enable');
		$user = $this->user;
		

		
		if ( $enable == "") {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
		$user->ga_enable=$enable;
		$user->save();
		
		
		$res['ret'] = 1;
		$res['msg'] = "设置成功";
        return $response->getBody()->write(json_encode($res));
    }
	
	public function ResetPort($request, $response, $args)
    {
		
		$user = $this->user;
		
		$user->port=Tools::getAvPort();
		$user->save();
		
		
		$res['ret'] = 1;
		$res['msg'] = "设置成功，新端口是".$user->port;
        return $response->getBody()->write(json_encode($res));
    }
	
	public function GaReset($request, $response, $args)
	{
		$user = $this->user;
		$ga = new GA();
		$secret = $ga->createSecret();
		
		$user->ga_token=$secret;
		$user->save();
		$newResponse = $response->withStatus(302)->withHeader('Location', '/user/edit');
        return $newResponse;
	}
	
	
	public function nodeAjax($request, $response, $args)
    {
		$id = $args['id'];
		$point_node=Node::find($id);
		$prefix=explode(" - ",$point_node->name);
        return $this->view()->assign('point_node', $point_node)->assign('prefix',$prefix[0])->assign('id', $id)->display('user/nodeajax.tpl');
    }
	

    public function node($request, $response, $args)
    {
        $user = Auth::getUser();
        $nodes = Node::where(
			function ($query) {
				$query->Where("node_group","=",$this->user->node_group)
					->orWhere("node_group","=",0);
			}
		)->where('type', 1)->where("node_class","<=",$this->user->class)->orderBy('name')->get();
		$node_prefix=Array();
		$node_method=Array();
		$a=0;
		$node_order=array();
		$node_alive=array();
		$node_prealive=array();
		$node_heartbeat=Array();
		$node_bandwidth=Array();
		$node_muport=Array();
		
		foreach ($nodes as $node) {
			
			if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0)&&($node->node_bandwidth_limit==0||$node->node_bandwidth<$node->node_bandwidth_limit))
			{
				if($node->sort==9)
				{
					$mu_user=User::where('port','=',$node->server)->first();
					$mu_user->obfs_param=$this->user->getMuMd5().".".$this->user->id.".".Config::get("mu_suffix");
					array_push($node_muport,array('server'=>$node->server,'user'=>$mu_user));
					continue;
				}
				
				
				
				$temp=explode(" - ",$node->name);
				if(!isset($node_prefix[$temp[0]]))
				{
					$node_prefix[$temp[0]]=array();
					$node_order[$temp[0]]=$a;
					$node_alive[$temp[0]]=0;
					
					if(isset($temp[1]))
					{
						$node_method[$temp[0]]=$temp[1];
					}
					else
					{
						$node_method[$temp[0]]="";
					}
					
					$a++;
				}
				
				
				if($node->sort==0||$node->sort==7||$node->sort==8)
				{
					$node_tempalive=$node->getOnlineUserCount();
					$node_prealive[$node->id]=$node_tempalive;
					if($node->node_heartbeat!=0)
					{
						if(time()-$node->node_heartbeat>90)
						{
							$node_heartbeat[$temp[0]]="离线";
						}
						else
						{
							$node_heartbeat[$temp[0]]="在线";
						}
					}
					else
					{
						if(!isset($node_heartbeat[$temp[0]]))
						{
							$node_heartbeat[$temp[0]]="暂无数据";
						}
					}
					
					if($node->node_bandwidth_limit==0)
					{
						$node_bandwidth[$temp[0]]=(int)($node->node_bandwidth/1024/1024/1024)." GB / 不限";
					}
					else
					{
						$node_bandwidth[$temp[0]]=(int)($node->node_bandwidth/1024/1024/1024)." GB / ".(int)($node->node_bandwidth_limit/1024/1024/1024)." GB - ".$node->bandwidthlimit_resetday." 日重置";
					}
					
					if($node_tempalive!="暂无数据")
					{

						$node_alive[$temp[0]]=$node_alive[$temp[0]]+$node_tempalive;

					}
				}
				else
				{
					$node_prealive[$node->id]="暂无数据";
					if(!isset($node_heartbeat[$temp[0]]))
					{
						$node_heartbeat[$temp[0]]="暂无数据";
					}
				}
				
				if(isset($temp[1]))
				{
					if(strpos($node_method[$temp[0]],$temp[1])===FALSE)
					{
						$node_method[$temp[0]]=$node_method[$temp[0]]." ".$temp[1];
					}
				}
				
				
				
				
				
				array_push($node_prefix[$temp[0]],$node);
				
			}
		}
		$node_prefix=(object)$node_prefix;
		$node_order=(object)$node_order;
        return $this->view()->assign('node_method', $node_method)->assign('node_muport', $node_muport)->assign('node_bandwidth',$node_bandwidth)->assign('node_heartbeat',$node_heartbeat)->assign('node_prefix', $node_prefix)->assign('node_prealive', $node_prealive)->assign('node_order', $node_order)->assign('user', $user)->assign('node_alive', $node_alive)->display('user/node.tpl');
    }


    public function nodeInfo($request, $response, $args)
    {
		$user = Auth::getUser();
        $id = $args['id'];
        $mu = $request->getQueryParams()["ismu"];
        $node = Node::find($id);

        if ($node == null) {
			return null;
        }


		switch ($node->sort) { 

			case 0: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0)&&($node->node_bandwidth_limit==0||$node->node_bandwidth<$node->node_bandwidth_limit))
				{
					$ary['server'] = $node->server;
					
					$is_mu = 0;
					
					if($mu == 0)
					{
						$ary['server_port'] = $this->user->port;
						$ary['password'] = $this->user->passwd;
						$ary['method'] = $node->method;
						if ($node->custom_method) {
							$ary['method'] = $this->user->method;
						}
					}
					else
					{
						$mu_user = User::where('port','=',$mu)->first();
						$mu_user->obfs_param = $this->user->getMuMd5().".".$this->user->id.".".Config::get("mu_suffix");
						$user = $mu_user;
						$node->name .= " - ".$mu." 端口单端口多用户";
						$ary['server_port'] = $mu_user->port;
						$ary['password'] = $mu_user->passwd;
						$ary['method'] = $node->method;
						if ($node->custom_method) {
							$ary['method'] = $mu_user->method;
						}
						$is_mu = 1;
					}
					
					$json = json_encode($ary);
					$json_show = json_encode($ary, JSON_PRETTY_PRINT);
					if(Config::get('enable_rss')=='true'&&$node->custom_rss==1&&!($user->obfs=='plain'&&$user->protocol=='origin'))
					{
						
						$ssurl = str_replace("_compatible","",$user->obfs).":".str_replace("_compatible","",$user->protocol).":".$ary['method'] . ":" . $ary['password'] . "@" . $ary['server'] . ":" . $ary['server_port']."/".base64_encode($user->obfs_param);
						$ssqr_s = "ss://" . base64_encode($ssurl);
						$ssurl = $ary['server']. ":" . $ary['server_port'].":".str_replace("_compatible","",$user->protocol).":".$ary['method'].":".str_replace("_compatible","",$user->obfs).":".Tools::base64_url_encode($ary['password'])."/?obfsparam=".Tools::base64_url_encode($user->obfs_param)."&remarks=".Tools::base64_url_encode($node->name);
						$ssqr_s_new = "ssr://" . Tools::base64_url_encode($ssurl);
						$ssurl = $ary['method'] . ":" . $ary['password'] . "@" . $ary['server'] . ":" . $ary['server_port'];
						$ssqr = "ss://" . base64_encode($ssurl);
						
					}
					else
					{
						$ssurl = $ary['method'] . ":" . $ary['password'] . "@" . $ary['server'] . ":" . $ary['server_port'];
						$ssqr = "ss://" . base64_encode($ssurl);
						$ssqr_s = "ss://" . base64_encode($ssurl);
						$ssqr_s_new = "ss://" . base64_encode($ssurl);
					}
					
					$token_1 = LinkController::GenerateSurgeCode($ary['server'],$ary['server_port'],$this->user->id,0,$ary['method']);
					$token_2 = LinkController::GenerateSurgeCode($ary['server'],$ary['server_port'],$this->user->id,1,$ary['method']);

					$surge_base = Config::get('baseUrl') . "/downloads/ProxyBase.conf";
					$surge_proxy = "#!PROXY-OVERRIDE:ProxyBase.conf\n";
					$surge_proxy .= "[Proxy]\n";
					$surge_proxy .= "Proxy = custom," . $ary['server'] . "," . $ary['server_port'] . "," . $ary['method'] . "," . $ary['password'] . "," . Config::get('baseUrl') . "/downloads/SSEncrypt.module";
					return $this->view()->assign('ary', $ary)->assign('mu',$is_mu)->assign('node',$node)->assign('user',$user)->assign('json', $json)->assign('link1',Config::get('baseUrl')."/link/".$token_1)->assign('link2',Config::get('baseUrl')."/link/".$token_2)->assign('json_show', $json_show)->assign('ssqr', $ssqr)->assign('ssqr_s_new',$ssqr_s_new)->assign('ssqr_s', $ssqr_s)->assign('surge_base', $surge_base)->assign('surge_proxy', $surge_proxy)->assign('info_server', $ary['server'])->assign('info_port', $this->user->port)->assign('info_method', $ary['method'])->assign('info_pass', $this->user->passwd)->display('user/nodeinfo.tpl');
				}
			break; 

			case 1: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
						
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$json_show="VPN 信息<br>地址：".$node->server."<br>"."用户名：".$email."<br>密码：".$this->user->passwd."<br>支持方式：".$node->method."<br>备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfovpn.tpl');
				}
			break; 

			case 2: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$json_show="SSH 信息<br>地址：".$node->server."<br>"."用户名：".$email."<br>密码：".$this->user->passwd."<br>支持方式：".$node->method."<br>备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfossh.tpl');

				}

			break; 


			case 3: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{

					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$exp = explode(":",$node->server);
					$token = LinkController::GenerateCode(3,$exp[0],$exp[1],0,$this->user->id);
					$json_show="PAC 信息<br>地址：".Config::get('baseUrl')."/link/".$token."<br>"."用户名：".$email."<br>密码：".$this->user->passwd."<br>支持方式：".$node->method."<br>备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfopac.tpl');

				}

			break; 

			case 4: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$json_show="APN 信息<br>下载地址：".$node->server."<br>"."用户名：".$email."<br>密码：".$this->user->passwd."<br>支持方式：".$node->method."<br>备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfoapn.tpl');

				}

			break; 

			case 5: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					
					$json_show="Anyconnect 信息<br>地址：".$node->server."<br>"."用户名：".$email."<br>密码：".$this->user->passwd."<br>支持方式：".$node->method."<br>备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfoanyconnect.tpl');
				}


			break; 

			case 6: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$exp = explode(":",$node->server);
					
					$token_cmcc = LinkController::GenerateApnCode("cmnet",$exp[0],$exp[1],$this->user->id);
					$token_cnunc = LinkController::GenerateApnCode("3gnet",$exp[0],$exp[1],$this->user->id);
					$token_ctnet = LinkController::GenerateApnCode("ctnet",$exp[0],$exp[1],$this->user->id);
					
					$json_show="APN 文件<br>移动地址：".Config::get('baseUrl')."/link/".$token_cmcc."<br>联通地址：".Config::get('baseUrl')."/link/".$token_cnunc."<br>电信地址：".Config::get('baseUrl')."/link/".$token_ctnet."<br>"."用户名：".$email."<br>密码：".$this->user->passwd."<br>支持方式：".$node->method."<br>备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfoapndownload.tpl');
				}


			break; 

			case 7: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$token = LinkController::GenerateCode(7,$node->server,($this->user->port-20000),0,$this->user->id);
					$json_show="PAC Plus 信息<br>PAC 地址：".Config::get('baseUrl')."/link/".$token."<br>支持方式：".$node->method."<br>备注：".$node->info;


					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfopacplus.tpl');
				}


			break; 

			case 8: 
				if($user->class>=$node->node_class&&($user->node_group==$node->node_group||$node->node_group==0))
				{
					$email=$this->user->email;
					$email=Radius::GetUserName($email);
					$token = LinkController::GenerateCode(8,$node->server,($this->user->port-20000),0,$this->user->id);
					$token_ios = LinkController::GenerateCode(8,$node->server,($this->user->port-20000),1,$this->user->id);
					$json_show="PAC Plus Plus信息<br>PAC 一般地址：".Config::get('baseUrl')."/link/".$token."<br>PAC iOS 地址：".Config::get('baseUrl')."/link/".$token_ios."<br>"."备注：".$node->info;

					return $this->view()->assign('json_show', $json_show)->display('user/nodeinfopacpp.tpl');
				}


			break; 



			default: 
				echo "微笑"; 

		}








    }
	
	public function GetPcConf($request, $response, $args){
        
        $newResponse = $response->withHeader('Content-type', ' application/octet-stream')->withHeader('Content-Disposition', ' attachment; filename=gui-config.json');//->getBody()->write($builder->output());
        $newResponse->getBody()->write(LinkController::GetPcConf(Node::where('sort', 0)->where("type","1")->where(
			function ($query) {
				$query->where("node_group","=",$this->user->node_group)
					->orWhere("node_group","=",0);
			}
		)->where("node_class","<=",$this->user->class)->get(),$this->user));
        return $newResponse;
    }
	
	public function GetIosConf($request, $response, $args){
        
        $newResponse = $response->withHeader('Content-type', ' application/octet-stream')->withHeader('Content-Disposition', ' attachment; filename=allinone.conf');//->getBody()->write($builder->output());
        $newResponse->getBody()->write(LinkController::GetIosConf(Node::where('sort', 0)->where("type","1")->where(
			function ($query) {
				$query->where("node_group","=",$this->user->node_group)
					->orWhere("node_group","=",0);
			}
		)->where("node_class","<=",$this->user->class)->get(),$this->user));
        return $newResponse;
    }
	

    public function profile($request, $response, $args)
    {
		$pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
		$paybacks = Payback::where("ref_by",$this->user->id)->orderBy("datetime","desc")->paginate(15, ['*'], 'page', $pageNum);
		$paybacks->setPath('/user/profile');

		$iplocation = new QQWry();
		
		$totallogin = LoginIp::where('userid', '=',$this->user->id)->where("type","=",0)->orderBy("datetime","desc")->take(10)->get();
		
		$userloginip=array();
		
		foreach($totallogin as $single)
		{
			//if(isset($useripcount[$single->userid]))
			{
				if(!isset($userloginip[$single->ip]))
				{
					//$useripcount[$single->userid]=$useripcount[$single->userid]+1;
					$location=$iplocation->getlocation($single->ip);
					$userloginip[$single->ip]=iconv('gbk', 'utf-8//IGNORE', $location['country'].$location['area']);
				}
			}
		}
		
		
		
        return $this->view()->assign("userloginip",$userloginip)->assign("paybacks",$paybacks)->display('user/profile.tpl');
    }
	
	
	public function announcement($request, $response, $args)
    {
		$Anns = Ann::orderBy('date', 'desc')->get();
		
		
		
        return $this->view()->assign("anns",$Anns)->display('user/announcement.tpl');
    }




    public function edit($request, $response, $args)
    {
		$themes=Tools::getDir(BASE_PATH."/resources/views");
		
		$BIP = BlockIp::where("ip",$_SERVER["REMOTE_ADDR"])->first();
		if($BIP == NULL)
		{
			$Block = "IP: ".$_SERVER["REMOTE_ADDR"]." 没有被封";
			$isBlock = 0;
		}
		else
		{
			$Block = "IP: ".$_SERVER["REMOTE_ADDR"]." 已被封";
			$isBlock = 1;
		}
		
        return $this->view()->assign('user',$this->user)->assign('themes',$themes)->assign('isBlock',$isBlock)->assign('Block',$Block)->display('user/edit.tpl');
    }


    public function invite($request, $response, $args)
    {
		
		$pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
		$codes=InviteCode::where('user_id', $this->user->id)->orderBy("created_at","desc")->paginate(15, ['*'], 'page', $pageNum);
		$codes->setPath('/user/invite');
		
		
		
        return $this->view()->assign('codes', $codes)->display('user/invite.tpl');
    }

    public function doInvite($request, $response, $args)
    {
        $n = $this->user->invite_num;
        if ($n < 1) {
            $res['ret'] = 0;
			$res['msg'] = "失败";
            return $response->getBody()->write(json_encode($res));
        }
        for ($i = 0; $i < $n; $i++) {
            $char = Tools::genRandomChar(32);
            $code = new InviteCode();
            $code->code = $char;
            $code->user_id = $this->user->id;
            $code->save();
        }
        $this->user->invite_num = 0;
        $this->user->save();
        $res['ret'] = 1;
		$res['msg'] = "生成成功。";
        return $this->echoJson($response, $res);
    }

    public function sys()
    {
        return $this->view()->assign('ana', "")->display('user/sys.tpl');
    }

    public function updatePassword($request, $response, $args)
    {
        $oldpwd = $request->getParam('oldpwd');
        $pwd = $request->getParam('pwd');
        $repwd = $request->getParam('repwd');
        $user = $this->user;
        if (!Hash::checkPassword($user->pass, $oldpwd)) {
            $res['ret'] = 0;
            $res['msg'] = "旧密码错误";
            return $response->getBody()->write(json_encode($res));
        }
        if ($pwd != $repwd) {
            $res['ret'] = 0;
            $res['msg'] = "两次输入不符合";
            return $response->getBody()->write(json_encode($res));
        }

        if (strlen($pwd) < 8) {
            $res['ret'] = 0;
            $res['msg'] = "密码太短啦";
            return $response->getBody()->write(json_encode($res));
        }
        $hashPwd = Hash::passwordHash($pwd);
        $user->pass = $hashPwd;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "修改成功";
        return $this->echoJson($response, $res);
    }
	
	public function updateHide($request, $response, $args)
    {
        $hide = $request->getParam('hide');
        $user = $this->user;
        $user->is_hide = $hide;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "修改成功";
        return $this->echoJson($response, $res);
    }
	
	public function Unblock($request, $response, $args)
    {
        $user = $this->user;
		$BIP = BlockIp::where("ip",$_SERVER["REMOTE_ADDR"])->first();
        if ($BIP == NULL) {
            $res['ret'] = 0;
            $res['msg'] = "没有被封";
            return $response->getBody()->write(json_encode($res));
        }
		
		$BIP = BlockIp::where("ip",$_SERVER["REMOTE_ADDR"])->get();
		foreach($BIP as $bi)
		{
			$bi->delete();
		
			$UIP = new UnblockIp();
			$UIP->userid = $user->id;
			$UIP->ip = $_SERVER["REMOTE_ADDR"];
			$UIP->datetime = time();
			$UIP->save();
		}
		
        

		
        $res['ret'] = 1;
        $res['msg'] = "解封 ".$_SERVER["REMOTE_ADDR"]." 成功";
        return $this->echoJson($response, $res);
    }
	
	public function shop($request, $response, $args){
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
		$shops = Shop::where("status",1)->paginate(15, ['*'], 'page', $pageNum);
		$shops->setPath('/user/shop');
		
        return $this->view()->assign('shops',$shops)->display('user/shop.tpl');
    }
	
	public function CouponCheck($request, $response, $args)
    {
        $coupon = $request->getParam('coupon');
        $shop = $request->getParam('shop');
		
		$shop=Shop::where("id",$shop)->where("status",1)->first();
		
		if($shop==null)
		{
			$res['ret'] = 0;
            $res['msg'] = "非法请求";
            return $response->getBody()->write(json_encode($res));
		}
		
		if($coupon=="")
		{
			$res['ret'] = 1;
			$res['name'] = $shop->name;
			$res['credit'] = "0 %";
			$res['total'] = $shop->price."元";
			return $response->getBody()->write(json_encode($res));
		}
		
		$coupon=Coupon::where("code",$coupon)->first();
		
		if($coupon==null)
		{
			$res['ret'] = 0;
            $res['msg'] = "优惠码无效";
            return $response->getBody()->write(json_encode($res));
		}
		
		if($coupon->order($shop->id)==FALSE)
		{
			$res['ret'] = 0;
            $res['msg'] = "此优惠码不可用于此商品";
            return $response->getBody()->write(json_encode($res));
		}
		
		$res['ret'] = 1;
		$res['name'] = $shop->name;
		$res['credit'] = $coupon->credit." %";
		$res['total'] = $shop->price*((100-$coupon->credit)/100)."元";
		
		return $response->getBody()->write(json_encode($res));
    }
	
	public function buy($request, $response, $args)
    {
        $coupon = $request->getParam('coupon');
		$code = $coupon;
        $shop = $request->getParam('shop');
		
		$autorenew = $request->getParam('autorenew');
		
		$shop=Shop::where("id",$shop)->where("status",1)->first();
		
		if($shop==null)
		{
			$res['ret'] = 0;
            $res['msg'] = "非法请求";
            return $response->getBody()->write(json_encode($res));
		}
		
		if($coupon=="")
		{
			$credit=0;
		}
		else
		{
		
			$coupon=Coupon::where("code",$coupon)->first();
			
			if($coupon==null)
			{
				$credit=0;
			}
			else
			{
				if($coupon->onetime==1)
				{
					$onetime=true;
				}
				
				$credit=$coupon->credit;
			}
			
			if($coupon->order($shop->id)==FALSE)
			{
				$res['ret'] = 0;
				$res['msg'] = "此优惠码不可用于此商品";
				return $response->getBody()->write(json_encode($res));
			}
			
			if($coupon->expire<time())
			{
				$res['ret'] = 0;
				$res['msg'] = "此优惠码已过期";
				return $response->getBody()->write(json_encode($res));
			}
		}
		
		$price=$shop->price*((100-$credit)/100);
		$user=$this->user;
		if($user->money<$price)
		{
			$res['ret'] = 0;
			$res['msg'] = "余额不足";
			return $response->getBody()->write(json_encode($res));
		}
		
		$user->money=$user->money-$price;
		$user->save();
		
		$bought=new Bought();
		$bought->userid=$user->id;
		$bought->shopid=$shop->id;
		$bought->datetime=time();
		if($autorenew==0||$shop->auto_renew==0)
		{
			$bought->renew=0;
		}
		else
		{
			$bought->renew=time()+$shop->auto_renew*86400;
		}
		
		$bought->coupon=$code;
		
		
		if(isset($onetime))
		{
			$price=$shop->price;
		}
		$bought->price=$price;
		$bought->save();
		
		$shop->buy($user);
		
		$res['ret'] = 1;
        $res['msg'] = "购买成功";
		
		return $response->getBody()->write(json_encode($res));
    }
	
	public function bought($request, $response, $args){
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
		$shops = Bought::where("userid",$this->user->id)->orderBy("id","desc")->paginate(15, ['*'], 'page', $pageNum);
		$shops->setPath('/user/bought');
		
        return $this->view()->assign('shops',$shops)->display('user/bought.tpl');
    }
	
	public function deleteBoughtGet($request, $response, $args){
        $id = $request->getParam('id');
        $shop = Bought::where("id",$id)->where("userid",$this->user->id)->first();
		
		if($shop==null)
		{
			$rs['ret'] = 0;
            $rs['msg'] = "退订失败，订单不存在。";
            return $response->getBody()->write(json_encode($rs));
		}
		
		if($this->user->id==$shop->userid)
		{
			$shop->renew=0;
		}
		
        if(!$shop->save()){
            $rs['ret'] = 0;
            $rs['msg'] = "退订失败";
            return $response->getBody()->write(json_encode($rs));
        }
        $rs['ret'] = 1;
        $rs['msg'] = "退订成功";
		return $response->getBody()->write(json_encode($rs));
    }
	
	
	public function ticket($request, $response, $args){
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
		$tickets = Ticket::where("userid",$this->user->id)->where("rootid",0)->orderBy("datetime","desc")->paginate(15, ['*'], 'page', $pageNum);
		$tickets->setPath('/user/ticket');
		
        return $this->view()->assign('tickets',$tickets)->display('user/ticket.tpl');
    }
	
	public function ticket_create($request, $response, $args){
		
        return $this->view()->display('user/ticket_create.tpl');
    }
	
	public function ticket_add($request, $response, $args){
        $title = $request->getParam('title');
		$content = $request->getParam('content');
		
		
		if($title==""||$content=="")
		{
			$res['ret'] = 0;
			$res['msg'] = "请填全";
			return $this->echoJson($response, $res);
		}
		
		if(strpos($content,"admin")!=FALSE||strpos($content,"user")!=FALSE)
		{
			$res['ret'] = 0;
			$res['msg'] = "请求中有不正当的词语。";
			return $this->echoJson($response, $res);
		}
		
        
        $ticket=new Ticket();
		
		$antiXss = new AntiXSS();
		
		$ticket->title=$antiXss->xss_clean($title);
		$ticket->content=$antiXss->xss_clean($content);
		$ticket->rootid=0;
		$ticket->userid=$this->user->id;
		$ticket->datetime=time();
		$ticket->save();
		
		$adminUser = User::where("is_admin","=","1")->get();
		foreach($adminUser as $user)
		{
			$subject = Config::get('appName')."-新工单被开启";
			$to = $user->email;
			$text = "管理员您好，有人开启了新的工单，请您及时处理。。" ;
			try {
				Mail::send($to, $subject, 'news/warn.tpl', [
					"user" => $user,"text" => $text
				], [
				]);
			} catch (Exception $e) {
				echo $e->getMessage();
			}
		}

        $res['ret'] = 1;
        $res['msg'] = "提交成功";
        return $this->echoJson($response, $res);
    }
	
	public function ticket_update($request, $response, $args){
        $id = $args['id'];
		$content = $request->getParam('content');
		$status = $request->getParam('status');
		
		if($content==""||$status=="")
		{
			$res['ret'] = 0;
			$res['msg'] = "请填全";
			return $this->echoJson($response, $res);
		}
		
		if(strpos($content,"admin")!=FALSE||strpos($content,"user")!=FALSE)
		{
			$res['ret'] = 0;
			$res['msg'] = "请求中有不正当的词语。";
			return $this->echoJson($response, $res);
		}
		
        
        $ticket_main=Ticket::where("id","=",$id)->where("rootid","=",0)->first();
		if($ticket_main->userid!=$this->user->id)
		{
			$newResponse = $response->withStatus(302)->withHeader('Location', '/user/ticket');
			return $newResponse;
		}
		
		if($status==1&&$ticket_main->status!=$status)
		{
			$adminUser = User::where("is_admin","=","1")->get();
			foreach($adminUser as $user)
			{
				$subject = Config::get('appName')."-工单被重新开启";
				$to = $user->email;
				$text = "管理员您好，有人重新开启了<a href=\"".Config::get('baseUrl')."/admin/ticket/".$ticket_main->id."/view\">工单</a>，请您及时处理。" ;
				try {
					Mail::send($to, $subject, 'news/warn.tpl', [
						"user" => $user,"text" => $text
					], [
					]);
				} catch (Exception $e) {
					echo $e->getMessage();
				}
			}
		}
		else
		{
			$adminUser = User::where("is_admin","=","1")->get();
			foreach($adminUser as $user)
			{
				$subject = Config::get('appName')."-工单被回复";
				$to = $user->email;
				$text = "管理员您好，有人回复了<a href=\"".Config::get('baseUrl')."/admin/ticket/".$ticket_main->id."/view\">工单</a>，请您及时处理。" ;
				try {
					Mail::send($to, $subject, 'news/warn.tpl', [
						"user" => $user,"text" => $text
					], [
					]);
				} catch (Exception $e) {
					echo $e->getMessage();
				}
			}
		}
		
		$antiXss = new AntiXSS();
		
		$ticket=new Ticket();
		$ticket->title=$antiXss->xss_clean($ticket_main->title);
		$ticket->content=$antiXss->xss_clean($content);
		$ticket->rootid=$ticket_main->id;
		$ticket->userid=$this->user->id;
		$ticket->datetime=time();
		$ticket_main->status=$status;
		
		$ticket_main->save();
		$ticket->save();
		
		
		

        $res['ret'] = 1;
        $res['msg'] = "提交成功";
        return $this->echoJson($response, $res);
    }
	
	public function ticket_view($request, $response, $args){
		$id = $args['id'];
		$ticket_main=Ticket::where("id","=",$id)->where("rootid","=",0)->first();
		if($ticket_main->userid!=$this->user->id)
		{
			$newResponse = $response->withStatus(302)->withHeader('Location', '/user/ticket');
			return $newResponse;
		}
		
		$pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
		
		
		$ticketset=Ticket::where("id",$id)->orWhere("rootid","=",$id)->orderBy("datetime","desc")->paginate(5, ['*'], 'page', $pageNum);
		$ticketset->setPath('/user/ticket/'.$id."/view");
		
		
		return $this->view()->assign('ticketset',$ticketset)->assign("id",$id)->display('user/ticket_view.tpl');
    }
	
	
	public function updateWechat($request, $response, $args)
    {
		$type = $request->getParam('imtype');
        $wechat = $request->getParam('wechat');
        
        $user = $this->user;
		
		if ( $wechat == ""||$type == "") {
            $res['ret'] = 0;
            $res['msg'] = "请填好";
            return $response->getBody()->write(json_encode($res));
        }
		
		$user1 = User::where('im_value',$wechat)->where('im_type',$type)->first();
        if ( $user1 != null) {
            $res['ret'] = 0;
            $res['msg'] = "此联络方式已经被注册了";
            return $response->getBody()->write(json_encode($res));
        }
        
		$user->im_type = $type;
		$antiXss = new AntiXSS();
        $user->im_value = $antiXss->xss_clean($wechat);
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "修改成功";
        return $this->echoJson($response, $res);
    }
	
	
	public function updateRss($request, $response, $args)
    {
		$protocol = $request->getParam('protocol');
		$obfs = $request->getParam('obfs');
        
        $user = $this->user;
		
		if ( $obfs == ""||$protocol == "") {
            $res['ret'] = 0;
            $res['msg'] = "请填好";
            return $response->getBody()->write(json_encode($res));
        }
		
		if (!Tools::is_validate($obfs)) {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
		if (!Tools::is_validate($protocol)) {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
        $antiXss = new AntiXSS();
		
		$user->protocol = $antiXss->xss_clean($protocol);
        $user->obfs = $antiXss->xss_clean($obfs);
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "修改成功";
        return $this->echoJson($response, $res);
    }
	
	public function updateTheme($request, $response, $args)
    {
        $theme = $request->getParam('theme');
        
        $user = $this->user;
		
		if ( $theme == "") {
            $res['ret'] = 0;
            $res['msg'] = "???";
            return $response->getBody()->write(json_encode($res));
        }
		
        
        $user->theme = filter_var($theme, FILTER_SANITIZE_STRING);
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "ok";
        return $this->echoJson($response, $res);
    }
	
	
	public function updateMail($request, $response, $args)
    {
        $mail = $request->getParam('mail');
        
        $user = $this->user;
		
		if ( !($mail == "1"||$mail == "0")) {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
        
        $user->sendDailyMail = $mail;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "ok";
        return $this->echoJson($response, $res);
    }
	
	public function PacSet($request, $response, $args)
    {
        $pac = $request->getParam('pac');
        
        $user = $this->user;
		
		if ($pac == "") {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
        
        $user->pac = $pac;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "ok";
        return $this->echoJson($response, $res);
    }


    public function updateSsPwd($request, $response, $args)
    {
        $user = Auth::getUser();
        $pwd = $request->getParam('sspwd');
        
        if ($pwd == "") {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
		
	if (!Tools::is_validate($pwd)) {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
        
        $user->updateSsPwd($pwd);
        $res['ret'] = 1;


        Radius::Add($user,$pwd);




        return $this->echoJson($response, $res);
    }

    public function updateMethod($request, $response, $args)
    {
        $user = Auth::getUser();
        $method = $request->getParam('method');
        $method = strtolower($method);
        
        if ($method == "") {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }

	if (!Tools::is_validate($method)) {
            $res['ret'] = 0;
            $res['msg'] = "悟空别闹";
            return $response->getBody()->write(json_encode($res));
        }
        
        $user->updateMethod($method);
        $res['ret'] = 1;
        return $this->echoJson($response, $res);
    }

    public function logout($request, $response, $args)
    {
        Auth::logout();
        $newResponse = $response->withStatus(302)->withHeader('Location', '/auth/login');
        return $newResponse;
    }

    public function doCheckIn($request, $response, $args)
    {
		if(Config::get('enable_geetest_checkin') == 'true')
		{
			$ret = Geetest::verify($request->getParam('geetest_challenge'),$request->getParam('geetest_validate'),$request->getParam('geetest_seccode'));
			if (!$ret) {
				$res['ret'] = 0;
				$res['msg'] = "系统无法接受您的验证结果，请刷新页面后重试。";
				return $response->getBody()->write(json_encode($res));
			}
		}
		
        if (!$this->user->isAbleToCheckin()) {
            $res['msg'] = "您似乎已经签到过了...";
            $res['ret'] = 1;
            return $response->getBody()->write(json_encode($res));
        }
        $traffic = rand(Config::get('checkinMin'), Config::get('checkinMax'));
        $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic);
        $this->user->last_check_in_time = time();
        $this->user->save();
        if ($traffic>0) {
			$res['msg'] = sprintf("运气不错，恭喜你获得了 %d MB流量！", $traffic);
		}
               else if ($traffic==0) {
			$res['msg'] = sprintf("恭喜你抽中 %d MB流量大奖！系统已自动为您充值了500G流量！", $traffic);
        $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=512000);
        $this->user->last_check_in_time = time();
        $this->user->save();
		}		
               else {
                      $traffic = 0-$traffic;
			$res['msg'] = sprintf("运气不佳，你减少了 %d MB流量，洗把脸再来吧.", $traffic);
		}	
        $res['ret'] = 1;
        return $this->echoJson($response, $res);
    }

	
	public function loin($request, $response, $args)
    {			
        if (!$this->user->isAbleToloin()) {
            $res['msg'] = "请刷新页面再试。。";
            $res['ret'] = 1;
            return $response->getBody()->write(json_encode($res));
        }
				
		$traffic = rand(Config::get('loMin'), Config::get('loMax'));
		
		if ($traffic <= 0) {
	         $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic);          
                  $this->user->save();	
	           $traffic = 0-$traffic;
		   $res['msg'] = sprintf("运气不佳，白花了  %d MB 流量！适可而止吧，骚年！游戏重在娱乐！", $traffic);			
 		}

        if ($traffic==511) {
			$res['msg'] = sprintf("恭喜你抽中  %d  我要要！奖励 5G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=5120);        
            $this->user->save();			
 		}	
        if ($traffic==520) {
			$res['msg'] = sprintf("恭喜你抽中  %d  我爱你！奖励 6G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=6144);            
            $this->user->save();			
 		}	
        if ($traffic==521) {
			$res['msg'] = sprintf("恭喜你抽中  %d  爱心数字！奖励 6G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=6144);          
            $this->user->save();
 		}					
        if ($traffic==555) {
			$res['msg'] = sprintf("恭喜你抽中  %d  中间数字！奖励 6G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=6144);          
            $this->user->save();
 		}			
        if ($traffic==566) {
			$res['msg'] = sprintf("恭喜你抽中  %d  我6 6！奖励 5G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=5120);          
            $this->user->save();
 		}			
        if ($traffic==588) {
			$res['msg'] = sprintf("恭喜你抽中  %d  我发发！奖励 6G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=6144);           
            $this->user->save();
 		}			
        if ($traffic==599) {
			$res['msg'] = sprintf("恭喜你抽中  %d  我救救！奖励 7G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=7168);
            $this->user->save();
 		}			
        if ($traffic==600) {
			$res['msg'] = sprintf("恭喜你抽中  %d  这个末尾数字！奖励 8G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=8192);            
            $this->user->save();
 		}			
        if ($traffic==518) {
			$res['msg'] = sprintf("恭喜你抽中  %d  我要发！奖励 6G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=6144);           
            $this->user->save();
 		}			
        if ($traffic==514) {
			$res['msg'] = sprintf("你真倒霉，抽中  %d  我要死！扣除 5G 流量！", $traffic);
            $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic=-5120);           
            $this->user->save();
 		}
			$res['ret'] = 1;		
        return $this->echoJson($response, $res);
    }
	
	
    public function kill($request, $response, $args)
    {
        return $this->view()->display('user/kill.tpl');
    }

    public function handleKill($request, $response, $args)
    {
        $user = Auth::getUser();
		
		$email=$user->email;
		
			
        $passwd = $request->getParam('passwd');
        // check passwd
        $res = array();
        if (!Hash::checkPassword($user->pass, $passwd)) {
            $res['ret'] = 0;
            $res['msg'] = " 密码错误";
            return $this->echoJson($response, $res);
        }
		Radius::Delete($email);
		
		RadiusBan::where('userid','=',$user->id)->delete();
		
		Wecenter::Delete($email);

        Auth::logout();
        $user->delete();
        $res['ret'] = 1;
        $res['msg'] = "GG!您的帐号已经从我们的系统中删除.";
        return $this->echoJson($response, $res);
    }

    public function trafficLog($request, $response, $args){
        $traffic=TrafficLog::where('user_id',$this->user->id)->where("log_time",">",(time()-3*86400))->orderBy('id', 'desc')->get();
        return $this->view()->assign('logs', $traffic)->display('user/trafficlog.tpl');
    }




    	public function detect_index($request, $response, $args){
		$pageNum = 1;
		if (isset($request->getQueryParams()["page"])) {
			$pageNum = $request->getQueryParams()["page"];
		}
		$logs = DetectRule::paginate(15, ['*'], 'page', $pageNum);
		$logs->setPath('/user/detect');
		return $this->view()->assign('rules',$logs)->display('user/detect_index.tpl');
	}

	public function detect_log($request, $response, $args){
		$pageNum = 1;
		if (isset($request->getQueryParams()["page"])) {
			$pageNum = $request->getQueryParams()["page"];
		}
		$logs = DetectLog::orderBy('id', 'desc')->where('user_id',$this->user->id)->paginate(15, ['*'], 'page', $pageNum);
		$logs->setPath('/user/detect/log');
		return $this->view()->assign('logs',$logs)->display('user/detect_log.tpl');
	}
}
