<?php
/**
 * TwitterApiComponent
 * @author polidog http://www.polidog.jp
 * @version 0.2
 * @copyright polidog
 */
class TwitterApiComponent extends Object
{
	/**
	 * 使用するコンポーネント
	 * @var array
	 * @access private
	 */
	var $components = array('Session','OauthConsumer');
	
	/**
	 * コントローラ
	 * @var AppController 
	 * @access private
	 */
	var $_controller;
	
	/**
	 * OAuth認証時のURL
	 * @var string
	 * @access public
	 */
	var $authorizeUrl = "http://twitter.com/oauth/authorize";
	
	/**
	 * API通信時に使用するURL
	 * @var unknown_type
	 */
	var $apiBaseUrl	= "http://api.twitter.com/1/";
	
	/**
	 * OAuth用のコールバックの指定
	 * @var array
	 * @access public
	 */
	var $autoStartAction = array('oauthStart' =>'/twitter/index' ,'oauthCallback' => '/twitter/callback');
	
	/**
	 * リダイレクト時のURL
	 * @var array
	 * @access public
	 */
	var $redirectUrl = array('oauth_denied' => '/twitter','oauth_noauthorize' => '/twitter','oauth_authorize' => '/twitter');
	
	/**
	 * リダイレクトを許可する、しないを選択する
	 * @var boolean
	 * @access public
	 */
	var $redirect	= true;	
	
	/**
	 * AccessTokenをsessionに保存する際の名前
	 * @var string
	 * @access public
	 */
	var $sessionSaveAccessTokenName = "twitter_access_token";
	
	/**
	 * APIの戻り値をデコードするかのフラグ
	 * @var boolean
	 * @access public
	 */
	var $apidecode	= true;
	
	/**
	 * APIの戻り値をデコードした際に配列に変更する
	 * @var boolean
	 * @access public
	 */
	var $apiassoc	= true;
	
	
	/**
	 * access token
	 * @var object 
	 */
	var $accessToken;
	
	/**
	 * オブジェクト起動時
	 * @param AppController $controller
	 * @param array $settings
	 */
	function initialize(&$controller,$settings=null ) {
		$this->_controller = &$controller;
	}
	
	/**
	 * beforeFilter後の動作
	 * @param AppController $controller
	 */
	function startup(&$controller) {
		
		$oauthStartAction = false;
		if ( isset($this->autoStartAction['oauthStart']) ) {
			$oauthStartAction = $this->autoStartAction['oauthStart'];
		}
		
		$oauthCallbackAction = false;
		if ( isset($this->autoStartAction['oauthCallback']) ) {
			$oauthCallbackAction = $this->autoStartAction['oauthCallback'];
		}
		
		if ( !$oauthCallbackAction && !$oauthStartAction ) {
			return true;
		}
		
		$url = '';
		
		if ( isset($controller->params['url']['url']) ) {
			$url = Router::normalize($controller->params['url']['url']);
		}
		
		switch($url) {
			case $oauthStartAction :
				$this->oauthStart();
				break;
			case $oauthCallbackAction :
				$this->oauthCallback();
				break;
		}
		
	}
	
	/**
	 * OAuth認証を開始する
	 */
	function oauthStart() {
		$requestToken = $this->OauthConsumer->getRequestToken('Twitter', 'http://twitter.com/oauth/request_token', 'http://akj.polidog.jp/callback'); 
		$this->Session->write('twitter_request_token', $requestToken);
		$this->_redirect($this->_getAuthorizeUrl( $requestToken->key ),null,true );
	}
	
	/**
	 * OAuth認証のコールバック
	 */
	function oauthCallback() {
		
		// 拒否された場合
		if ( !empty($this->params['url']['denied']) ) {
			$this->_redirect('oauth_denied','拒否されました');
			return false;
		}
		
		// RequestTokenが取得できない
		$requestToken = $this->Session->read('twitter_request_token');
		if ( is_null($requestToken) ) {
			$this->_redirect('oauth_noauthorize','認証に失敗しました');
			return false;
		}
		
		// accessTokenが取得できない
		$accessToken = $this->OauthConsumer->getAccessToken('Twitter', 'http://twitter.com/oauth/access_token', $requestToken);
		if ( is_null($accessToken) ) {
			$this->_redirect('oauth_noauthorize','認証に失敗しました');
		}		
		
		// accessTokenを保存する
		$this->_saveAccessToken($accessToken);
		
		$this->_redirect('oauth_authorize','認証が完了しました');
		
		
	}
	
	/**
	 * AccessTOkenを保存する
	 * @param $accessToken
	 */
	function _saveAccessToken($accessToken,$key=null,$secret=null) {
		if ( !$this->sessionSaveAccessTokenName ) {
			return false;
		}
		if ( is_null($accessToken) && !is_null($key) && !is_null($secret) ) {
			$accessToken = $this->_craeteOAuthToken($key,$secret);
		}
		if ( is_a($accessToken,"OAuthToken") === false ) {
			return false;
		}
		
		$this->Session->write($this->sessionSaveAccessTokenName, $accessToken);
	}

	/**
	 * AccessTokenを読み込む
	 * @return mixed トークンがある場合は、AccessToken、ない場合はfalseがかえってくる
	 */
	function _readAccessToken() {
		if ( !is_null($this->accessToken) ) {
			return $this->accessToken;
		}
		
		$a = $this->Session->read('twitter_access_token');
		if ( !is_null($a) ) {
			return $a;
		}
		return false;
	}

		
	/**
	 * OAuthトークンを作成する
	 * @param string $key
	 * @param string $secret
	 * @return OAuthToken
	 */
	function _craeteOAuthToken($key,$secret) {
		return new OAuthToken($key, $secret);
	}
	
	
	/**
	 * accessTokenが取得済みか確認する
	 * @return boolean
	 */
	function isAccessToken() {
		$data = $this->Session->read('twitter_access_token');
		if ( is_null($data) ) {
			return false;
		}
		return true;
	}
	
	/**
	 * APIコールメソッド
	 * @param string $path
	 * @param array $getData
	 * @param string $format
	 */
	function api( $path, $param=array(), $method="get", $format="json", $assoc=null ) {
		
		$accessToken = $this->_readAccessToken();
		if ( !$accessToken ) {
			return false;
		}		
		
		$url = $this->apiBaseUrl.$path.".".$format;
		$method = strtolower($method);
		
		$data = $this->OauthConsumer->$method('Twitter', $accessToken->key, $accessToken->secret, $url, $param); 
		
		
		if ( !$this->apidecode ) {
			return $data;
		}

		if ( !is_bool($assoc) ) {
			$assoc = $this->apiassoc;
		}
		
		if ( $format == "json" ) {
			return  json_decode($data,$assoc);
		} else if ( $format == "xml") {
			$xml = simplexml_load_string( $data, 'SimpleXMLElement', LIBXML_NOCDATA );
			if ( $assoc ) {
				$xml = (array)$xml;
			}
			return $xml;
		}
		return $data;
	}
	
	/**
	 * リダイレクト処理を行う
	 * @param string $type	$this->redirectUrlのキーまたはURLを指定する
	 * @param string $flashMessage　リダイレクト先で表示したいメッセージ
	 * @param boolean $forceRedirect 強制リダイレクトフラグ
	 * @access private
	 */
	function _redirect($type,$flashMessage=null,$forceRedirect = false) {
		
		$redirectFlag = $this->redirect;
		if ( $redirectFlag === false && $forceRedirect === true ) {
			$redirectFlag = true;
		}
		
		if ( $redirectFlag ) {
			$url = $type;
			if ( isset($this->redirectUrl[$type]) ) {
				$url = $this->redirectUrl[$type];
				if ( is_null($url) ) {
					return null;
				}
			}
			
			if ( !is_null($flashMessage) ) {
				$this->Session->setFlash($flashMessage);
			}
			
			$this->_controller->redirect($url);
			
		}
		
		if ( $forceRedirect ) {
			
			if ( !is_null($flashMessage) ) {
				$this->Session->setFlash($flashMessage);
			}
			
			$this->_controller->redirect($type);
		}
	}
	
	
	/**
	 * 認証用URIを取得
	 * @param string $requestKey リクエストトークンのキー
	 * @return string
	 * @access private
	 */
	function _getAuthorizeUrl($requestKey = null ) {
		if ( is_null($requestKey) ) {
			return false;
		}
		
		return $this->authorizeUrl."?oauth_token=".$requestKey;
	}
	
	function __call( $method,$args ) {
		
		//APIコールの場合
		$pattern = "/^(api)([a-zA-Z1-9_]*)/i";
		if ( preg_match($pattern, $method, $matches) ) {
			if ( isset($matches[2]) ) {
				
				$apiPath = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '/\\1', $matches[2]));
				$apiArgs = array($apiPath);
				
				if ( is_array($args) ) {
					foreach($args as $arg ) {
						$apiArgs[] = $arg;
					}
				}
				
				
				return call_user_func_array( array( $this, 'api'), $apiArgs);
			}
		}
	}
	
}