<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEntryDescriptionRequest;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class EntryDescriptionController extends Controller
{
    /**
     * Save the user's description of an entry; an empty one removes it.
     */
    public function __invoke(UpdateEntryDescriptionRequest $request, Transaction $transaction): JsonResponse
    {
        $transaction->update(['description' => $request->input('description')]);

        return response()->json(['description' => $transaction->description]);
    }
}
