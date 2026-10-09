<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            'BTN' => ['Magelang', 'Yogyakarta', 'Wonosari'],
            'BSN' => ['Magelang', 'Yogyakarta'],
            'BNI' => ['Magelang'],
            'MANDIRI' => ['Wonosari'],
            'BPD DIY' => ['Wonosari', 'Yogyakarta'],
            'BRI' => ['Wonosari', 'Yogyakarta', 'Magelang'],
            'BPD JATENG SYARIAH' => ['Magelang'],
            'BPD JATENG' => ['Magelang'],
        ];

        foreach ($banks as $name => $units) {
            $bank = Bank::query()->updateOrCreate(['name' => $name], ['is_active' => true]);

            foreach ($units as $unit) {
                $bank->units()->updateOrCreate(['name' => $unit], ['is_active' => true]);
            }
        }
    }
}
