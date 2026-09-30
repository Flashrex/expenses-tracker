<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteGroupRequest;
use App\Http\Requests\ReorderGroupsRequest;
use App\Http\Requests\SaveGroupRequest;
use App\Http\Requests\SaveIgnoreRulesRequest;
use App\Models\Group;
use App\Models\Rule;
use App\Models\Transaction;
use App\Services\Groups\GroupCards;
use App\Services\Groups\GroupCatalog;
use App\Services\Groups\StaleCardException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    /**
     * Show the group cards and the Ignored card.
     */
    public function index(GroupCards $cards): View
    {
        return view('pages.groups', ['state' => $cards->state()]);
    }

    /**
     * Create a group with its rules, placed before the saved card below it (or before "Other").
     */
    public function store(SaveGroupRequest $request, GroupCards $cards, GroupCatalog $catalog): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($request, $cards, $catalog) {
                $group = Group::query()->create([
                    'key' => Str::lower((string) Str::ulid()),
                    'name' => $request->string('name')->trim()->toString(),
                    'color' => $request->string('color')->toString(),
                    'position' => 0,
                ]);

                $cards->saveRules($group->key, $request->input('rules'));

                $keys = array_values(array_diff($catalog->keys(), [$group->key]));
                $before = in_array($request->input('before'), $keys, true) ? $request->input('before') : Group::OTHER;
                $at = array_search($before, $keys, true);
                array_splice($keys, $at === false ? count($keys) : $at, 0, [$group->key]);
                Group::reorder($keys);

                return $group;
            });
        } catch (StaleCardException) {
            return $this->stale();
        }

        return response()->json(['card' => $cards->card($group->fresh())], 201);
    }

    /**
     * Save the name, colour and rules of a group.
     */
    public function update(SaveGroupRequest $request, Group $group, GroupCards $cards): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $group, $cards) {
                $group->update([
                    'name' => $request->string('name')->trim()->toString(),
                    'color' => $request->string('color')->toString(),
                ]);

                $cards->saveRules($group->key, $request->input('rules'));
            });
        } catch (StaleCardException) {
            return $this->stale();
        }

        return response()->json(['card' => $cards->card($group->fresh())]);
    }

    /**
     * Save the rules of the Ignored card.
     */
    public function updateIgnored(SaveIgnoreRulesRequest $request, GroupCards $cards): JsonResponse
    {
        try {
            DB::transaction(fn () => $cards->saveRules(null, $request->input('rules')));
        } catch (StaleCardException) {
            return $this->stale();
        }

        return response()->json(['card' => $cards->ignoredCard()]);
    }

    /**
     * Store the group order; the list must hold every group exactly once.
     */
    public function order(ReorderGroupsRequest $request, GroupCatalog $catalog): JsonResponse|Response
    {
        $keys = $request->input('groups');
        $current = $catalog->keys();

        sort($current);
        $sorted = $keys;
        sort($sorted);

        if ($sorted !== $current) {
            return $this->stale();
        }

        Group::reorder($keys);
        return response()->noContent();
    }

    /**
     * Delete a group with all its rules; its entries move to the chosen group as picked manually.
     */
    public function destroy(DeleteGroupRequest $request, Group $group): Response
    {
        abort_if($group->key === Group::OTHER, 403);

        DB::transaction(function () use ($request, $group) {
            Transaction::query()->where('group_key', $group->key)->update([
                'group_key' => $request->string('move_to')->toString(),
                'rule_id' => null,
                'declined' => null,
            ]);

            Rule::query()->where('group_key', $group->key)->delete();
            $group->delete();
        });

        return response()->noContent();
    }

    private function stale(): JsonResponse
    {
        return response()->json(['message' => 'stale'], 409);
    }
}
