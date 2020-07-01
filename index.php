<?php
require_once("functions.php");

$api = build_bot_api();
if(!$api)
{
    http_response_code(400);
    exit(0);
}

if(!isset($_GET[bot_name_key]))
{
    http_response_code(400);
    exit(0);
}

$name = $_GET[bot_name_key];
if(!is_string($name))
{
    http_response_code(400);
    exit(0);
}

$content = file_get_contents("php://input");
$update = json_decode($content, TRUE);
if(!$update)
{
    http_response_code(400);
    exit(0);
}
process_update($api, $name, $update);