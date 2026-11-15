<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Discord application
    |--------------------------------------------------------------------------
    |
    | Each environment (local, staging, production) has its own Discord
    | application. The application ID, public key and bot token are secrets:
    | they live in .env and are never committed.
    |
    */

    'application_id' => env('DISCORD_APPLICATION_ID'),

    'public_key' => env('DISCORD_PUBLIC_KEY'),

    'bot_token' => env('DISCORD_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Guild
    |--------------------------------------------------------------------------
    |
    | The company uses one Discord server. Commands from any other guild are
    | rejected (FR-ID-04). Guild IDs are 64-bit integers handled as strings.
    |
    */

    'guild_id' => env('DISCORD_GUILD_ID'),

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    */

    'api_base' => env('DISCORD_API_BASE', 'https://discord.com/api/v10'),

    'timeout' => (int) env('DISCORD_HTTP_TIMEOUT', 10),

    'retry_times' => (int) env('DISCORD_RETRY_TIMES', 3),

    /*
    |--------------------------------------------------------------------------
    | Default channels
    |--------------------------------------------------------------------------
    |
    | The owner can override every channel on /attendance/settings. These env
    | values are the starting point and the fallback when a setting is empty.
    | Each office has its own attendance feed; that ID is stored on the office.
    |
    */

    'channels' => [
        'attendance' => env('DISCORD_ATTENDANCE_CHANNEL_ID'),
        'owner_digest' => env('DISCORD_OWNER_DIGEST_CHANNEL_ID'),
        'reports' => env('DISCORD_REPORTS_CHANNEL_ID'),
        'presence' => env('DISCORD_PRESENCE_CHANNEL_ID'),
    ],

];
