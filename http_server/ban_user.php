<?php

header("Content-type: text/plain");

require_once GEN_HTTP_FNS;
require_once QUERIES_DIR . '/bans.php';
require_once QUERIES_DIR . '/campaigns.php';
require_once QUERIES_DIR . '/level_prizes.php';
require_once QUERIES_DIR . '/mod_actions.php';
require_once QUERIES_DIR . '/new_levels.php';

$ban_name = default_post('banned_name');
$duration = (int) default_post('duration', 60);
$reason = default_post('reason', '');
$log = default_post('record', '');
$using_mod_site = default_post('using_mod_site', 'no');
$redirect = default_post('redirect', 'no');
$type = default_post('type', 'both');
$scope = default_post('scope', 'g');
$level_id = (int) default_post('level_id', 0);
$force_ip = default_post('force_ip', '');
$ip = get_ip();

$ret = new stdClass();
$ret->success = false;

try {
    // rate limiting
    rate_limit('ban-'.$ip, 5, 1);

    // POST check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // sanity check: was a name passed to the script?
    if (is_empty($ban_name)) {
        throw new Exception('Invalid name provided.');
    }

    // connect
    $pdo = pdo_connect();

    // check for permission
    $mod = check_moderator($pdo);

    // get variables from the mod variable
    $mod_uid = (int) $mod->user_id;
    $mod_name = $mod->name;

    // limit ban length
    $max_length = $mod->trial_mod ? 86400 : 31536000;
    $duration = $duration > $max_length ? $max_length : $duration;
    $ends = time() + $duration;

    // throttle bans
    $max_bans = $mod->trial_mod ? 30 : 101;
    $throttle = throttle_bans($pdo, $mod_uid);
    if ($throttle->recent_ban_count > $max_bans) {
        throw new Exception("You have reached the cap of $max_bans bans per hour.");
    }

    // get the banned user's info
    $target = user_select_by_name($pdo, $ban_name, true);
    if ($target === false) {
        throw new Exception("The user you're trying to ban doesn't exist.");
    }

    // make some variables
    $banned_power = $target->power;
    $ban_uid = (int) $target->user_id;
    if (!empty($force_ip) && filter_var($force_ip, FILTER_VALIDATE_IP) && !$mod->trial_mod) {
        $ban_ip = $force_ip;
    } else {
        $ban_ip = $target->ip;
        $force_ip = '';
    }

    // throw out non-banned info, set ban types
    $is_ip = $is_acc = 0;
    switch ($type) {
        case 'both':
            $is_ip = $is_acc = 1;
            break;
        case 'account':
            $is_acc = 1;
            break;
        case 'ip':
            $is_ip = 1;
            break;
        default:
            throw new Exception("Invalid ban type specified.");
        break;
    }

    // determine scope
    $scope = $scope === 'social' ? 's' : 'g';

    // permission check
    /*if ($banned_power >= 2 || $mod->power < 2) {
        throw new Exception("You lack the power to ban $ban_name.");
    }*/

    // don't ban guest accounts, just the ip
    if ($banned_power == 0) {
        $ban_uid = 0;
        $ban_name = '';
    }

    // ban the user
    // phpcs:disable
    $ban_id = (int) ban_insert($pdo, $ban_ip, $ban_uid, $mod_uid, $ends, $reason, $log, $ban_name, $mod_name, $is_ip, $is_acc, $scope);
    // phpcs:enable
    if ($ban_id === 0) {
        throw new Exception('Could not record ban.');
    }

    // remove level if a level ID is specified
    if (!empty($level_id)) {
        remove_level($pdo, $mod, $level_id);
    }

    // make things pretty
    $disp_duration = format_duration($duration);
    $disp_reason = is_empty($reason) ? 'no reason given' : "reason: $reason";

    // make account/ip ban and scope detection pretty courtesy of data_fns.php
    $is_account_ban = check_value($is_acc, 1);
    $is_ip_ban = check_value($is_ip, 1);
    $ban_scope = check_value($scope, 'g', 'game', 'social');

    // make expire time pretty
    $disp_expire_time = date('Y-m-d H:i:s', $ends);

    // record the ban in the action log
    $msg = "$mod_name banned $ban_name from $ip "
        ."{ban_id: $ban_id, "
        ."duration: $disp_duration, "
        ."account_ban: $is_account_ban, "
        ."ip_ban: $is_ip_ban, "
        ."scope: $ban_scope, "
        ."expire_time: $disp_expire_time, "
        ."$disp_reason}";
    mod_action_insert($pdo, $mod_uid, $msg, 'ban', $ip);

    if ($using_mod_site === 'yes' && $redirect === 'yes') {
        $url_ip = urlencode($force_ip);
        header("Location: /mod/player_info.php?user_id=$ban_uid&force_ip=$url_ip");
        die();
    } else {
        $ban_scope = $scope === 's' ? ' socially' : '';
        $disp_name = htmlspecialchars($ban_name, ENT_QUOTES);
        $guest_msg = "Guest [$ban_ip] has been$ban_scope banned for $duration seconds.";
        $user_msg = "$disp_name has been$ban_scope banned for $duration seconds.";
        $ret->success = true;
        $ret->ban_id = $ban_id;
        $ret->message = $ban_uid === 0 ? $guest_msg : $user_msg;
    }
} catch (Exception $e) {
    $ret->error = $e->getMessage();
} finally {
    die(json_encode($ret));
}
