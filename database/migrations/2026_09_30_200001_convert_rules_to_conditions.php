<?php

use App\Models\Rule;
use App\Services\Rules\TextNormalizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Turn every field/pattern rule into a one-condition rule in place, so rule ids stay valid.
     * System rules get positions per card in the order the old matcher checked them.
     */
    public function up(): void
    {
        $collection = Rule::query()->toBase()->raw();
        $documents = iterator_to_array($collection->find(['pattern' => ['$exists' => true]]), false);
        $buckets = [];

        foreach ($documents as $document) {
            if ($document['source'] !== 'manual') {
                $buckets[(string) ($document['group_key'] ?? '')][] = $document;
            }
        }

        $positions = [];

        foreach ($buckets as $bucket) {
            usort($bucket, fn ($a, $b) => [$b['priority'] ?? 100, mb_strlen(TextNormalizer::normalize($b['pattern'])), (string) $a['_id']]
                <=> [$a['priority'] ?? 100, mb_strlen(TextNormalizer::normalize($a['pattern'])), (string) $b['_id']]);

            foreach ($bucket as $index => $document) {
                $positions[(string) $document['_id']] = $index + 1;
            }
        }

        foreach ($documents as $document) {
            $collection->updateOne(['_id' => $document['_id']], [
                '$set' => [
                    'conditions' => [['field' => $document['field'], 'operator' => 'contains', 'value' => $document['pattern']]],
                    'source' => $document['source'] === 'manual' ? 'manual' : 'system',
                    'position' => $positions[(string) $document['_id']] ?? null,
                ],
                '$unset' => ['field' => '', 'pattern' => '', 'priority' => ''],
            ]);
        }
    }

    /**
     * Reverse the migrations. Lossy: only the first condition survives, and rules whose first condition is on the amount are left as they are.
     */
    public function down(): void
    {
        $collection = Rule::query()->toBase()->raw();

        foreach ($collection->find(['conditions' => ['$exists' => true]]) as $document) {
            $first = $document['conditions'][0] ?? null;

            if ($first === null || $first['field'] === 'amount') {
                continue;
            }

            $manual = $document['source'] === 'manual';

            $collection->updateOne(['_id' => $document['_id']], [
                '$set' => [
                    'field' => $first['field'],
                    'pattern' => $first['value'],
                    'priority' => $manual ? 300 : 100,
                    'source' => $manual ? 'manual' : 'seeded',
                ],
                '$unset' => ['conditions' => '', 'position' => ''],
            ]);
        }
    }
};
