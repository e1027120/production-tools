<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $devices = [
            // Audio Group
            ['brand' => 'Allen & Heath', 'name' => 'dLive CDM32 MixRack', 'type' => 'Audio', 'u_height' => 5, 'power_consumption' => 90, 'weight' => 10.5],
            ['brand' => 'Allen & Heath', 'name' => 'Qu-Pac Compact Digital Mixer', 'type' => 'Audio', 'u_height' => 4, 'power_consumption' => 82, 'weight' => 6.6],
            ['brand' => 'Behringer', 'name' => 'X32 Rack Digital Mixer', 'type' => 'Audio', 'u_height' => 3, 'power_consumption' => 120, 'weight' => 6.5],
            ['brand' => 'Behringer', 'name' => 'S16 Digital Stage Box', 'type' => 'Audio', 'u_height' => 2, 'power_consumption' => 45, 'weight' => 4.5],
            ['brand' => 'Shure', 'name' => 'AD4D Dual Wireless Receiver', 'type' => 'Audio', 'u_height' => 1, 'power_consumption' => 30, 'weight' => 3.3],
            
            // Video Group
            ['brand' => 'Blackmagic Design', 'name' => 'Smart Videohub 20x20', 'type' => 'Video', 'u_height' => 1, 'power_consumption' => 40, 'weight' => 2.0],
            ['brand' => 'Blackmagic Design', 'name' => 'ATEM Production Studio 4K', 'type' => 'Video', 'u_height' => 1, 'power_consumption' => 60, 'weight' => 2.5],
            ['brand' => 'Blackmagic Design', 'name' => 'HyperDeck Studio HD Pro', 'type' => 'Video', 'u_height' => 1, 'power_consumption' => 30, 'weight' => 2.1],
            
            // Network Group
            ['brand' => 'UniFi', 'name' => 'Dream Machine Pro', 'type' => 'Network', 'u_height' => 1, 'power_consumption' => 33, 'weight' => 3.9],
            ['brand' => 'UniFi', 'name' => 'Switch Enterprise XG 24', 'type' => 'Network', 'u_height' => 1, 'power_consumption' => 100, 'weight' => 6.1],
            
            // Power Group
            ['brand' => 'CyberPower', 'name' => 'PR1500LCDRTXL2U UPS', 'type' => 'Power', 'u_height' => 2, 'power_consumption' => 1350, 'weight' => 25.4],
            ['brand' => 'Furman', 'name' => 'PL-8 C Power Conditioner', 'type' => 'Power', 'u_height' => 1, 'power_consumption' => 10, 'weight' => 2.7],
        ];

        foreach ($devices as $device) {
            \App\Models\CatalogDevice::create($device);
        }
    }
}
