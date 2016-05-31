<?php
/*
 * Download Manager Bot for www.aathaapi.org
 */
$token = '238992121:AAGPTHmkAxO0o9OcG5xJx96k77HFCQCm7Lc';

function build_reply($chat_id, $text) {
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendMessage?chat_id='
        . $chat_id . '&text= ' . $text.' &disable_web_page_preview=true&parse_mode=markdown';
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
    $returnvalue = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/sendMessage?chat_id='. $chat_id  . '&reply_to_message_id=' . $message_id . '&text=Cancelled !&reply_markup=' . json_encode($markup);
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
        for($i = 0; $i < count($file_exist); $i++) {
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
            error_log($button_text);
            $keyboard['keyboard'][$i+1][0] = urlencode($button_text);
        }
        send_curl(build_keyboard($chat_id,"`".count($file_exist)." file(s) found!`", $message_id, $keyboard));
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
    send_curl(build_reply($chat_id,urlencode($sql)));
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
    $username = $messageobj['message']['from']['username'];
    $message_id = $messageobj['message']['message_id'];
    $admin = in_array($username,array("Nisal","CMNisal","RASMR","saminda"));
    $verified = in_array($chat_id,array(196536622,59436507,132666396,120125309,-145097544)) || $admin;
    //chat_id - (Nisal,Sarani,Saminda,Amila,Aathaapi TEST)

    if($request_message=="/help" || $request_message=="/start"){
        $reply = urlencode(' Welcome to   *Aathaapi Download Manager*

/find _<keyword>_ -  Find files Match with Given Keyword. 
/help  	-	Show this Dialog. 
');

        send_curl(build_reply($chat_id,$reply));
        return;
    }if($request_message=="/find" || $request_message=="#find"){
        if(strlen($keyword)){
            find($chat_id,$keyword,$message_id);
        }else if(array_key_exists('reply_to_message', $messageobj['message'])){
            find($chat_id,$messageobj['message']['reply_to_message']['text'],$message_id);
        }else{
            send_curl(build_forcereply($chat_id,"`Enter keyword :`",$message_id));
        }
        return;
    }if($request_message=="📚" || $request_message=="🎥" || $request_message=="🖼" || $request_message=="🔊" || $request_message=="🛠" || $request_message=="📦"){
        find_with_display_name($keyword,$chat_id);
        return;
    }if($request_message=="/delete" || $request_message=="#delete"){
        if(!$verified){
            send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to Delete any files.`"));
            return;
        }
        if(strlen($keyword)){
            delete($keyword,$chat_id);
        }else if(array_key_exists('reply_to_message', $messageobj['message'])){
            if(array_key_exists('audio', $messageobj['message'])){
                $file = $messageobj['message']['reply_to_message']['audio'];
                $file_name = $file['title'].".mp3";
            }else{
                $file = $messageobj['message']['reply_to_message']['document'];
                $file_name = $file['file_name'];
            }

            delete($file_name,$chat_id);
        }else{
            delete_find($chat_id,$message_id);
        }
        return;
    }if($request_message=="⛔️"){
        if(!$verified){
            send_curl(build_reply($chat_id,"`Sorry, You are not Authorized to Delete any files.`"));
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
            $file_name = $file['file_name'];
			$display_name = str_replace("_"," ",$file['file_name']);
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
            $new_file->display_name = str_replace("_"," ",$file_name);
            $new_file->filetype = $file_type;
            $new_file->user = $username;
            $db->insertObject('aathaapi_files', $new_file);

            $file = new stdClass();
            $file->file_id = $file_id;
            $file->active = 1;
            $db->updateObject('aathaapi_files',$file,'file_id');
            $reply = urlencode("`Enter display name for` - *".$file_name."*");
            send_curl(build_forcereply($chat_id,$reply,$message_id));
        }else{
            $reply = "*".$file_name."* `is already exist`";
            send_curl(build_reply($chat_id,$reply));
        }
        return;
    }
    if($messageobj['message']['reply_to_message']['from']['username']=="AathaapiBot"){
        $bot_reply = $messageobj['message']['reply_to_message']['text'];//botReply
        if($bot_reply=="Enter keyword :"){
            find($chat_id,$message_text,$message_id);
        }
        if(strpos($bot_reply, 'Enter display name') !== false){
            if(!$verified){
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
        }
        if($bot_reply=="Enter exact file name you want to delete :"){
            delete($message_text,$chat_id);
        }
        return;
    }

//end	
}
send_response(file_get_contents('php://input'));
