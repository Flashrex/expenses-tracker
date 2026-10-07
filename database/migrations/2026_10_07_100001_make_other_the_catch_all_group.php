<?php

use App\Models\Group;
use App\Models\Rule;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use MongoDB\BSON\Regex;

return new class extends Migration
{
    /**
     * "Other" holds every outgoing entry no rule matches, so it keeps no rules of its own:
     * the Rundfunkbeitrag rules move to a new "Fees & Taxes" group with their entries,
     * the other rules of "Other" are deleted, outgoing entries without a group move into "Other", and "Other" becomes the last group.
     */
    public function up(): void
    {
        $this->moveOtherLast();

        $rules = Rule::query()->toBase()->raw();
        $transactions = Transaction::query()->toBase()->raw();

        $fees = iterator_to_array($rules->find([
            'group_key' => Group::OTHER,
            'conditions.value' => new Regex('rundfunkbeitrag', 'i'),
        ]), false);

        if ($fees !== []) {
            $key = $this->feesGroup();

            foreach ($fees as $index => $rule) {
                $rules->updateOne(['_id' => $rule['_id']], ['$set' => ['group_key' => $key, 'position' => $index + 1]]);
                $transactions->updateMany(
                    ['rule_id' => (string) $rule['_id'], 'group_key' => Group::OTHER],
                    ['$set' => ['group_key' => $key]],
                );
            }
        }

        $deleted = array_map(
            fn ($rule) => (string) $rule['_id'],
            iterator_to_array($rules->find(['group_key' => Group::OTHER], ['projection' => ['_id' => 1]]), false),
        );

        if ($deleted !== []) {
            $transactions->updateMany(['rule_id' => ['$in' => $deleted]], ['$set' => ['rule_id' => null, 'unmatched' => true]]);
            $rules->deleteMany(['group_key' => Group::OTHER]);
        }

        $transactions->updateMany(
            ['direction' => 'out', 'ignored' => ['$ne' => true], 'group_key' => null],
            ['$set' => ['group_key' => Group::OTHER, 'unmatched' => true]],
        );
    }

    /**
     * Reverse the migrations. The deleted rules and the former "Unassigned" entries cannot be told apart any more.
     */
    public function down(): void
    {
        //
    }

    private function moveOtherLast(): void
    {
        $groups = Group::query()->toBase()->raw();
        $last = $groups->findOne(['key' => ['$ne' => Group::OTHER]], ['sort' => ['position' => -1]]);

        if ($last !== null) {
            $groups->updateOne(['key' => Group::OTHER], ['$set' => ['position' => (int) $last['position'] + 1]]);
        }
    }

    /**
     * Key of the "Fees & Taxes" group, created just above "Other" when no group of that name exists.
     */
    private function feesGroup(): string
    {
        $groups = Group::query()->toBase()->raw();

        $existing = $groups->findOne(['name' => new Regex('^fees & taxes$', 'i')]);

        if ($existing !== null) {
            return $existing['key'];
        }

        $other = $groups->findOne(['key' => Group::OTHER]);
        $position = (int) ($other['position'] ?? 0);

        $groups->updateMany(['position' => ['$gte' => $position]], ['$inc' => ['position' => 1]]);
        $groups->insertOne(['key' => 'fees', 'name' => 'Fees & Taxes', 'color' => '#bd9c35', 'position' => $position]);

        return 'fees';
    }
};
