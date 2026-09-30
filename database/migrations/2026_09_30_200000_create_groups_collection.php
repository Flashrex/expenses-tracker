<?php

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations: create the collection and copy the configured groups into it once.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $collection) {
            $collection->unique('key');
        });

        $collection = Group::query()->toBase()->raw();

        if ($collection->countDocuments() > 0) {
            return;
        }

        $documents = [];

        foreach (config('expenses.groups') as $key => $group) {
            $documents[] = ['key' => $key, 'name' => $group['name'], 'color' => $group['color'], 'position' => $group['sort']];
        }

        $collection->insertMany($documents);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
