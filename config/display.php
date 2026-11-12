<?php

return [
    'playlist' => [
        'announcement_duration_seconds' => 60,

        /*
         * The base cycle only contains production screens. The display
         * controller appends the announcement step when an active announcement
         * applies to the current sector.
         */
        'steps' => [
            ['screen' => 'principal', 'duration_seconds' => 60],
            ['screen' => 'hoje', 'duration_seconds' => 30],
            ['screen' => 'semana', 'duration_seconds' => 30],
            ['screen' => 'mes', 'duration_seconds' => 30],
        ],
    ],
];
