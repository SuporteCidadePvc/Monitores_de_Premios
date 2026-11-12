<?php

return [
    'attachments' => [
        /*
         * Files live on the private "local" disk (storage/app/private) and are
         * served to the displays through the application, never directly.
         */
        'disk' => 'local',
        'directory' => 'announcements',

        /* Must stay below PHP upload_max_filesize (10M) and post_max_size (12M). */
        'max_kilobytes' => 10240,

        /* Orphan files younger than this may still belong to an in-flight request. */
        'orphan_min_age_minutes' => 60,
    ],
];
