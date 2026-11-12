<?php

namespace Database\Factories;

use App\Models\TableColumnPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableColumnPreference>
 */
class TableColumnPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'table' => 'App\Filament\Resources\Sales\Pages\ListSales',
            'columns' => [],
        ];
    }
}
