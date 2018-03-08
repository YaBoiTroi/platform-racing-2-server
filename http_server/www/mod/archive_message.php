<?php

require_once __DIR__ . '/../../fns/all_fns.php';
require_once __DIR__ . '/../../fns/output_fns.php';

$message_id = (int) default_val($_GET['message_id'], 0);
$ip = get_ip();

try {
    // rate limiting
    rate_limit('mod-archive-message-'.$ip, 3, 2);

    // connect
    $db = new DB();

    // make sure you're a moderator
    $mod = check_moderator($db);
} catch (Exception $e) {
    $error = $e->getMessage();
    output_header("Error");
    echo "Error: $error";
    output_footer();
    die();
}

try {
    // archive the message
    $result = $db->query(
        "UPDATE messages_reported
							SET archived = 1
							WHERE message_id = '$message_id'
							LIMIT 1"
    );
    if (!$result) {
        throw new Exception('Could not archive the message.');
    }

    // action log
    $name = $mod->name;
    $ip = $mod->ip;

    // record the change
    $db->call('mod_action_insert', array($mod->user_id, "$name archived the report of PM $message_id from $ip", $mod->user_id, $ip));

    // tell the sorry saps trying to debug
    $ret = new stdClass();
    $ret->success = true;
    $ret->message_id = $message_id;
    echo json_encode($ret);
} catch (Exception $e) {
    $ret = new stdClass();
    $ret->success = false;
    $ret->error = $e->getMessage();
    $ret->message_id = $message_id;
    echo json_encode($ret);
}
