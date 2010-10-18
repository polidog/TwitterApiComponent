class TwitterController extends AppController
{

	var $name = "Twitter";
	var $components = array('TwitterApi');

	function beforeFilter() {
		
		$this->TwitterApi->autoStartAction['oauthStart'] = "/twitter/oauth";
		$this->TwitterApi->autoStartAction['oauthCallback'] = "/twitter/callback";
		$this->TwitterApi->redirectUrl['oauth_denied'] = "/";
		$this->TwitterApi->redirectUrl['oauth_noauthorize'] = "/";
		$this->TwitterApi->redirectUrl['oauth_authorize']  = null;

		
	}

	function oauth() {
	
	}

	function callback() {
		// 認証に成功したら、ユーザー情報を取得する
		$data = $this->TwitterApi->apiStatusesUpdate(array('status'=>'札幌なう','lat'=>'43.039273','long'=>'141.343811'),"post");
		var_dump($data);
		exit("stop");
	}
}
