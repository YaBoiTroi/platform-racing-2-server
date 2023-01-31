<?php

require_once GEN_HTTP_FNS;
require_once HTTP_FNS . '/output_fns.php';
require_once QUERIES_DIR . '/level_backups.php';
require_once QUERIES_DIR . '/new_levels.php';

$ip = get_ip();
$desc = "<p><center>".
        "Welcome to PR2's level restore system!<br>".
        "You can use this tool to restore any level that was modified or deleted in the past year.".
        "</center></p>";

try {
    // rate limiting
    rate_limit('level-backups-'.$ip, 5, 2);
    rate_limit('level-backups-'.$ip, 30, 10);

    // connect
    $pdo = pdo_connect();
    $user_id = (int) token_login($pdo, true, false, 'g');

    // rate limiting
    rate_limit('level-backups-'.$user_id, 5, 2);
    rate_limit('level-backups-'.$user_id, 30, 10);

    // output mod nav if they're a mod
    $staff = is_staff($pdo, $user_id, false);
    output_header('Level Backups', $staff->mod, $staff->admin);

    // restore a backup
    $action = default_get('action');

    if ($action === 'restore') {
        // check referrer
        require_trusted_ref('restore level backups');

        // get the level_id that this backup_id points to
        $backup_id = default_get('backup_id');
        $row = level_backup_select($pdo, $backup_id);
        if ((int) $row->user_id !== $user_id) {
            throw new Exception('You do not own this backup.');
        }

        // initialize some variables
        $level_id = (int) $row->level_id;
        $version = (int) $row->version;
        $title = $row->title;

        // connect
        $s3 = s3_connect();

        // pull the backup
        $file = $s3->getObject('pr2backups', "$level_id-v$version.txt");
        if (!$file) {
            throw new Exception('Could not load backup contents.');
        }
        $body = $file->body;

        // restore this backup to the db
        levels_restore_backup(
            $pdo,
            $user_id,
            $title,
            $row->note,
            $row->live,
            $ip,
            $row->min_level,
            $row->song,
            $level_id,
            $row->play_count,
            $row->pass,
            $row->type,
            $row->bad_hats,
            $row->votes,
            $row->rating,
            $version
        );
        $restored_level = level_select($pdo, $level_id);
        $new_version = (int) $restored_level->version;

        // increment the version and recalculate the hash of the level body
        $str1 = "&version=$version";
        $str2 = "&version=$new_version";
        $body = str_replace($str1, $str2, $body);
        $len = strlen($body) - 32;
        $body = substr($body, 0, $len);
        $str_to_hash = $new_version . $level_id . $body . $LEVEL_SALT_2;
        $hash = md5($str_to_hash);
        $body = $body . $hash;

        // write the backup to the level system
        $result = $s3->putObjectString($body, 'pr2levels1', "$level_id.txt");
        if (!$result) {
            throw new Exception('Could not restore backup.');
        }

        // add to newest if the save time was within the last day
        $live = (bool) (int) $row->live;
        $max_on_newest = check_newest_max($pdo, $user_name, $ip);
        if ($live && !$max_on_newest && $row->time > time() - 86400) {
            new_level_insert($pdo, $level_id, $row->time, $ip);
        }

        // success
        $safe_title = htmlspecialchars($title, ENT_QUOTES);
        echo $desc;
        echo '<p>---</p>';
        echo "<p><b>$safe_title v$version</b> restored successfully!</p>";
    } else {
        echo $desc;
    }

    // display available backups
    echo '<br/>';
    $backups = level_backups_select_by_user($pdo, $user_id);
    if (!empty($backups)) {
        foreach ($backups as $row) {
            $title = htmlspecialchars($row->title, ENT_QUOTES);
            $date = date('M j, Y g:i A', $row->time);
            echo "<p>$date: <b>$title</b> v$row->version "
                ."(<a href='?action=restore&backup_id=$row->backup_id'>restore</a>)</p>";
        }
    } else {
        echo "<center>You haven't modified or deleted any levels in the past year.</center>";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    output_header("Level Backups");
    echo "Error: $error";
} finally {
    output_footer();
}
