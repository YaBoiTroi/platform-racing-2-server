<?php


// set right room
function client_set_right_room($socket, $data)
{
    $player = $socket->getPlayer();
    if (isset($player->right_room)) {
        $player->right_room->removePlayer($player);
    }
    if ($data !== 'none' && isset($player->game_room)) {
        $player->game_room->removePlayer($player);
    }
    if ($data !== 'none' && strpos($data, '`') === false) {
        global ${$data.'_room'};
        if (${$data.'_room'} != null) {
            ${$data.'_room'}->addPlayer($player);
        }
    }
}


// set the chat room
function client_set_chat_room($socket, $data)
{
    $player = $socket->getPlayer();
    $group = $player->group;
    if (isset($player->chat_room)) {
        $player->chat_room->removePlayer($player);
    }
    if (($data === 'mod' && $group < 2) || ($data === 'admin' && ($group < 3 || $player->user_id === FRED))) {
        $data = 'none';
        $player->write('message`You lack the power to enter this room.');
    }
    if (is_obscene($data)) {
        $data = 'none';
        $player->write('message`Keep the room names clean, pretty please. :)');
    }
    if ($data !== 'none') {
        $chat_room = get_chat_room($data);
        $chat_room->addPlayer($player);
    }
}


// set game room
function client_set_game_room($socket)
{
    $player = $socket->getPlayer();
    if (isset($player->game_room)) {
        $player->game_room->removePlayer($player);
    }
}


// checks if a page is supposed to be highlighted
function client_refresh_highlights($socket)
{
    $player = $socket->getPlayer();
    if (isset($player->right_room)) {
        $player->right_room->refreshHighlights($player);
    }
}


// join a slot in a course box
function client_fill_slot($socket, $data)
{
    list($course_id, $slot, $page) = explode('`', $data);
    $player = $socket->getPlayer();
    if (isset($player->right_room)) {
        $player->right_room->fillSlot($player, $course_id, $slot, (int) $page);
    }
}


// confirm a slot in a course box
function client_confirm_slot($socket)
{
    $player = $socket->getPlayer();
    $course_box = $player->course_box;
    if (isset($player->right_room) && isset($course_box)) {
        $course_box->confirmSlot($player);
    }
}


// clear a slot in a course box
function client_clear_slot($socket)
{
    $player = $socket->getPlayer();
    $course_box = $player->course_box;
    if (isset($player->right_room) && isset($course_box)) {
        $course_box->clearSlot($player);
    }
}


// force the players who have not confirmed out so the rest can play
function client_force_start($socket)
{
    $player = $socket->getPlayer();
    $course_box = $player->course_box;
    if (isset($player->right_room) && isset($course_box)) {
        $course_box->forceStart();
    }
}


// returns info for the customize page
function client_get_customize_info($socket)
{
    $player = $socket->getPlayer();
    $player->sendCustomizeInfo();
}


// sets info for the character
function client_set_customize_info($socket, $data)
{
    $player = $socket->getPlayer();
    $player->setCustomizeInfo($data);
}


// sends a chat message
function client_chat($socket, $data)
{
    $player = $socket->getPlayer();
    new \pr2\multi\ChatMessage($player, $data);
}


// get a list of the players that are online
function client_get_online_list($socket)
{
    global $player_array;
    foreach ($player_array as $player) {
        $hats = count($player->hat_array) - 1;
        $group = group_str($player);
        $socket->write("addUser`$player->name`$group`$player->active_rank`$hats");
    }
}


// get a list of the top chat rooms
function client_get_chat_rooms($socket)
{
    global $chat_room_array;

    $temp_array = array_merge($chat_room_array);
    usort($temp_array, 'sort_chat_room_array');
    $str = 'setChatRoomList';
    $count = count($temp_array);
    $count = $count > 8 ? 8 : $count;

    for ($i = 0; $i < $count; $i++) {
        $chat_room = $temp_array[$i];
        $room_name = $chat_room->chat_room_name;
        $players = count($chat_room->player_array);
        $lang = $players !== 1 ? 'players' : 'player';
        $str .= "`$room_name - $players $lang";
    }

    if ($str === 'setChatRoomList') {
        $str .= '`No one is chatting. :(';
    }

    $socket->write($str);
}


// add a user to your following array
function client_follow_user($socket, $data)
{
    $player = $socket->getPlayer();
    $new_follow = name_to_player($data);
    if (isset($new_follow)) {
        array_push($player->following_array, $new_follow->user_id);
    }
}


// remove a user from your following array
function client_unfollow_user($socket, $data)
{
    $player = $socket->getPlayer();
    $follow = name_to_player($data);
    if (isset($player)) {
        $index = @array_search($follow->user_id, $player->following_array);
        if ($index !== false) {
            array_splice($player->following_array, $index, 1);
        }
    }
}


// add a user to your friends array
function client_add_friend($socket, $data)
{
    $player = $socket->getPlayer();
    $new_friend = name_to_player($data);
    if (isset($new_friend)) {
        array_push($player->friends_array, $new_friend->user_id);
    }
}


// remove a user from your friends array
function client_remove_friend($socket, $data)
{
    $player = $socket->getPlayer();
    $friend = name_to_player($data);
    if (isset($player)) {
        $index = @array_search($friend->user_id, $player->friends_array);
        if ($index !== false) {
            array_splice($player->friends_array, $index, 1);
        }
    }
}


// add a user to your ignored array
function client_ignore_user($socket, $data)
{
    $player = $socket->getPlayer();
    $ignored_player = name_to_player($data);
    if (isset($ignored_player) && $ignored_player !== $player) {
        array_push($player->ignored_array, $ignored_player->user_id);
    }
}


// remove a user from your ignored array
function client_unignore_user($socket, $data)
{
    $player = $socket->getPlayer();
    $ignored_player = name_to_player($data);
    if (isset($player)) {
        $index = @array_search($ignored_player->user_id, $player->ignored_array);
        if ($index !== false) {
            array_splice($player->ignored_array, $index, 1);
        }
    }
}


// unlock the kong set (ant set)
function client_award_kong_outfit($socket)
{
    $player = $socket->getPlayer();
    $player->awardKongOutfit();
}


// increment used rank tokens
function client_use_rank_token($socket)
{
    $player = $socket->getPlayer();
    $player->useRankToken();
}


// decrement used rank tokens
function client_unuse_rank_token($socket)
{
    $player = $socket->getPlayer();
    $player->unuseRankToken();
}
