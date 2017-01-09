<?php
/*
 * Download Manager Bot for www.aathaapi.org
 */
$token = '238992121:AAGPTHmkAxO0o9OcG5xJx96k77HFCQCm7Lc';

function build_reply($chat_id, $text) {
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendMessage?chat_id='
        . $chat_id . '&text= ' . urlencode($text).' &disable_web_page_preview=true&parse_mode=markdown';
    return $returnvalue;
}
function build_forcereply($chat_id,$text,$message_id) {
    $markup['force_reply'] = true;
    $markup['selective'] = true;
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendMessage?chat_id='
        . $chat_id . '&text=' . $text . '&reply_markup=' . json_encode($markup). '&reply_to_message_id=' . $message_id.'&disable_web_page_preview=true&parse_mode=markdown';
    return $returnvalue;
}
function build_document($chat_id,$file_id) {
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendDocument?chat_id='
        . $chat_id . '&document=' .$file_id.'&caption=@AathaapiBot';
    return $returnvalue;
}
function build_keyboard($chat_id, $text, $message_id, $markup) {
    $markup['resize_keyboard'] = true;
    $markup['one_time_keyboard'] = true;
    $markup['selective'] = true;
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendMessage?chat_id='
        . $chat_id . '&text=' . $text . '&reply_to_message_id=' . $message_id . '&reply_markup=' . json_encode($markup).'&disable_web_page_preview=true&parse_mode=markdown';
    return $returnvalue;
}
function hide_keyboard($chat_id,$message_id) {
    $markup['hide_keyboard'] = true;
    $markup['selective'] = true;
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendMessage?chat_id='. $chat_id  . '&reply_to_message_id=' . $message_id . '&text=List Cancelled !&reply_markup=' . json_encode($markup);
    error_log($returnvalue);
    return $returnvalue;
}
function find_with_display_name($find_keyword,$chat_id){
    $db = dbAccess::getInstance();
    $query = "SELECT * FROM aathaapi_files WHERE display_name = '$find_keyword'";
    error_log($query);
    $db->setQuery($query);
    $file_exist = $db->loadAssoc();
    if(empty($file_exist)){
        send_curl(build_reply($chat_id,"`File Not Found!`"));
    }else{
        $file_id = $file_exist['file_id'];
        send_curl(build_document($chat_id,$file_id));
    }
}
function error_report($error){
	send_curl(build_reply(-1001054269939,urlencode("`".$error."`")));
}
function find($chat_id,$find_keyword,$message_id){
    $db = dbAccess::getInstance();
    $find_keyword = str_replace(' ','%',$find_keyword);
    $query = "SELECT * FROM aathaapi_files WHERE (name LIKE '%".$find_keyword."%' OR display_name LIKE '%".$find_keyword."%' ) AND active = 1 ORDER BY display_name";
    error_log($query);
    $db->setQuery($query);
    $file_exist = $db->loadAssocList();
    if(empty($file_exist)){
        send_curl(build_reply($chat_id,"`No Files Found!`"));
    }else if(count($file_exist)==1){
        error_log("1 File");
        $file_id = $file_exist[0]['file_id'];
        send_curl(build_document($chat_id,$file_id));
    }else{
        $keyboard = array('keyboard' => array());
        $keyboard['keyboard'][0][0] = "Cancel";
		
		$temp_msg = "`".count($file_exist)." file(s) found!`";
		$file_list_msg = "";
		for($i = 0; $i < 130; $i++) {
            switch ($file_exist[$i]['filetype']) {
                case pdf:
                    $button_text = "📚 ";
                    break;
                case video:
                    $button_text = "🎥 ";
                    break;
                case image:
                    $button_text = "🖼 ";
                    break;
                case audio:
                    $button_text = "🔊 ";
                    break;
                case 'octet-stream':
                    $button_text = "🛠 ";
                    break;
                default:
                    $button_text = "📦 ";
            }
            $button_text .= $file_exist[$i]['display_name'];
			$file_list_msg.='/dl'.$file_exist[$i]['uid']." ".$button_text."\n";
            
            $keyboard['keyboard'][$i+1][0] = urlencode($button_text);
        }
		error_log($file_list_msg);
        send_curl(build_keyboard($chat_id,$temp_msg, $message_id, $keyboard));
		send_curl(build_reply($chat_id,$file_list_msg));//File Message
        return;
    }
}
function delete_find($chat_id,$message_id){
    $db = dbAccess::getInstance();
    $find_keyword = str_replace(' ','%',$find_keyword);
    $query = "SELECT * FROM aathaapi_files WHERE active = 1 ORDER BY display_name";
    error_log($query);
    $db->setQuery($query);
    $file_exist = $db->loadAssocList();
    if(empty($file_exist)){
        send_curl(build_reply($chat_id,"`No Files Found!`"));
    }else{
        $keyboard = array('keyboard' => array());
        $keyboard['keyboard'][0][0] = "Cancel";
        for($i = 0; $i < count($file_exist); $i++) {
            error_log("Delete More Files");
            $button_text = "";
            $button_text .= "⛔️ ".$file_exist[$i]['name'];
            $keyboard['keyboard'][$i+1][0] = urlencode($button_text);
        }
        send_curl(build_keyboard($chat_id,"`Select the File`", $message_id, $keyboard));
        return;
    }
}
function delete($filename,$chat_id){
    $db = dbAccess::getInstance();
    $sql = "SELECT * FROM aathaapi_files WHERE name = '".$filename."' AND active = 1";
    $db->setQuery($sql);
    $check_file = $db->loadAssoc();
    if(empty($check_file)){
        send_curl(build_reply($chat_id,$filename."` File Not Found!`"));
        return;
    }
    $reply = urlencode("`Deleting file...`
`File name 	  -` *".$check_file['name']."*
`Display name -` *".$check_file['display_name']."*
`File type    -` *".ucfirst($check_file['filetype']."*"));
    send_curl(build_reply($chat_id,$reply));

    $file = new stdClass();
    $file->name = $filename;
    $file->active = 0;
    $db->updateObject('aathaapi_files',$file,'name');

    error_log($query);
    $db->setQuery($sql);
    $file_exist = $db->loadAssoc();
    if(empty($file_exist)){
        send_curl(build_reply($chat_id,"`File Delete Successfull!`"));
    }else{
        $file_id = $file_exist['file_id'];
        send_curl(build_reply($chat_id,"`File Delete Unsuccessfull!`"));
    }
}
function send_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }
    // Close connection
    curl_close($ch);
}

function send_response($input_raw) {
    include_once ('dbAccess.php');
    $db = dbAccess::getInstance();
    //$response = send_curl('https://api.telegram.org/bot115962358:AAEIAdDOp1xUlFBOM_B8e0-nWZN7Y146Cp0/getUpdates');
    /*$input_raw = '{
                      "update_id": 89018516,
                      "message": {
                        "message_id": 62,
                        "from": {
                          "id": 38722085,
                          "first_name": "Ramindu \"RamdeshLota\"",
                          "last_name": "Deshapriya",
                          "username": "CMNisal"
                        },
                        "chat": {
                          "id":38722085,
                          "title": "Bottest"
                        },
                        "date": 1435508622,
                        "text": "/addmetodir"
                      }
                    }';*/
    // let's log the raw JSON message first
    $messageobj = json_decode($input_raw, true);
    $message_text = str_replace('@AathaapiBot','',$messageobj['message']['text']);
    $message_part = explode(' ', strtolower($message_text));
    $request_message = $message_part[0];
    $keyword = substr($message_text,strlen($request_message)+1,strlen($message_text));
    $chat_id = $messageobj['message']['chat']['id'];
    $user_id = $messageobj['message']['from']['id'];
    $username = '@'.$messageobj['message']['from']['username'];
	$name = $messageobj['message']['from']['first_name']." ".$messageobj['message']['from']['last_name'];
    $message_id = $messageobj['message']['message_id'];
    $admin = in_array($username,array("@Nisal","@CMNisal","@RASMR","@saminda"));
    $verified = (in_array($chat_id,array(196536622,59436507,132666396,120125309,-145097544)) || $admin);
    //chat_id - (Nisal,Sarani,Saminda,Amila,Aathaapi TEST)

    if($request_message=="/help" || $request_message=="/start"){
        $reply = urlencode(' Welcome to		
*Aathaapi Download Manager*

/find _<keyword>_ -  Find files Match with Given Keyword. 
        eg:- /find _Tathagata Bala_

/find _Bana_ - *Find all Bana*
/find _Video_ - *Find all Videos*
/find _Chart_ - *Find all Charts*
/find _Book_ - *Find all Books*	
		
/help  	-	Show this Dialog. 
');

        send_curl(build_reply($chat_id,$reply));
        return;
    }if($request_message=="/find" || $request_message=="#find"){
        if(strlen($keyword)){
			if(!$admin){error_report("Find ".$keyword."\n".$username."-".$name);}
            find($chat_id,$keyword,$message_id);
        }else if(array_key_exists('reply_to_message', $messageobj['message'])){
			if(!$admin){error_report("Find ".$keyword."\n".$username."-".$name);}
            find($chat_id,$messageobj['message']['reply_to_message']['text'],$message_id);
        }else{
            send_curl(build_forcereply($chat_id,"`Enter keyword :`",$message_id));
        }
        return;
    }if($request_message=="📚" || $request_message=="🎥" || $request_message=="🖼" || $request_message=="🔊" || $request_message=="🛠" || $request_message=="📦"){
        error_report("Download ".$keyword."\n".$username."-".$name);find_with_display_name($keyword,$chat_id);
        return;
    }if($request_message=="/delete" || $request_message=="#delete"){
        if(!$verified){
			if(!$admin){error_report("Unauthorized Delete request from ".$username."-".$name);}
            send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to Delete any files.`"));
            return;
        }else if(strlen($keyword)){
            delete($keyword,$chat_id);
        }else if(array_key_exists('reply_to_message', $messageobj['message'])){
            if(array_key_exists('audio', $messageobj['message'])){
                $file = $messageobj['message']['reply_to_message']['audio'];
                $file_name = $file['title'].".mp3";
            }else{
                $file = $messageobj['message']['reply_to_message']['document'];
                $file_name = $file['file_name'];
            }
			error_report($file_name."\nDeleted by ".$username."-".$name);
            delete($file_name,$chat_id);
        }else{
            delete_find($chat_id,$message_id);
        }
        return;
    }if($request_message=="⛔️"){
        if(!$verified){
			error_report("Unauthorized Delete request from ".$username."-".$name);
            send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to Delete any file(s).`"));
            return;
        }
        delete($keyword,$chat_id);
        return;
    }if ($request_message == 'cancel') {
        error_log("Cancel");
        send_curl(hide_keyboard($chat_id,$message_id));
        return;
    }

    if(array_key_exists('document', $messageobj['message']) || array_key_exists('audio', $messageobj['message'])){
        if(!$verified){
			error_report("Unauthorized upload request from ".$username."-".$name);
            send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to upload any files.`"));
            return;
        }
        send_curl(build_reply($chat_id,"`Document Found.`"));

        if(array_key_exists('audio', $messageobj['message'])){
            $file = $messageobj['message']['audio'];
            $file_name = $file['title'].".mp3";
			$display_name = str_replace("_"," ",$file['title']);
        }else{
            $file = $messageobj['message']['document'];
            $file_name = str_replace("_"," ",$file['file_name']);
			$filebroken = explode( '.', $file_name);
			$extension = array_pop($filebroken);
			$display_name = implode('.', $filebroken);
        }

        $db->setQuery("SELECT * FROM aathaapi_files WHERE name = '$file_name' AND active = 1");
        $file_exist = $db->loadAssoc();
        if(empty($file_exist)){
            $file_types = explode('/', $file['mime_type']);
            if($file_types[0]=="application"){
                $file_type = $file_types[1];
            }else{
                $file_type = $file_types[0];
            }
            $file_id = $file['file_id'];

            $new_file = new stdClass();
            $new_file->file_id = $file_id;
            $new_file->name = $file_name;
            $new_file->display_name = $display_name;
            $new_file->filetype = $file_type;
            $new_file->user = $username;	    
            $db->insertObject('aathaapi_files', $new_file);

            $file = new stdClass();
            $file->file_id = $file_id;
            $file->active = 1;
            $db->updateObject('aathaapi_files',$file,'file_id');
            $reply = urlencode("`Enter display name for` - *".$file_name."*");
			error_report($file_name."\n File Uploaded by ".$username."-".$name);
            send_curl(build_forcereply($chat_id,$reply,$message_id));
			send_curl(build_reply(-1001054269939,$reply));
        }else{
            $reply = "*".$file_name."* `is already exist`";
			error_report("Duplicate File Upload ".$file_name."\n".$username."-".$name);
            send_curl(build_reply($chat_id,$reply));
        }
	            	    
        return;
    }
	
    if($request_message=="/help" || $request_message=="/start" || $request_message=="/find"){
	    
	 $db->setQuery("SELECT * FROM aathaapi_users WHERE user_id = '$user_id' AND active = 1");
	 $file_exist = $db->loadAssoc();
         if(empty($file_exist)){
		
	     $new_user = new stdClass();
	     $new_user->user_id = $user_id;
	     $new_user->user = $username;
	     $new_user->user_name = $name;
	     $db->insertObject('aathaapi_users', $new_user);

	     $user = new stdClass();
	     $user->user_id = $user_id;
	     if(!$verified){
		$user->hasAccess = 0;	
            	return;
             }
	     else{
	     	$user->hasAccess = 1;
	     }
	     $user->active = 1;
	     $db->updateObject('aathaapi_user',$user,'user_id');
	}
	    
	return;
    }
	
    if($messageobj['message']['reply_to_message']['from']['username']=="AathaapiBot"){
        $bot_reply = $messageobj['message']['reply_to_message']['text'];//botReply
        if($bot_reply=="Enter keyword :"){
			error_report("Find ".$message_text."\n".$username."-".$name);
            find($chat_id,$message_text,$message_id);
        }else if(strpos($bot_reply, 'Enter display name') !== false){
            if(!$verified){
			error_report("Unauthorized Display Name change request from ".$username."-".$name);
    		send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to Change any Display Name.`"));
    		return;
			}
            $filename = substr($bot_reply,strpos($bot_reply,'-')+2);
			$file = new stdClass();
            $file->name = $filename;
            $file->display_name = $message_text;
            $db->updateObject('aathaapi_files',$file,'name');

            $sql = "SELECT * FROM aathaapi_files WHERE name = '".$filename."'";
            $db->setQuery($sql);
            $check_file = $db->loadAssoc();

            $reply = urlencode("`File name 	  -` *".$check_file['name']."*
`Display name -` *".$check_file['display_name']."*
`File type    -` *".ucfirst($check_file['filetype']."*"));
            send_curl(build_reply($chat_id,$reply));
			send_curl(build_reply(-1001054269939,$reply));
			return;
        }else if($bot_reply=="Enter exact file name you want to delete :"){
            if(!$verified){
			error_report("Unauthorized Delete request from ".$username."-".$name);
    		send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to Delete any file(s).`"));
    		return;
			}
			delete($message_text,$chat_id);
			return;
        }
		//error_report($message_text."\n".$username."-".$name);
        return;
    }
	if($message_text{0}=="/"||$message_text{0}=="#"){error_report($message_text."\n".$username."-".$name);}

//end	
}

send_response(file_get_contents('php://input'));
