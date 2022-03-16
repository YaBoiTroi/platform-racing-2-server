<?php


// get priors for a user
function client_view_priors($socket, $data)
{
    $player = $socket->getPlayer();
    get_priors($player, $data);
}


// kick a player
function client_kick($socket, $data)
{
    global $is_ps, $guild_owner, $server_name;
    $name = $data;

    // get players
    $kicked = name_to_player($name);
    $mod = $socket->getPlayer();

    // safety first
    $safe_kname = htmlspecialchars($name, ENT_QUOTES);

    // if they're a mod and not the person being kicked, proceed
    if ($mod->group >= 2 && $kicked != $mod) {
        // check if online and get user data if not
        $kicked_online = isset($kicked);
        if (!$kicked_online) {
            $kicked = db_op('user_select_by_name', array($name, true));
            if ($kicked === false) {
                $mod->write("message`Error: Could not find a user with the name \"$safe_kname\".");
                return false;
            } else {
                $kicked->group = $kicked->power;
                $kicked->server_owner = false;
                if ($kicked->user_id == $guild_owner) {
                    $kicked->server_owner = true;
                    $kicked->group = 3;
                }
            }
        } elseif ($kicked->temp_mod) { // demote if a temp mod
            $kicked->group = 1;
            $kicked->temp_mod = false;
            $kicked->write('demoteMod`');
        }

        // remove existing kicks, then kick
        if (\pr2\multi\ServerBans::isBanned($name) === true) {
            \pr2\multi\ServerBans::remove($name);
        }

        // kick the user
        if (($kicked->group < 2 || $mod->server_owner) && !$kicked->server_owner) {
            // add server ban
            \pr2\multi\ServerBans::add($name, $kicked->ip);

            // let people know that the player is kicking someone
            if (isset($mod->chat_room)) {
                $mod_url = userify($mod, $mod->name);
                $kicked_url = userify($kicked, $name);
                $message = "systemChat`$mod_url has kicked $kicked_url from this server for 30 minutes.";
                $mod->chat_room->sendChat($message);
            }

            // disconnect them
            if ($kicked_online === false) {
                $mod->write("message`$safe_kname is not currently on this server, but the kick was applied anyway.");
            } else {
                // kick others on this IP
                \pr2\multi\ServerBans::applyToIP($kicked->ip);
                $mod->write("message`$safe_kname has been kicked from this server for 30 minutes.");
            }

            // log the action if it's on a public server
            if (!$is_ps) {
                $message = "$mod->name kicked $name from $server_name from $mod->ip.";
                db_op('mod_action_insert', array($mod->user_id, $message, 'kick', $mod->ip));
            }
        } else {
            $mod->write("message`Error: You lack the power to kick $safe_kname.");
        }
    } elseif ($kicked == $mod) {
        $mod->write("message`Error: You can't kick yourself out of a server, silly!");
    } else {
        $mod->write("message`Error: You lack the power to kick $safe_kname.");
    }
}


// unkick a player
function client_unkick($socket, $data)
{
    global $is_ps, $server_name;
    $name = $data;

    // get some info
    $mod = $socket->getPlayer();
    $unkicked_name = htmlspecialchars($name, ENT_QUOTES);

    // if the player actually has the power to do what they're trying to do, then do it
    if (($mod->group >= 2 && $mod->temp_mod === false) || $mod->server_owner === true) {
        if (\pr2\multi\ServerBans::isBanned($name) === true) {
            \pr2\multi\ServerBans::remove($name);

            // unkick them, yo
            $mod->write("message`$unkicked_name has been unkicked! Hooray for second chances!");

            // log the action if it's on a public server
            if (!$is_ps) {
                $message = "$mod->name unkicked $name from $server_name from $mod->ip.";
                db_op('mod_action_insert', array($mod->user_id, $message, 'unkick', $mod->ip));
            }
        } else {
            $mod->write("message`Error: $unkicked_name isn't kicked.");
        }
    } else {
        $mod->write("message`Error: You lack the power to unkick $unkicked_name.");
    }
}


// administer a chat warning
function client_warn($socket, $data)
{
    global $guild_owner;
    list($name, $num) = explode("`", $data);

    // get player info
    $warned = name_to_player($name);
    $mod = $socket->getPlayer();

    // safety first
    $num = (int) $num;
    $safe_wname = htmlspecialchars($name, ENT_QUOTES);

    // warning number and duration
    $num = limit($num, 1, 3);
    $w_str = $num !== 1 ? 'warnings' : 'warning';
    $time = $num === 3 ? 120 : $num * 30;
    $time_str = format_duration($time);

    // if they're a mod, and the user is on this server, warn the user
    if ($mod->group >= 2 && $warned != $mod) {
        $warned_online = isset($warned);
        if (!$warned_online) {
            $warned = db_op('user_select_by_name', array($name, true));
            if ($warned === false) {
                $mod->write("message`Error: Could not find a user with the name \"$safe_wname\".");
                return false;
            } else {
                $warned->group = $warned->power;
                $warned->server_owner = false;
                if ($warned->user_id == $guild_owner) {
                    $warned->server_owner = true;
                    $warned->group = 3;
                }
            }
        }

        // remove existing mutes, then mute
        if (\pr2\multi\Mutes::isMuted($name) === true) {
            \pr2\multi\Mutes::remove($name);
        }

        // warn the user if they're not a mod
        if (($warned->group < 2 || $mod->server_owner) && !$warned->server_owner) {
            \pr2\multi\Mutes::add($name, $warned->ip, $time);
            if ($warned_online === false) {
                $mod->write("message`$safe_wname is not currently on this server, but the mute was applied anyway.");
            }

            // tell the world
            if (isset($mod->chat_room) && $mod->group >= 2 && $mod->group > $warned->group) {
                $mod_url = userify($mod, $mod->name);
                $warned_url = userify($warned, $name);
                $msg = "$mod_url has given $warned_url $num $w_str. They have been muted from the chat for $time_str.";
                $mod->chat_room->sendChat("systemChat`$msg");
            }
        } else {
            $mod->write("message`Error: You lack the power to warn $safe_wname.");
        }
    } elseif ($warned == $mod) {
        $mod->write("message`Error: You can't warn yourself, silly!");
    } else {
        $mod->write("message`Error: You lack the power to warn $safe_wname.");
    }
}


// unmute a player
function client_unmute($socket, $data)
{
    $name = $data;

    // get some info
    $mod = $socket->getPlayer();
    $unmuted_name = htmlspecialchars($name, ENT_QUOTES);

    // if the player actually has the power to do what they're trying to do, then do it
    if (($mod->group >= 2 && $mod->temp_mod === false) || $mod->server_owner === true) {
        if (\pr2\multi\Mutes::isMuted($name) === true) {
            \pr2\multi\Mutes::remove($name);

            // unmute them, yo
            $mod->write("message`$unmuted_name has been unmuted! Hooray for speech!");
        } else {
            $mod->write("message`Error: $unmuted_name isn't muted.");
        }
    } else {
        $mod->write("message`Error: You lack the power to unmute $unmuted_name.");
    }
}


// ban a player
function client_ban($socket, $data)
{
    list($banned_name, $seconds, $scope, $ban_id, $reason) = explode("`", $data);

    // get player info
    $mod = $socket->getPlayer();
    $banned = name_to_player($banned_name);

    // reason
    $reason = htmlspecialchars($reason, ENT_QUOTES);
    $reason = $reason === '' ? 'There was no reason given' : "Reason: $reason";

    // make friendly time
    $duration = format_duration($seconds);

    // tell the world
    if ($mod->group >= 2 && isset($banned) && ($banned->group < 2 || $banned->temp_mod)) {
        // check for valid ban
        $ban = db_op('ban_select', array((int) $ban_id));
        if ($ban->banned_ip != $banned->ip || $ban->banned_user_id != $banned->user_id) {
            $mod->write('message`Error: Invalid ban ID sent to server.');
            return;
        }
        
        $mod_url = userify($mod, $mod->name);
        $name_url = userify($banned, $banned_name);
        $scope_lang = $scope === 'game' ? 'banned' : 'socially banned';

        // send notif to chat
        if (isset($mod->chat_room)) {
            $log = urlify('https://pr2hub.com/bans', 'the ban log');
            $msg = "$mod_url has $scope_lang $name_url for $duration. $reason. This ban has been recorded on $log.";
            $mod->chat_room->sendChat("systemChat`$msg");
        }

        // demote if a temp mod
        if ($banned->temp_mod) {
            $banned->group = 1;
            $banned->temp_mod = false;
            $banned->write('demoteMod`');
        }

        // increment social ban expire time for all users on this IP or remove them from the server
        global $player_array;
        foreach ($player_array as $player) {
            if ($banned->ip === $player->ip) {
                if ($scope === 'social') {
                    // if this isn't the most severe, it will update at the top of the minute
                    $player->sban_id = (int) $ban_id;
                    $player->sban_exp_time = time() + $seconds;
                } else {
                    $player->remove();
                }
            }
        }
    }
}


// promote a player to a moderator
function client_promote_to_moderator($socket, $data)
{
    list($name, $type) = explode("`", $data);

    // get player info
    $admin = $socket->getPlayer();
    $promoted = name_to_player($name);

    // safety first
    $safe_pname = htmlspecialchars($name, ENT_QUOTES);

    // if they're an admin and not a server owner, continue with the promotion (1st line of defense)
    if ($admin->group >= 3 && $admin->server_owner === false) {
        $result = promote_to_moderator($name, $type, $admin, $promoted);

        $mod_power = null;
        switch ($type) {
            case 'temporary':
                $mod_power = 0;
                $reign_time = 'hours';
                break;
            case 'trial':
                $mod_power = 1;
                $reign_time = 'days';
                break;
            case 'permanent':
                $reign_time = '1000 years';
                break;
        }

        if (isset($admin->chat_room) && (isset($promoted) || $type !== 'temporary') && $result === true) {
            $admin_url = userify($admin, $admin->name);
            $promoted_url = userify($promoted, $name, 2, $mod_power);
            $mod_guide = urlify('https://jiggmin2.com/forums/showthread.php?tid=12', 'moderator guidelines');

            $msg = "$admin_url has promoted $promoted_url to a $type moderator! "
                ."May they reign in $reign_time of peace and prosperity! Make sure you read the $mod_guide.";
            $admin->chat_room->sendChat("systemChat`$msg", $admin->user_id);
        }
    } else { // if they're not an admin, tell them
        $admin->write("message`Error: You lack the power to promote $safe_pname to a $type moderator.");
    }
}


// demote a moderator
function client_demote_moderator($socket, $name)
{
    // get player info
    $admin = $socket->getPlayer();
    $demoted = name_to_player($name);

    if ($admin->group === 3 && $admin->server_owner === false) {
        demote_mod($name, $admin, $demoted);
    }
}
