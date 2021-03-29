<?php


function admin_user_update($pdo, $user_id, $name, $email, $guild, $verified)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET name = :name,
               email = :email,
               guild = :guild,
               verified = :verified
         WHERE user_id = :user_id
        ');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':guild', $guild, PDO::PARAM_INT);
    $stmt->bindValue(':verified', $verified, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not update user data.");
    }

    return true;
}


function id_to_name($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT name
          FROM users
         WHERE user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query id_to_name.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        if ($suppress_error === false) {
            throw new Exception('id_to_name: Could not find a user with that ID.');
        } else {
            return false;
        }
    }

    return $user->name;
}


function name_to_id($pdo, $name, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT user_id
          FROM users
         WHERE name = :name
         LIMIT 1
    ');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not perform query name_to_id.");
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if ($user === false) {
        if ($suppress_error === false) {
            throw new Exception('name_to_id: Could not find a user with that name.');
        } else {
            return false;
        }
    }

    return $user->user_id;
}


function user_apply_temp_pass($pdo, $user_id)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET pass_hash = temp_pass_hash,
               temp_pass_hash = NULL
         WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not apply temporary password.');
    }

    return $result;
}


function user_insert($pdo, $name, $pass_hash, $ip, $time, $email)
{
    $stmt = $pdo->prepare('
        INSERT INTO users
           SET name = :name,
               pass_hash = :pass_hash,
               register_ip = :ip,
               ip = :ip,
               time = :time,
               register_time = :time,
               email = :email
    ');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':pass_hash', $pass_hash, PDO::PARAM_STR);
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':time', $time, PDO::PARAM_INT);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not create a new account.');
    }

    return $result;
}


function user_select_by_name($pdo, $name, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT user_id,
               name,
               email,
               register_ip,
               ip,
               time,
               register_time,
               power,
               trial_mod,
               verified,
               status,
               read_message_id,
               guild,
               coins,
               server_id
          FROM users
          WHERE name = :name
          LIMIT 1
    ');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query user_select_by_name.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user) && $suppress_error === false) {
        throw new Exception('Could not find a user with that name.');
    }

    return $user;
}


function user_select_coins($pdo, $user_id)
{
    $stmt = $pdo->prepare('SELECT coins FROM users WHERE user_id = :user_id LIMIT 1');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();
    if ($result === false) {
        throw new Exception('Could not retrieve number of coins.');
    }

    return (int) $stmt->fetchColumn();
}


function user_select_expanded($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT p.*,
               e.epic_hats,
               e.epic_heads,
               e.epic_bodies,
               e.epic_feet,
               u.name,
               u.power,
               u.trial_mod,
               u.verified,
               u.status,
               u.ip,
               u.time,
               u.register_time,
               u.guild,
               u.coins,
               u.server_id,
               rt.used_tokens
          FROM users u
          LEFT JOIN pr2 p ON u.user_id = p.user_id
          LEFT JOIN rank_tokens rt ON rt.user_id = p.user_id
          LEFT JOIN epic_upgrades e ON u.user_id = e.user_id
         WHERE u.user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query user_select_expanded.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        if ($suppress_error === false) {
            throw new Exception('user_select_expanded: Could not find a user with that ID.');
        } else {
            return false;
        }
    }

    return $user;
}


function user_select_full_by_name($pdo, $name)
{
    $stmt = $pdo->prepare('
        SELECT *
          FROM users
         WHERE name = :name
         LIMIT 1
    ');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query user_select_full_by_name.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        throw new Exception('That username / password combination was not found.');
    }

    return $user;
}


function user_select_guest($pdo)
{
    $stmt = $pdo->prepare('
        SELECT user_id,
               name,
               email,
               register_ip,
               ip,
               time,
               register_time,
               power,
               trial_mod,
               verified,
               status,
               read_message_id,
               guild,
               server_id
          FROM users
         WHERE power = 0
           AND status = "offline"
         LIMIT 1
    ');
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query user_select_guest.');
    }

    $guest = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($guest)) {
        $err = 'Could not find a suitable guest account. Try again later, or create a new account instead.';
        throw new Exception($err);
    }

    return $guest;
}


function user_select_level_plays($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
          SELECT SUM(play_count) as total_play_count
            FROM levels
           WHERE user_id = :user_id
        GROUP BY user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        if ($suppress_error === false) {
            throw new Exception('Could not count the number of plays for this user.');
        }
        return 0;
    }

    $row = $stmt->fetch(PDO::FETCH_OBJ);
    return $row ? $row->total_play_count : 0;
}


function user_select_mod($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
         SELECT user_id,
                name,
                email,
                register_ip,
                ip,
                time,
                register_time,
                power,
                trial_mod,
                verified,
                status,
                read_message_id,
                guild
            FROM users
            WHERE user_id = :user_id
            AND power >= 2
            LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query user_select_mod.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user) && $suppress_error === false) {
        throw new Exception('Could not find a mod with that ID.');
    }

    return $user;
}


function user_select_name_active_power($pdo, $user_id, $suppress_error = false)
{
    $count = (int) $count;
    $stmt = $pdo->prepare('
          SELECT name, time, power, trial_mod
            FROM users
           WHERE user_id = :user_id
           LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_select_name_active_power.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        if ($suppress_error == false) {
            throw new Exception('Could not find any users with that ID.');
        } else {
            return false;
        }
    }

    return $user;
}


function user_select_name_guild_power($pdo, $user_id)
{
    $stmt = $pdo->prepare('
        SELECT name, guild, power, trial_mod
          FROM users
         WHERE user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not perform query user_select_name_guild_power.");
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        throw new Exception("Could not find a user with that ID.");
    }

    return $user;
}


function user_select_name_and_power($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT name, power, trial_mod
          FROM users
         WHERE user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not perform query user_select_name_and_power.");
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        if ($suppress_error === true) {
            return false;
        }
        throw new Exception("Could not find a user with that ID.");
    }

    return $user;
}


function user_select_power($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT power, trial_mod
          FROM users
         WHERE user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not perform query user_select_power.");
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        if ($suppress_error === false) {
            throw new Exception("Could not find a user with that ID.");
        } else {
            return false;
        }
    }

    return $user->power . ',' . $user->trial_mod;
}


function user_select_power_by_name($pdo, $name, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT power, trial_mod
          FROM users
         WHERE name = :name
         LIMIT 1
    ');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not perform query user_select_power_by_name.");
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user)) {
        if ($suppress_error === false) {
            throw new Exception("Could not find a user with that name.");
        }
        return false;
    }

    return $user->power . ($user->trial_mod == 1 ? ',' . $user->trial_mod : '');
}


function user_select_server_id($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT
          server_id
        FROM
          users
        WHERE
          user_id = :user_id
        LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        if ($suppress_error === false) {
            throw new Exception('Could not perform query user_select_server_id.');
        } else {
            return false;
        }
    }

    return (int) $stmt->fetch(PDO::FETCH_OBJ)->server_id;
}


function user_select($pdo, $user_id, $suppress_error = false)
{
    $stmt = $pdo->prepare('
        SELECT user_id,
               name,
               email,
               register_ip,
               ip,
               time,
               register_time,
               power,
               trial_mod,
               verified,
               status,
               read_message_id,
               guild,
               coins,
               server_id
          FROM users
         WHERE user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query user_select.');
    }

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (empty($user) && $suppress_error === false) {
        throw new Exception('Could not find a user with that ID.');
    }

    return $user;
}


function user_update_coins($pdo, $user_id, $coins)
{
    $op = $coins < 0 ? '-' : '+';
    $stmt = $pdo->prepare("
        UPDATE users
           SET coins = coins $op :coins
         WHERE user_id = :user_id
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':coins', abs($coins), PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Unable to complete the transaction. Make sure you have enough coins!');
    }

    return $result;
}


function user_update_email($pdo, $user_id, $old_email, $new_email)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET email = :new_email
         WHERE user_id = :user_id
           AND email = :old_email
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':new_email', $new_email, PDO::PARAM_STR);
    $stmt->bindValue(':old_email', $old_email, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not update the email address of user #$user_id.");
    }

    return $result;
}


function user_update_guild($pdo, $user_id, $guild_id)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET guild = :guild_id
         WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':guild_id', $guild_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not update the guild of user #$user_id.");
    }

    return $result;
}


function user_update_ip($pdo, $user_id, $ip)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET time = UNIX_TIMESTAMP(NOW()),
               ip = :ip
         WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not update the IP address for user #$user_id.");
    }

    return $result;
}


function user_update_pass($pdo, $user_id, $pass_hash)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET pass_hash = :pass_hash
         WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':pass_hash', $pass_hash, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not change the password of user #$user_id.");
    }

    return $result;
}


function user_update_power($pdo, $user_id, $power, $trial = false)
{
    $trial = $power === 1 ? 0 : (int) $trial;
    $stmt = $pdo->prepare('
        UPDATE users
        SET power = :power,
        trial_mod = :trial
        WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':power', $power, PDO::PARAM_INT);
    $stmt->bindValue(':trial', $trial, PDO::PARAM_INT);

    $result = $stmt->execute();
    if ($result === false) {
        throw new Exception('Could not update user power');
    }

    return $result;
}


function user_update_read($pdo, $user_id, $read_message_id)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET read_message_id = :read_message_id
         WHERE user_id = :user_id
           AND read_message_id < :read_message_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':read_message_id', $read_message_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not update the last read message ID for user #$user_id.");
    }

    return $result;
}


function user_update_status($pdo, $user_id, $status, $server_id)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET time = UNIX_TIMESTAMP(NOW()),
               status = :status,
               server_id = :server_id
         WHERE user_id = :user_id
         LIMIT 1
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':server_id', $server_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not update the status of user #$user_id.");
    }

    return $result;
}


function user_update_temp_pass($pdo, $user_id, $temp_pass_hash)
{
    $stmt = $pdo->prepare('
        UPDATE users
           SET temp_pass_hash = :temp_pass_hash
         WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':temp_pass_hash', $temp_pass_hash, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception("Could not set a temporary password for user #$user_id.");
    }

    return $result;
}


function users_count_from_ip_expanded($pdo, $search_ip)
{
    $stmt = $pdo->prepare("
        SELECT
          COUNT(DISTINCT u.user_id) as 'count'
        FROM
          users u
          LEFT JOIN recent_logins rl ON u.user_id = rl.user_id
        WHERE
          :search_ip IN (u.ip, u.register_ip, rl.ip)
    ");
    $stmt->bindValue(':search_ip', $search_ip, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_count_from_ip_expanded.');
    }

    $data = $stmt->fetchAll(PDO::FETCH_OBJ);

    return (int) $data[0]->count;
}


function users_reset_status($pdo)
{
    $result = $pdo->exec('
        UPDATE users
           SET status = "offline"
         WHERE time < UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY)
    ');

    if ($result === false) {
        throw new Exception('Could not reset user statuses.');
    }

    return $result;
}


function users_search($pdo, $query)
{
    $query = "%$query%";
    $stmt = $pdo->prepare('
          SELECT power, trial_mod, name, time
            FROM users
           WHERE name LIKE :query
        ORDER BY time DESC
    ');
    $stmt->bindValue(':query', $query, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform search.');
    }

    $users = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($users)) {
        throw new Exception('Could not find any users with names like your search.');
    }

    return $users;
}


function users_select_by_email($pdo, $email)
{
    $stmt = $pdo->prepare('
          SELECT power, trial_mod, name, time
            FROM users
           WHERE email = :email
        ORDER BY time DESC
    ');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not search for users with that email.');
    }

    $users = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($users)) {
        throw new Exception('Could not find any users with that email.');
    }

    return $users;
}


function users_select_by_ip_expanded($pdo, $search_ip, $start = 0, $count = 25)
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT
          u.name AS 'name',
          u.power AS 'power',
          u.trial_mod AS 'trial_mod',
          u.time AS 'time'
        FROM
          users u
          LEFT JOIN recent_logins rl ON u.user_id = rl.user_id
        WHERE
          :search_ip IN (u.ip, u.register_ip, rl.ip)
        ORDER BY
          u.time DESC
        LIMIT
          :start, :count
    ");
    $stmt->bindValue(':search_ip', $search_ip, PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':count', $count, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_select_by_ip_expanded.');
    }

    $users = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($users)) {
        $search_ip = htmlspecialchars($search_ip);
        $err = "Could not find any users associated for that IP address ($search_ip) with those search parameters.";
        throw new Exception($err);
    }

    return $users;
}


function users_select_by_ip($pdo, $ip)
{
    $stmt = $pdo->prepare('
          SELECT user_id, name, time, power, trial_mod
            FROM users
           WHERE ip = :ip
        GROUP BY user_id
        ORDER BY time DESC
    ');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not search for users with that IP.');
    }

    $users = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($users)) {
        throw new Exception('Could not find any users with that IP.');
    }

    return $users;
}


function users_select_no_pr2($pdo)
{
    $stmt = $pdo->prepare('
        SELECT u.user_id
          FROM users u
          LEFT JOIN pr2 ON pr2.user_id = u.user_id
         WHERE pr2.user_id IS NULL
           AND u.time < :month
    ');
    $stmt->bindValue(':month', time() - 2592000, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_select_no_pr2.');
    }

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}


function users_select_old($pdo)
{
    $stmt = $pdo->prepare('
        SELECT u.user_id, u.time, pr2.rank, pr2.user_id
          FROM users u, pr2
         WHERE u.time < :year3
           AND u.user_id = pr2.user_id
           AND u.power = 1
           AND pr2.rank < 15
    ');
    $stmt->bindValue(':year3', time() - 94610000, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_select_old.');
    }

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}


function users_select_rank_tokens_and_rentals_by_guild($pdo, $guild_id)
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $stmt = $pdo->prepare('
        SELECT
          u.user_id,
          rt.used_tokens,
          rt.available_tokens,
          COUNT(rtr.time) as active_rentals
        FROM
          users u
        LEFT JOIN
          rank_tokens rt ON u.user_id = rt.user_id
        LEFT JOIN
          rank_token_rentals rtr ON u.guild = rtr.guild_id
        WHERE
          guild = :guild AND guild > 0
        GROUP BY
          u.user_id
    ');
    $stmt->bindValue(':guild', $guild_id, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        var_dump($stmt->errorInfo());
        die();
        throw new Exception('Could not perform query users_select_rank_tokens_and_rentals_by_guild.');
    }

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}


function users_select_staff($pdo)
{
    $stmt = $pdo->prepare('
        SELECT power, trial_mod, status, name, time, register_time
          FROM users
         WHERE power > 1
         ORDER BY power DESC, trial_mod ASC, time DESC
         LIMIT 100
    ');
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_select_staff.');
    }

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}


function users_select_top($pdo, $start, $count)
{
    $stmt = $pdo->prepare('
        SELECT u.name AS name,
               u.power AS power,
               u.trial_mod AS trial_mod,
               SUM(IFNULL(rt.used_tokens, 0) + pr2.rank) AS active_rank,
               pr2.hat_array AS hats,
               rt.used_tokens as tokens_used
          FROM users u
          LEFT JOIN pr2 ON pr2.user_id = u.user_id
          LEFT JOIN rank_tokens rt ON rt.user_id = pr2.user_id
         WHERE pr2.rank > 44 AND u.user_id <> 4291976
         GROUP BY name, power, pr2.rank, rt.used_tokens, hats
        HAVING active_rank > 49
         ORDER BY active_rank DESC, name ASC
         LIMIT :start, :count
    ');
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':count', $count, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        throw new Exception('Could not perform query users_select_top.');
    }

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
