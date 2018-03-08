<?php

header("Content-type: text/plain");

require_once __DIR__ . '/../fns/all_fns.php';
require_once __DIR__ . '/../fns/Encryptor.php';

$level_id = (int) default_val($_GET['courseID'], 0);
$hash = find_no_cookie('hash', '');

try {
    // rate limiting
    rate_limit('level-pass-'.$ip, 3, 2);

    // sanity
    if (is_empty($level_id, false) || is_empty($hash)) {
        throw new Exception('Invalid input. ' . join(', ', $_GET));
    }

    // connect
    $db = new DB();

    // check their login
    $user_id = token_login($db, false);

    // more rate limiting
    rate_limit('level-pass-'.$user_id, 3, 2);

    // check the pass
    $hash2 = sha1($hash . $LEVEL_PASS_SALT);
    $match = $db->grab('isMatch', 'level_check_pass', array($level_id, $hash2));
    if (!$match) {
        sleep(1);
    }

    // return info
    $result = new stdClass();
    $result->access = $match;
    $result->level_id = $level_id;
    $result->user_id = $user_id;
    $str_result = json_encode($result);

    // set up encryptor
    $encryptor = new Encryptor();
    $encryptor->set_key($LEVEL_PASS_KEY);
    $enc_result = $encryptor->encrypt($str_result, $LEVEL_PASS_IV);

    echo 'result=' . urlencode($enc_result);
} catch (Exception $e) {
    echo 'error=' . urlencode($e->getMessage());
}
