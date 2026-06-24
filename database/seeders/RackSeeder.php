<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Rack::create([
            'name' => 'FOH Audio Control Rack',
            'size' => 24,
            'description' => 'Front of House (FOH) rack containing main audio mixers, wireless receivers, and power management.',
            'devices' => [
                [
                    'id' => 'dev_1',
                    'brand' => 'CyberPower',
                    'name' => 'PR1500LCDRTXL2U UPS',
                    'type' => 'Power',
                    'u_height' => 2,
                    'position' => 1,
                    'power_consumption' => 1350,
                    'heat_dissipation' => 450,
                    'weight' => 25.4,
                ],
                [
                    'id' => 'dev_2',
                    'brand' => 'Behringer',
                    'name' => 'X32 Rack Digital Mixer',
                    'type' => 'Audio',
                    'u_height' => 3,
                    'position' => 8,
                    'power_consumption' => 120,
                    'heat_dissipation' => 410,
                    'weight' => 6.5,
                ],
                [
                    'id' => 'dev_3',
                    'brand' => 'Behringer',
                    'name' => 'S16 Digital Stage Box',
                    'type' => 'Audio',
                    'u_height' => 2,
                    'position' => 13,
                    'power_consumption' => 45,
                    'heat_dissipation' => 150,
                    'weight' => 4.5,
                ],
                [
                    'id' => 'dev_4',
                    'brand' => 'Allen & Heath',
                    'name' => 'Qu-Pac Compact Digital Mixer',
                    'type' => 'Audio',
                    'u_height' => 4,
                    'position' => 17,
                    'power_consumption' => 82,
                    'heat_dissipation' => 280,
                    'weight' => 6.6,
                ],
                [
                    'id' => 'dev_5',
                    'brand' => 'Shure',
                    'name' => 'AD4D Dual Wireless Receiver',
                    'type' => 'Audio',
                    'u_height' => 1,
                    'position' => 22,
                    'power_consumption' => 30,
                    'heat_dissipation' => 100,
                    'weight' => 3.3,
                ],
                [
                    'id' => 'dev_6',
                    'brand' => 'Furman',
                    'name' => 'PL-8 C Power Conditioner',
                    'type' => 'Power',
                    'u_height' => 1,
                    'position' => 24,
                    'power_consumption' => 10,
                    'heat_dissipation' => 34,
                    'weight' => 2.7,
                ],
            ]
        ]);

        \App\Models\Rack::create([
            'name' => 'Broadcast Video Rack A',
            'size' => 16,
            'description' => 'Main broadcasting rack containing video switches, network distribution, and backup power.',
            'devices' => [
                [
                    'id' => 'dev_7',
                    'brand' => 'CyberPower',
                    'name' => 'PR1500LCDRTXL2U UPS',
                    'type' => 'Power',
                    'u_height' => 2,
                    'position' => 1,
                    'power_consumption' => 1350,
                    'heat_dissipation' => 450,
                    'weight' => 25.4,
                ],
                [
                    'id' => 'dev_8',
                    'brand' => 'Blackmagic Design',
                    'name' => 'HyperDeck Studio HD Pro',
                    'type' => 'Video',
                    'u_height' => 1,
                    'position' => 9,
                    'power_consumption' => 30,
                    'heat_dissipation' => 102,
                    'weight' => 2.1,
                ],
                [
                    'id' => 'dev_9',
                    'brand' => 'Blackmagic Design',
                    'name' => 'ATEM Production Studio 4K',
                    'type' => 'Video',
                    'u_height' => 1,
                    'position' => 11,
                    'power_consumption' => 60,
                    'heat_dissipation' => 205,
                    'weight' => 2.5,
                ],
                [
                    'id' => 'dev_10',
                    'brand' => 'Blackmagic Design',
                    'name' => 'Smart Videohub 20x20',
                    'type' => 'Video',
                    'u_height' => 1,
                    'position' => 13,
                    'power_consumption' => 40,
                    'heat_dissipation' => 136,
                    'weight' => 2.0,
                ],
                [
                    'id' => 'dev_11',
                    'brand' => 'UniFi',
                    'name' => 'Switch Enterprise XG 24',
                    'type' => 'Network',
                    'u_height' => 1,
                    'position' => 14,
                    'power_consumption' => 100,
                    'heat_dissipation' => 340,
                    'weight' => 6.1,
                ],
                [
                    'id' => 'dev_12',
                    'brand' => 'UniFi',
                    'name' => 'Dream Machine Pro',
                    'type' => 'Network',
                    'u_height' => 1,
                    'position' => 15,
                    'power_consumption' => 33,
                    'heat_dissipation' => 112,
                    'weight' => 3.9,
                ],
            ]
        ]);
    }
}
