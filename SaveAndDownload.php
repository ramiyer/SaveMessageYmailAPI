<?php

//Libraries used for accessing YMail api's.
require_once 'OAuth.php';
require_once 'JsonRpcClient.inc';
$OAuthConsumerKey = $argv[2];
$OAuthConsumerSecret = $argv[3];

if (count ($argv) != 4) {
	echo "php SaveAndDownload.php JSON <consumer key> <consumer secret> \n";
	exit();	
}

//Endpoint for Yahoo mail WSDL
$endPoint = 'http://mail.yahooapis.com/ws/mail/v1.1';
//OAuth Endpoint
$OAuthEndPoint = 'https://api.login.yahoo.com/oauth/v2';
$endPointDownload = 'http://mail.yahooapis.com/ya/download';

// see http://developer.yahoo.com/oauth/guide/oauth-auth-flow.html
// We can even use PLAINTEXT. 
$signature = new OAuthSignatureMethod_HMAC_SHA1();

// 1) Get Request Token
$request = new OAuthRequest('GET', "$OAuthEndPoint/get_request_token", array(
	'oauth_nonce'=>mt_rand(),
	'oauth_timestamp'=>time(),
	'oauth_version'=>'1.0',
	'oauth_signature_method'=>'HMAC-SHA1', //'HMAC-SHA1'
	'oauth_consumer_key'=>$OAuthConsumerKey,
	'oauth_callback'=>'oob'));
//Use this for PlainText 
//$url = $request->to_url()."&oauth_signature=$OAuthConsumerSecret%26";

//For HMAC-SHA1 signature
$url = $request->to_url()."&oauth_signature=".urlencode($signature->build_signature( $request, new OAuthConsumer('', $OAuthConsumerSecret), NULL));

//Curl related functions
$ch = curl_init();
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt( $ch, CURLOPT_URL, $url );
//curl_setopt( $ch, CURLOPT_VERBOSE , 1 );
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
	//.$signature->build_signature( $request, new OAuthConsumer('', $OAuthConsumerSecret), new OAuthToken('', $oauth_token_secret));
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


// 4) Get Authorization header

// 4a) for YMWS SOAP endpoint
$request = new OAuthRequest('POST', "$endPoint/soap", array(
	'oauth_nonce'=>mt_rand(),
	'oauth_timestamp'=>time(),
	'oauth_version'=>'1.0',
	'oauth_signature_method'=>'HMAC-SHA1',
	'oauth_consumer_key'=>$OAuthConsumerKey,
	'oauth_token'=>$oauth_token
	));
$request->sign_request($signature, new OAuthConsumer('', $OAuthConsumerSecret), new OAuthToken('', $oauth_token_secret));
$oauthHeaderForSoap = $request->to_header();

// 4b) for YMWS JSONRPC endpoint
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

// 5) Call YMWS API
if(strtoupper($argv[1]) == "JSON")
{	
	$jsonClient = new JsonRpcClient("$endPoint/jsonrpc", NULL);
	$jsonClient->__setHeader(array('Content-Type: application/json','Accept: application/json', $oauthHeaderForJson));
	$filename = 'msg_download_attachment.msg';
	$saveRawMessageRequest = new stdclass();
	$saveRawMessageRequest->fid = "Inbox";
	$saveRawMessageRequest->text = base64_encode(file_get_contents($filename));
	echo "***********SaveMessageResponse  *************** \n";
	$saveRawMessageResponse = $jsonClient->SaveRawMessage($saveRawMessageRequest);
	var_dump($saveRawMessageResponse);
	$mid = $saveRawMessageResponse->mid;
	$midRequest = $saveRawMessageResponse->result->mid;
	$midRequest = $mid;
	echo "************************************************ \n";

	//Create Download request
	$queryString = $endPointDownload . "?";
	$queryString .= "mid=".urlencode($midRequest);
	$queryString .= "&fid=Inbox";
	$queryString .= "&pid=2";
	$queryString .= "&clean=0";
	$queryString .= "&output=xml";

	echo "***********Download query string *************** \n";
	//Create Download request
	var_dump($queryString);
	echo "************************************************ \n";
	//OAuth header for download request
	$request_args = array(
	        'oauth_nonce'=>mt_rand(),
	        'oauth_timestamp'=>time(),
	        'oauth_version'=>'1.0',
	        'oauth_signature_method'=>'HMAC-SHA1',
	        'oauth_consumer_key'=>$OAuthConsumerKey,
	        'oauth_token'=>$oauth_token,
	        'mid'=>$midRequest,
	        'fid'=>'Inbox',
	        'pid'=>'2',
	        'clean'=>'0',
	        'output'=>'xml'
	        );
	$request = new OAuthRequest('GET', "$endPointDownload", $request_args);
	$request->sign_request($signature, new OAuthConsumer('', $OAuthConsumerSecret), new OAuthToken('', $oauth_token_secret));
	$oauthHeaderForDownloadJson = $request->to_header();
	var_dump($oauthHeaderForDownloadJson);
	
	//Do curl_exec
	global $downloadheader;
	$downloadheader = new stdclass();
	$ch = curl_init($queryString);
	curl_setopt($ch,CURLOPT_HTTPHEADER,array($oauthHeaderForDownloadJson));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, "readDownloadHeader");
	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	$rawresponse = curl_exec($ch);
	var_dump($rawresponse);

	//Get downloaded data
	$response = new stdclass();
	$response->body = $rawresponse;
	$response->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$response->contenttype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$response->contentlength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	$response->lasteffectiveurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	$response->contentdisposition = isset($downloadheader->contentdisposition)? $downloadheader->contentdisposition : "";
	$response->headercontentlength = isset($downloadheader->contentlength)? $downloadheader->contentlength : 0;
	$response->errormessage = isset($downloadheader->errormessage)? $downloadheader->errormessage : "";
	var_dump($response);
}

function readDownloadHeader($ch, $header) {
        global $downloadheader;

        if(preg_match("/^Content-Disposition: (.*)$/", $header, $matches) > 0) {
                $downloadheader->contentdisposition = $matches[1];
        }

        if(preg_match("/^Content-Length: (.*)$/", $header, $matches) > 0) {
                $downloadheader->contentlength = $matches[1] + 0;
        }

        if(preg_match("/^HTTP\/1\.\d (.*)$/", $header, $matches) > 0) {
                $downloadheader->errormessage = $matches[1];
        }

        return strlen($header);
}

?>
