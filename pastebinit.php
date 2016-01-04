#!/usr/bin/php
<?php
//pastebinit.php version 0.1.0-dev
@ob_end_clean();//little hack to remove the #!/usr/bin/php from output.. thanks "Viliam Simko"@stackoverflow
hhb_init();
$stdin = fopen('php://stdin', 'rb');
if(false===$stdin){
	throw new RuntimeException('unable to open stdin!');
}
@stream_set_blocking($stdin,true);
$tmpfile=tmpfile();
if(false===$tmpfile){
	throw new RuntimeException('tmpfile() failed!');
}
$tmpfile_location=stream_get_meta_data($tmpfile);
if(!is_array($tmpfile_location) || !array_key_exists('uri',$tmpfile_location) || 0>=strlen($tmpfile_location['uri'])){
	throw new RuntimeException('unable to get the location of tmpfile!');
}
$tmpfile_location=$tmpfile_location['uri'];
if(!is_readable($tmpfile_location)){
	throw new RuntimeException('tmpfile_location is not readable!');
}
$tmpfile_location=hhb_combine_filepaths($tmpfile_location);//make it use / instead of \, it has caused me trouble on Windows hosts on other projects..
assert(is_readable($tmpfile_location));//this should always be true..
$newdata="";
$written_last=0;
$written_total=0;
while(!feof($stdin)){
	sleep(0);//$stdin should be blocking anyway...
	$newdata=stream_get_contents($stdin);
	if(false===$newdata || strlen($newdata)===0){
		continue;
	}
		$written_last=fwrite($tmpfile,$newdata);
		if($written_last!==strlen($newdata)){
			throw new RuntimeException('tried to write '.strlen($newdata).' bytes to tmpfile, but could only write '.$written_last.' bytes!');
		}
		$written_total+=$written_last;
}
$api_options=array(
//<Mandatory parameters>
//paste_data - The text to be pasted
//'paste_data'=>(version_compare(PHP_VERSION,'5.5.0','>=')? new CURLFile($tmpfile_location) :'@'.$tmpfile_location.';type=application/octet-stream'),//i prefer to keep things binary, for safety :p
//TODO: tell curl to load the data from this file (apparently NOT easy...), OR rewrite the entire script to keep everything in memory..
//right now its just an IO waste, the intent was to tell curl to load the data from file, to support big uploads / minimize memory
'paste_data'=>file_get_contents($tmpfile_location),
//paste_lang - The development language used
'paste_lang'=>'text',
//api_submit - Set this parameter to true
'api_submit'=>'true',
//mode - Pass xml or json to this parameter
'mode'=>'json',
//</Mandatory paramaters>
//<Optional parameters>
//paste_user - An alphanumeric username of the paste author
//paste_password - A password string to protect the paste
//paste_private - Private post flag, having the values: yes or no
'paste_private'=>'yes',//the documentation says "yes" and "no", but the html page use "on" or "off"...
//paste_expire - Time in seconds after which paste will be deleted from server. Set this value to 0 to disable this feature.
'paste_expire'=>1*60*60*24*365,//1 year. even 32bit PHP_INT_MAX allows approx 68 years.
//paste_project - Whether to associate a project with the paste
//For a list of supported language codes for the paste_lang parameter, see http://qbnz.com/highlighter/. 
//</Optional parameters>
);
echo fedoraproject_pastebinit($api_options).PHP_EOL;

function print_stderr(/*...*/){
	$args=func_get_args();
	$stderr=fopen('php://stderr','wb');
	assert(false!==$stderr);
	$ret=fwrite($stderr,hhb_return_var_dump($args));
	fclose($stderr);
	return $ret;
}
function hhb_return_var_dump() //works like var_dump, but returns a string instead of printing it.
{
    $args = func_get_args(); //for <5.3.0 support ...
    ob_start();
    call_user_func_array('var_dump', $args);
    return ob_get_clean();
}

//this function always returns the url, or throw an exception, no exceptions.
function fedoraproject_pastebinit($api_options=array()){
	assert(is_array($api_options));
	$ch=hhb_curl_init();
	
	curl_setopt_array($ch,array(
	CURLOPT_SAFE_UPLOAD=>(version_compare(PHP_VERSION,'5.5.0','>=')?true:false),//warning: php 5.6.0: CURLOPT_SAFE_UPLOAD is now TRUE by default.
	CURLOPT_POST=>true,
	CURLOPT_POSTFIELDS=>$api_options,
	CURLOPT_HTTPHEADER=>array(
	'Expect:',//not sure why curl defaults to "expect 100 continue"-something... but it is making fedora pastebin page angry.
	),
	));
	$headers=array();
	$cookies=array();
	$http_debug_str="";
	$response=hhb_curl_exec2($ch,'http://paste.fedoraproject.org',$headers,$cookies,$http_debug_str);
	$response_parsed=json_decode($response,true,1337);
	if(!is_array($response_parsed)){
		print_stderr('Error: Could not decode the response as JSON!','response: ',$response,'headers:',$headers,'cookies:',$cookies,'http debug str:',$http_debug_str);
		throw new RuntimeException('Error: Could not decode the response as JSON! see stderr for more info...');
	}
	//var_dump($response_parsed);
	if(array_key_exists('error',$response_parsed)){
		assert(is_string($response_parsed['error']));
		$errstr='';
		switch($response_parsed['error']){
			case 'err_nothing_to_do':
			$errstr='err_nothing_to_do - No POST request was received by the create API';
			break;
			case 'err_author_numeric':
			$errstr='err_author_numeric - The paste author\'s alias should be alphanumeric';
			break;
			case 'err_save_error':
			$errstr='err_save_error - An error occurred while saving the paste';
			break;
			case 'err_spamguard_ipban':
			$errstr='err_spamguard_ipban - Poster\'s IP address is banned';
			break;
			case 'err_spamguard_stealth':
			$errstr='err_spamguard_stealth - The paste triggered the spam filter';
			break;
			case 'err_spamguard_noflood':
			$errstr='err_spamguard_noflood - Poster is trying the flood';
			break;
			case 'err_spamguard_php':
			$errstr='err_spamguard_php - Poster\'s IP address is listed as malicious';
			break;
			default:
			$errstr='unknown error: '.var_export($response_parsed['error'],true);
			break;
		}
		print_stderr('got error when uploading!: '.$errstr,'response: ',$response,'headers:',$headers,'cookies:',$cookies,'http debug str:',$http_debug_str);
		throw new RuntimeException('got error when uploading! see stderr for more info..');
	}
	if(!array_key_exists('result',$response_parsed)){
		print_stderr('error: could not find result object in response!','response: ',$response,'headers:',$headers,'cookies:',$cookies,'http debug str:',$http_debug_str);
		throw new RuntimeException('error: could not find result object in response! see stderr for more info..');
	}
	if(!array_key_exists('id',$response_parsed['result'])){
		print_stderr('error: could not find id object in response["result"] !','response: ',$response,'headers:',$headers,'cookies:',$cookies,'http debug str:',$http_debug_str);
		throw new RuntimeException('error: could not find id object in response["result"] ! see stderr for more info..');
	}
	$url='http://paste.fedoraproject.org';// /307111/19476341/raw/';
	$url.='/'.$response_parsed['result']['id'];
	if(isset($response_parsed['result']['hash'])){
		$url.='/'.$response_parsed['result']['hash'];
	}
	$url.='/raw/';
	return $url;
	//var_dump($response_parsed);
}

function hhb_init()
{
    error_reporting(E_ALL);
    set_error_handler("hhb_exception_error_handler");
    //	ini_set("log_errors",true);
    //	ini_set("display_errors",true);
    //	ini_set("log_errors_max_len",0);
    //	ini_set("error_prepend_string",'<error>');
    //	ini_set("error_append_string",'</error>'.PHP_EOL);
    //	ini_set("error_log",__DIR__.'/error_log.php');
}
function hhb_exception_error_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}


function hhb_combine_filepaths( /*...*/ )
{
    $args = func_get_args();
    if (count($args) == 0) {
        return "";
    }
    $ret = "";
    $i   = 0;
    foreach ($args as $arg) {
        ++$i;
        if ($i != 1) {
            $ret .= '/';
        }
        $ret .= str_replace("\\", '/', $arg) . '/';
    }
    while (false !== stripos($ret, '//')) {
        $ret = str_replace('//', '/', $ret);
    }
    if (strlen($ret) < 2) {
        return $ret; //very edge case scenario, a single / or \ empty
    }
    if ($ret[strlen($ret) - 1] == '/') {
        $ret = substr($ret, 0, -1);
    }
    return $ret;
}
function hhb_curl_init($custom_options_array = array())
{
    if (empty($custom_options_array)) {
        $custom_options_array = array();
        //i feel kinda bad about this.. argv[1] of curl_init wants a string(url), or NULL
        //at least i want to allow NULL aswell :/
    }
    if (!is_array($custom_options_array)) {
        throw new InvalidArgumentException('$custom_options_array must be an array!');
    }
    ;
    $options_array = array(
        CURLOPT_AUTOREFERER => true,
        CURLOPT_BINARYTRANSFER => true,
        CURLOPT_COOKIESESSION => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FORBID_REUSE => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 11,
        CURLOPT_ENCODING => ""
        //CURLOPT_REFERER=>'example.org',
        //CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0'
    );
    if (!array_key_exists(CURLOPT_COOKIEFILE, $custom_options_array)) {
        //do this only conditionally because tmpfile() call..
        static $curl_cookiefiles_arr = array(); //workaround for https://bugs.php.net/bug.php?id=66014
        $curl_cookiefiles_arr[]            = $options_array[CURLOPT_COOKIEFILE] = tmpfile();
        $options_array[CURLOPT_COOKIEFILE] = stream_get_meta_data($options_array[CURLOPT_COOKIEFILE]);
        $options_array[CURLOPT_COOKIEFILE] = $options_array[CURLOPT_COOKIEFILE]['uri'];
        
    }
    //we can't use array_merge() because of how it handles integer-keys, it would/could cause corruption
    foreach ($custom_options_array as $key => $val) {
        $options_array[$key] = $val;
    }
    unset($key, $val, $custom_options_array);
    $curl = curl_init();
    if($curl===false){
    	throw new RuntimeException('could not create a curl handle! curl_init() returned false');
    }
    if(false===curl_setopt_array($curl, $options_array)){
    	$errno=curl_errno($curl);
    	$error=curl_error($curl);
    	throw new RuntimeException('could not set options on curl! curl_setopt_array returned false. curl_errno :'.$curl_errno.'. curl_error: '.$curl_error);
    }
    return $curl;
}
function hhb_curl_exec($ch, $url)
{
    static $hhb_curl_domainCache = "";//warning, this will not work properly with 2 different curl's visiting 2 different sites. 
    //should probably use SplObjectStorage here, so each curl can have its own cache..
    //$hhb_curl_domainCache=&$this->hhb_curl_domainCache;
    //$ch=&$this->curlh;
    if (!is_resource($ch) || get_resource_type($ch) !== 'curl') {
        throw new InvalidArgumentException('$ch must be a curl handle!');
    }
    if (!is_string($url)) {
        throw new InvalidArgumentException('$url must be a string!');
    }
    
    $tmpvar = "";
    if (parse_url($url, PHP_URL_HOST) === null) {
        if (substr($url, 0, 1) !== '/') {
            $url = $hhb_curl_domainCache . '/' . $url;
        } else {
            $url = $hhb_curl_domainCache . $url;
        }
    }
    ;
    
    if(false===curl_setopt($ch, CURLOPT_URL, $url)){
        $errno=curl_errno($curl);
    	$error=curl_error($curl);
    	throw new RuntimeException('could not set CURLOPT_URL on curl! curl_setopt returned false. curl_errno :'.$curl_errno.'. curl_error: '.$curl_error.'. url: '.var_export($url,true));
    }
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error (curl_errno=' . curl_errno($ch) . ') on url ' . var_export($url, true) . ': ' . curl_error($ch));
        // echo 'Curl error: ' . curl_error($ch);
    }
    if ($html === '' && 203 != ($tmpvar = curl_getinfo($ch, CURLINFO_HTTP_CODE)) /*203 is "success, but no output"..*/ ) {
        throw new Exception('Curl returned nothing for ' . var_export($url, true) . ' but HTTP_RESPONSE_CODE was ' . var_export($tmpvar, true));
    }
    ;
    //remember that curl (usually) auto-follows the "Location: " http redirects..
    $hhb_curl_domainCache = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST);
    return $html;
}
function hhb_curl_exec2($ch, $url, &$returnHeaders = array(), &$returnCookies = array(), &$verboseDebugInfo = "")
{
    $returnHeaders    = array();
    $returnCookies    = array();
    $verboseDebugInfo = "";
    if (!is_resource($ch) || get_resource_type($ch) !== 'curl') {
        throw new InvalidArgumentException('$ch must be a curl handle!');
    }
    if (!is_string($url)) {
        throw new InvalidArgumentException('$url must be a string!');
    }
    static $stderrhandle=false;
    if($stderrhandle===false){
    	$stderrhandle=fopen('php://stderr', 'wb');
    	if($stderrhandle===false){
    		throw new RuntimeException('unable to get a handle to php://stderr !');
    	}
    }
    $verbosefileh = tmpfile();
    if($verbosefileh===false){
        throw new RuntimeException('can not create a tmpfile for curl\'s stderr. tmpfile returned false');
    }
    $verbosefile  = stream_get_meta_data($verbosefileh);
    $verbosefile  = $verbosefile['uri'];
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_STDERR, $verbosefileh);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $html             = hhb_curl_exec($ch, $url);
    $verboseDebugInfo = file_get_contents($verbosefile);
    curl_setopt($ch, CURLOPT_STDERR, $stderrhandle);
    fclose($verbosefileh);
    unset($verbosefile, $verbosefileh);
    $headers       = array();
    $crlf          = "\x0d\x0a";
    $thepos        = strpos($html, $crlf . $crlf, 0);
    $headersString = substr($html, 0, $thepos);
    $headerArr     = explode($crlf, $headersString);
    $returnHeaders = $headerArr;
    unset($headersString, $headerArr);
    $htmlBody = substr($html, $thepos + 4); //should work on utf8/ascii headers... utf32? not so sure..
    unset($html);
    //I REALLY HOPE THERE EXIST A BETTER WAY TO GET COOKIES.. good grief this looks ugly..
    //at least it's tested and seems to work perfectly...
    $grabCookieName = function($str,&$len)
    {
        $len=0;
        $ret = "";
        $i   = 0;
        for ($i = 0; $i < strlen($str); ++$i) {
            ++$len;
            if ($str[$i] === ' ') {
                continue;
            }
            if ($str[$i] === '=' || $str[$i] === ';') {
                --$len;
                break;
            }
            $ret .= $str[$i];
        }
        return urldecode($ret);
    };
    foreach ($returnHeaders as $header) {
        //Set-Cookie: crlfcoookielol=crlf+is%0D%0A+and+newline+is+%0D%0A+and+semicolon+is%3B+and+not+sure+what+else
        /*Set-Cookie:ci_spill=a%3A4%3A%7Bs%3A10%3A%22session_id%22%3Bs%3A32%3A%22305d3d67b8016ca9661c3b032d4319df%22%3Bs%3A10%3A%22ip_address%22%3Bs%3A14%3A%2285.164.158.128%22%3Bs%3A10%3A%22user_agent%22%3Bs%3A109%3A%22Mozilla%2F5.0+%28Windows+NT+6.1%3B+WOW64%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F43.0.2357.132+Safari%2F537.36%22%3Bs%3A13%3A%22last_activity%22%3Bi%3A1436874639%3B%7Dcab1dd09f4eca466660e8a767856d013; expires=Tue, 14-Jul-2015 13:50:39 GMT; path=/
        Set-Cookie: sessionToken=abc123; Expires=Wed, 09 Jun 2021 10:18:14 GMT;
        //Cookie names cannot contain any of the following '=,; \t\r\n\013\014'
        //
        */
        if (stripos($header, "Set-Cookie:") !== 0) {
            continue;
            /**/
        }
        $header = trim(substr($header, strlen("Set-Cookie:")));
        $len=0;
        while (strlen($header) > 0) {
            $cookiename                 = $grabCookieName($header,$len);
            $returnCookies[$cookiename] = '';
            $header                     = substr($header, $len); 
            if (strlen($header) < 1) {
                break;
            }
            if($header[0]==='='){
				$header=substr($header,1);
			}
            $thepos = strpos($header, ';');
            if ($thepos === false) { //last cookie in this Set-Cookie.
                $returnCookies[$cookiename] = urldecode($header);
                break;
            }
            $returnCookies[$cookiename] = urldecode(substr($header, 0, $thepos));
            $header                     = trim(substr($header, $thepos + 1)); //also remove the ;
        }
    }
    unset($header, $cookiename, $thepos);
    return $htmlBody;
}