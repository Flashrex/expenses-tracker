<?php

namespace App\Http\Controllers;

use App\Enums\RuleSource;
use App\Models\Rule;
use Illuminate\Http\Response;

class ManualRuleController extends Controller
{
    /**
     * Delete an "Always use" rule; its entries keep their group.
     */
    public function destroy(Rule $rule): Response
    {
        abort_unless($rule->source === RuleSource::Manual, 404);

        $rule->delete();
        session()->forget(RuleRerunController::SESSION_KEY);

        return response()->noContent();
    }
}
