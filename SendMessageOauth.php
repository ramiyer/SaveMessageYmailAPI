<?php

//php SendMessageOAuth.php <consumerkey> <consumer-secret> <from> <to>

//Libraries used for accessing YMail api's.
require_once 'OAuth.php';
require_once 'JsonRpcClient.inc';
$OAuthConsumerKey = $argv[1];
$OAuthConsumerSecret = $argv[2];

//Endpoint for Yahoo mail WSDL
$endPoint = 'http://mail.yahooapis.com/ws/mail/v1.1';
//OAuth Endpoint
$OAuthEndPoint = 'https://api.login.yahoo.com/oauth/v2';
$signature = new OAuthSignatureMethod_HMAC_SHA1();

// 1) Get Request Token
$request = new OAuthRequest('GET', "$OAuthEndPoint/get_request_token", array(
	'oauth_nonce'=>mt_rand(),
	'oauth_timestamp'=>time(),
	'oauth_version'=>'1.0',
	'oauth_signature_method'=>'HMAC-SHA1', //'HMAC-SHA1'
	'oauth_consumer_key'=>$OAuthConsumerKey,
	'oauth_callback'=>'oob'));

//For HMAC-SHA1 signature
$url = $request->to_url()."&oauth_signature=".urlencode($signature->build_signature( $request, new OAuthConsumer('', $OAuthConsumerSecret), NULL));

//Curl related functions
$ch = curl_init();
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt( $ch, CURLOPT_URL, $url );
$resp = curl_exec( $ch );
curl_close($ch);
parse_str($resp,$tokens);
$oauth_token = $tokens['oauth_token'];
$oauth_token_secret = $tokens['oauth_token_secret'];
if (!$oauth_token || !$oauth_token_secret) {
	throw new Exception($resp);	
}
// 2) Get User Authorization
echo " Open this Url in your browser ->> $OAuthEndPoint/request_auth?oauth_token=$oauth_token \n";
echo " This should be provided to end users of your application.End users should provide their 
'Username' and 'Password' and sign-in which means they authorize your app. On successful login the end users will see a code in the page \n";
echo " This code is the oauth_token which Yahoo returns to your app \n";
echo " Enter the code here: ";
$oauth_verifier = trim(fgets(STDIN));

// 3) Get Access Token
$request = new OAuthRequest('GET', "$OAuthEndPoint/get_token", array(
	'oauth_nonce'=>mt_rand(),
	'oauth_timestamp'=>time(),
	'oauth_version'=>'1.0',
	'oauth_signature_method'=>'PLAINTEXT', //'HMAC-SHA1'
	'oauth_consumer_key'=>$OAuthConsumerKey,
	'oauth_token'=>$oauth_token,
	'oauth_verifier'=>$oauth_verifier));
$url = $request->to_url()."&oauth_signature=$OAuthConsumerSecret%26$oauth_token_secret";
$ch = curl_init();
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt( $ch, CURLOPT_URL, $url );
$resp = curl_exec( $ch );
curl_close($ch);
unset($oauth_token);
unset($oauth_token_secret);
parse_str($resp);
if (!$oauth_token || !$oauth_token_secret) {
	throw new Exception($resp);	
}

$request = new OAuthRequest('POST', "$endPoint/jsonrpc", array(
	'oauth_nonce'=>mt_rand(),
	'oauth_timestamp'=>time(),
	'oauth_version'=>'1.0',
	'oauth_signature_method'=>'HMAC-SHA1',
	'oauth_consumer_key'=>$OAuthConsumerKey,
	'oauth_token'=>$oauth_token
	));
$request->sign_request($signature, new OAuthConsumer('', $OAuthConsumerSecret), new OAuthToken('', $oauth_token_secret));
$oauthHeaderForJson = $request->to_header();
	
$jsonClient = new JsonRpcClient("$endPoint/jsonrpc", NULL);
$jsonClient->__setHeader(array('Content-Type: application/json', $oauthHeaderForJson));

$sendMessageRequest = new stdclass();
$sendMessageRequest->savecopy = true;
$sendMessageRequest->message = new stdclass();

$sendMessageRequest->message->subject = "OAUTH Send Message Testing!";
$sendMessageRequest->message->from = new stdclass();
$sendMessageRequest->message->from->name = "from";
$sendMessageRequest->message->from->email = $argv[3];

$sendMessageRequest->message->to = array();
$to = new stdclass();
$to->name = "to";
$to->email = $argv[4];
array_push($sendMessageRequest->message->to, $to);

$sendMessageRequest->message->body = new stdclass();
$sendMessageRequest->message->body->data = "Hello there! OAUTH testing in progress ... ";
$sendMessageRequest->message->body->type = "text";
$sendMessageRequest->message->body->subtype = "plain";
$sendMessageRequest->message->body->charset = "us-ascii";

$rsp = $jsonClient->SendMessage($sendMessageRequest);

?>
