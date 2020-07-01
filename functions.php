<?php
if(count(debug_backtrace()) == 0)
{
    http_response_code(403);
    exit(0);
}

const bot_id_key = "id";
const bot_pass_key = "pass";
const bot_author_key = "author";

const message_key = "message";
const from_key = "from";
const id_key = "id";
const username_key = "username";
const text_key = "text";
const entities_key = "entities";

const bot_command_type = "bot_command";
const type_key = "type";
const offset_key = "offset";
const length_key = "length";

const require_markdown_key = "require_markdown";
const mention_key = "mention";

const first_name_key = "first_name";
const last_name_key = "last_name";

const chat_id_key = "chat_id";
const parse_mode_key = "parse_mode";

const chat_key = "chat";

function process_update($api, $update)
{
    if(!is_array($update))
        return;
    
    if(!isset($update[message_key]))
        return;
    
    $message = $update[message_key];
    
    if(!is_array($message))
        return;
    
    $chat_id = get_chat_id_from_message($message);
    if(!$chat_id)
        return;
    
    $user = get_user_from_message($message);
    if(!$user)
        return;
    
    $text = get_text_from_message($message);
    if(!$text)
        return;
    
    $command_entities = get_command_entities_from_message($message);
    
    $list_command = get_command_from_message($command_entities, $text);
    foreach($list_command as $command)
        process_response($message, $chat_id, $command, $user, $api);
}

function is_int_string($value)
{
    if(!is_numeric($value))
        return FALSE;
    return intval($value) == floatval($value);
}

function get_user_from_message($message)
{
    if(!isset($message[from_key]))
        return FALSE;
    
    $from = $message[from_key];
    if(!is_array($from))
        return FALSE;
    
    if(!isset($from[id_key]))
        return FALSE;
    
    $id = $from[id_key];
    if(!is_int($id))
        return FALSE;
    
    return $from;
}

function get_text_from_message($message)
{
    if(!isset($message[text_key]))
        return FALSE;
    
    $text = $message[text_key];
    
    if(!is_string($text))
        return FALSE;
    
    return $text;
}

function get_command_entities_from_message($message)
{
    if(!isset($message[entities_key]))
        return FALSE;
    
    $entities = $message[entities_key];
    
    if(!is_array($entities))
        return FALSE;
    
    return array_filter($entities,
        function ($entity)
        {
            if(!is_array($entity))
                return FALSE;
            
            if(!isset($entity[type_key]))
                return FALSE;
            
            $type = $entity[type_key];
            
            if(!is_string($type))
                return FALSE;
            
            if($type != bot_command_type)
                return FALSE;
            
            if(!isset($entity[offset_key]))
                return FALSE;
            
            $offset = $entity[offset_key];
            if(!is_int($offset))
                return FALSE;
            
            if($offset < 0)
                return FALSE;
            
            if(!isset($entity[length_key]))
                return FALSE;
            
            $length = $entity[length_key];
            if(!is_int($length))
                return FALSE;
            
            if($length < 2)
                return FALSE;
            
            return TRUE;
        });
}

function get_command_from_message($command_entities, $text)
{
    $list_command = array_map(function ($entity) use ($text)
    {
        $offset = $entity[offset_key];
        $length = $entity[length_key];
        return $command = substr($text, $offset, $length);
    },
        $command_entities);
    
    return array_filter($list_command,
        function ($command)
        {
            if(substr($command, 0, 1) != "/")
                return FALSE;
            
            return TRUE;
        });
}

function process_response($message, $chat_id, $command, $user, $api)
{
    $mention_data = build_mention($user);
    switch($command)
    {
        case "/chat_id":
        {
            response_get_chat_id($chat_id, $mention_data, $api);
            exit(0);
        }
    }
}

function build_mention($user)
{
    if(isset($user[username_key]))
        return [mention_key => "@" . $user[username_key], require_markdown_key => FALSE];
    
    $text = "[";
    if(isset($user[first_name_key]))
        if(!is_array($user[first_name_key]))
            $text .= $user[first_name_key];
    if(isset($user[last_name_key]))
        if(!is_array($user[last_name_key]))
            $text .= " " . $user[last_name_key];
    $text .= "]";
    
    $text .= "(tg://user?id=" . $user[id_key] . ")";
    return [mention_key => $text, require_markdown_key => TRUE];
}

function get_chat_id_from_message($message)
{
    if(!isset($message[chat_key]))
        return FALSE;
    
    $chat = $message[chat_key];
    
    if(!is_array($chat))
        return FALSE;
    
    if(!isset($chat[id_key]))
        return FALSE;
    
    $id = $chat[id_key];
    
    if(!is_int_string($id))
        return FALSE;
    
    return $id;
}

function build_bot_api()
{
    if(!isset($_GET[bot_id_key]))
        return FALSE;
    
    $id = $_GET[bot_id_key];
    
    if(!is_int_string($id))
        return FALSE;
    
    if(!isset($_GET[bot_pass_key]))
        return FALSE;
    
    $pass = $_GET[bot_pass_key];
    
    if(!is_string($pass))
        return FALSE;
    
    return "https://api.telegram.org/bot" . $id . ":" . $pass . "/";
}

function send_message($text, $chat_id, $mention_data, $api)
{
    $full_text = $mention_data[mention_key] . " " . $text;
    $content = [chat_id_key => $chat_id, text_key => $full_text];
    if($mention_data[require_markdown_key])
        $content[parse_mode_key] = "Markdown";
    file_get_contents($api . "sendMessage",
                      FALSE,
                      stream_context_create(['http' => ['method' => 'POST',
                                                        'header' => "Content-type: application/json",
                                                        'content' => json_encode($content, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG)]]));
}

function response_get_chat_id($chat_id, $mention_data, $api)
{
    send_message($chat_id, $chat_id, $mention_data, $api);
}