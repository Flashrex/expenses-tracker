<?php

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Old swatch => its muted replacement (same lightness, less saturation). Custom colours are left alone.
     *
     * @var array<string, string>
     */
    private const COLORS = [
        '#6366f1' => '#7f81d5',
        '#f59e0b' => '#c6923a',
        '#10b981' => '#329776',
        '#8b5cf6' => '#977bd7',
        '#ec4899' => '#cb6999',
        '#0ea5e9' => '#3a94bd',
        '#f97316' => '#cc7b43',
        '#f43f5e' => '#d06376',
        '#14b8a6' => '#35978c',
        '#94a3b8' => '#9ba4b1',
        '#ef4444' => '#cd6666',
        '#84cc16' => '#7ca83a',
        '#06b6d4' => '#2f99ab',
        '#eab308' => '#bd9c35',
        '#78716c' => '#76716e',
        '#d946ef' => '#c068cd',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->recolor(self::COLORS);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->recolor(array_flip(self::COLORS));
    }

    /**
     * @param  array<string, string>  $colors
     */
    private function recolor(array $colors): void
    {
        $collection = Group::query()->toBase()->raw();

        foreach ($colors as $from => $to) {
            $collection->updateMany(['color' => $from], ['$set' => ['color' => $to]]);
        }
    }
};
