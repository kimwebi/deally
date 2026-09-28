<?php

namespace Database\Seeders;

use Deally\Calls\Models\MeetingPlatform;
use Illuminate\Database\Seeder;

/**
 * The meeting platforms a company can choose from.
 *
 * Every row ships enabled but not connected, and says why in plain words. That
 * is the accurate state: the picker has to exist and be usable so the
 * initiation flow is real, but DeAlly cannot create a Zoom meeting or admit a
 * transcription bot without real OAuth app credentials, and presenting a
 * platform as if it could would be a join link that goes nowhere.
 *
 * Connecting one means registering a MeetingPlatformConnector against it — see
 * config/services.php for the credentials each expects.
 */
class MeetingPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'key' => 'zoom',
                'name' => 'Zoom',
                'icon' => '🎥',
                'sort_order' => 10,
                'connection_note' => 'Not connected. DeAlly needs a Zoom Server-to-Server OAuth app to create meetings and admit the recording bot on your behalf.',
            ],
            [
                'key' => 'microsoft_teams',
                'name' => 'Microsoft Teams',
                'icon' => '🟦',
                'sort_order' => 20,
                'connection_note' => 'Not connected. DeAlly needs Microsoft Graph application credentials to create the online meeting and admit the transcription bot.',
            ],
            [
                'key' => 'google_meet',
                'name' => 'Google Meet',
                'icon' => '📹',
                'sort_order' => 30,
                'connection_note' => 'Not connected. DeAlly needs a Google Workspace OAuth client to create the Meet link and admit the transcription bot.',
            ],
        ];

        foreach ($platforms as $platform) {
            MeetingPlatform::query()->updateOrCreate(
                ['key' => $platform['key']],
                [
                    'name' => $platform['name'],
                    'icon' => $platform['icon'],
                    'sort_order' => $platform['sort_order'],
                    'connection_note' => $platform['connection_note'],
                    'enabled' => true,
                    // Deliberately not flipped by a seeder. Connection is a
                    // credential state, not a preference, and defaulting it true
                    // would have the picker offering something that cannot work.
                    'connected' => false,
                ]
            );
        }
    }
}
