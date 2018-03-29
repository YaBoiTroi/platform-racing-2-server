<?php

require_once __DIR__ . '/data_fns.php';
require_once __DIR__ . '/../../http_server/queries/artifacts_found/artifacts_found_insert.php';
require_once __DIR__ . '/../../http_server/queries/artifact_locations/artifact_location_update_first_finder.php';
require_once __DIR__ . '/../../http_server/queries/artifact_locations/artifact_location_select.php';
require_once __DIR__ . '/../../http_server/queries/messages/message_insert.php';

function artifact_first_check($player)
{
    global $pdo;

    $user_id = $player->user_id;
    $safe_user_name = htmlspecialchars($player->name);

    try {
        artifacts_found_insert($pdo, $user_id);
        artifact_location_update_first_finder($pdo, $user_id);
        $artifact = artifact_location_select($pdo);
        $first_finder = $artifact->first_finder;

        // they found it first? time to get jiggy with it!
        if ($first_finder === $user_id) {
            // make a prize array for the game to show the user
            $artifact_first_prize_popup = json_encode(
                array(
                "type" => "eHead",
                "id" => 27,
                "name" => "Bubble Set",
                "desc" => "For finding the artifact first, you earned your very own bubble set!",
                "universal" => true
                )
            );

            // give all parts of the bubble set using the gain_part function from Player.php
            $player->gain_part("head", 27, true);
            $player->gain_part("body", 21, true);
            $player->gain_part("feet", 28, true);

            // tell the world
            $player->write('winPrize`' . $artifact_first_prize_popup);

            // pm the user (finishing touch!)
            $artifact_first_pm = "Dear $safe_user_name,\n\n"
                ."Congratulations for finding the artifact first! To commemorate this momentous occasion, you've been awarded with your very own bubble set.\n\n"
                ."Thanks for playing Platform Racing 2!\n\n"
                ."- Jiggmin";

            message_insert($pdo, $user_id, 1, $artifact_first_pm, '0');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        echo "Error: ".$message;
        return false;
    }
}
