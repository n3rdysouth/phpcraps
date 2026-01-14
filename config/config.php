<?php

/**
 * Configuration file
 */

return [
    'database' => [
        'path' => __DIR__ . '/../database/craps_game.db'
    ],
    'game' => [
        'max_players' => 8,
        'starting_bankroll' => 1000.0,
        'default_game_id' => 1
    ]
];
