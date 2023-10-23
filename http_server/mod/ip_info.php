<?php

require_once GEN_HTTP_FNS;
require_once HTTP_FNS . '/ip_api_fns.php';
require_once HTTP_FNS . '/output_fns.php';
require_once HTTP_FNS . '/pages/player_search_fns.php';
require_once QUERIES_DIR . '/bans.php';
require_once QUERIES_DIR . '/ip_validity.php';
require_once QUERIES_DIR . '/recent_logins.php';

$ip = default_get('ip', '');

try {
    // rate limiting
    rate_limit('ip-search-'.$ip, 60, 10, 'Wait a minute at most before searching again.');
    rate_limit('ip-search-'.$ip, 30, 5);

    // connect
    $pdo = pdo_connect();

    // make sure you're a mod
    $staff = is_staff($pdo, token_login($pdo), false, true);

    // header
    output_header('IP Info', $staff->mod, $staff->admin);

    // check for trial mod
    if ($staff->trial) {
        throw new Exception('You lack the power to access this resource.');
    }

    // we can dance if we want to, we can leave your friends behind
    $html_ip = htmlspecialchars($ip, ENT_QUOTES);

    // show textbox
    echo '<form>'
        ."IP: <input type='text' name='ip' value='$html_ip'> "
        .'<input type="submit" value="Search"></form>';

    // possibly stop here (no IP passed)
    if (empty($ip)) {
        throw new Exception();
    }

    // sanity check: is a value entered for IP?
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new Exception("Invalid IP address entered.");
    }

    // get IP info
    $ip_geo = http_get_contents($IP_API_LINK_2 . $ip, ['User-Agent: keycdn-tools:https://pr2hub.com']);
    if ($ip_geo !== false) {
        $ip_geo = json_decode($ip_geo);
    }

    // check if it's valid
    $skip_fanciness = $ip_geo !== false ? $ip_geo->status !== 'success' : true;

    // if the data retrieval was successful, define our fancy variables
    if ($skip_fanciness === false) {
        $ip_geo = $ip_geo->data->geo;

        // make some variables
        $html_host = htmlspecialchars($ip_geo->host, ENT_QUOTES);
        $html_dns = htmlspecialchars($ip_geo->rdns, ENT_QUOTES);
        $html_isp = htmlspecialchars($ip_geo->isp, ENT_QUOTES);
        $url_isp = 'https://www.google.com/search?q=' . htmlspecialchars(urlencode($ip_geo->isp), ENT_QUOTES);
        $html_city = htmlspecialchars($ip_geo->city, ENT_QUOTES);
        $html_region = htmlspecialchars($ip_geo->region_name, ENT_QUOTES);
        $html_country = htmlspecialchars($ip_geo->country_name, ENT_QUOTES);
        $html_country_code = htmlspecialchars($ip_geo->country_code, ENT_QUOTES);

        // make a location string out of the location data
        $loc = '';
        $loc = !is_empty($html_city) ? $loc . $html_city . ', ' : $loc;
        $loc = !is_empty($html_region) ? $loc . $html_region . ', ' : $loc;
        $loc = !is_empty($html_country) ? $loc . $html_country . ' (' . $html_country_code . ')' : $loc;

        // update missing country code if needed
        $valid_ip = filter_var($ip, FILTER_VALIDATE_IP);
        if ($valid_ip && !is_empty($ip_geo->country_code) && strlen($ip_geo->country_code) === 2) {
            recent_logins_update_missing_country($pdo, $ip, $ip_geo->country_code);
        }
    }

    // determine IP validity
    $ip_validity = ip_validity_select($pdo, $ip, true);
    $valid = !empty($ip_validity) ? (bool) (int) $ip_validity->valid : null;
    $text = isset($valid) ? ($valid ? 'Valid' : 'Invalid') : 'No record for this IP address.';
    $color = isset($valid) ? ($text === 'Valid' ? 'green' : 'red') : '#c2b613';

    // determine if expired (if entry greater than 2 months old)
    $exp_time = $ip_validity->time + 5270400;
    $exp_date = date('Y-m-d H:i:s', $exp_time);
    $rel_exp_time = (time() > $exp_time ? 'd ' : 's in ') . format_duration($exp_time - time());

    // output IP validity
    $manage_link = "<a href='manage_ip_validity.php?ip=$html_ip'>manage</a>";
    $expire_text = "<br><span style='font-style: italic' title='Expire Date: $exp_date'>(expire$rel_exp_time)</span>";
    echo "<p>Validity: <b><span style='color: $color'>$text</span></b> <i>($manage_link)</i>";
    echo (isset($valid) ? $expire_text : '') . '</p>';

    // if the geo data retrieval was successful, display our fancy variables
    if (!$skip_fanciness) {
        echo '<p>';
        echo empty($html_host) ? '' : "Host: $html_host";
        echo empty($html_dns) ? '' : "<br>DNS: $html_dns";
        echo empty($html_isp) ? '' : "<br>ISP: <a href='$url_isp' target='_blank'>$html_isp</a>";
        echo empty($loc) ? '' : "<br>Location: $loc";
        echo '</p>';
    }

    // check if they are currently banned
    $banned = 'No';
    $ban = check_if_banned($pdo, 0, $ip, 'b', false);

    // give some more info on the most severe ban (g > s scope, latest exp time) currently in effect if there is one
    if (!empty($ban)) {
        $ban_id = $ban->ban_id;
        $reason = htmlspecialchars($ban->reason, ENT_QUOTES);
        $ban_end_date = date("F j, Y, g:i a", $ban->expire_time);
        $scope = $ban->scope === 's' ? 'socially banned' : 'banned';
        $banned = "<a href='/bans/show_record.php?ban_id=$ban_id'>Yes.</a>"
            ." This IP is $scope until $ban_end_date. Reason: $reason";
    }

    // look for all historical bans given to this ip address
    $ip_bans = bans_select_by_ip($pdo, $ip);
    $ip_ban_count = (int) count($ip_bans);
    $ip_ban_list = create_ban_list($ip_bans);
    $ip_lang = $ip_ban_count !== 1 ? 'times' : 'time';

    // echo ban status
    echo "<p>Currently banned: $banned</p>"
        ."<p>This IP has been banned $ip_ban_count $ip_lang.</p>"
        .$ip_ban_list;

    // get users associated with this IP
    $users = users_select_by_ip($pdo, $ip);
    $user_count = count($users);
    $res = $user_count !== 1 ? 'accounts are' : 'account is';

    // echo user count
    echo "$user_count $res associated with the IP address \"$html_ip\".<br><br>";

    foreach ($users as $user) {
        $user_id = (int) $user->user_id;
        $name = htmlspecialchars($user->name, ENT_QUOTES);
        $power_color = get_group_info($user)->color;
        $active = date('j/M/Y', (int) $user->time);

        // echo results
        echo "<a href='/mod/player_info.php?user_id=$user_id' style='color: #$power_color'>$name</a>
            | Last Active: $active<br>";
    }
} catch (Exception $e) {
    output_error_page($e->getMessage(), @$staff);
} finally {
    output_footer();
}
