<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MatchingPartnerService;
use Illuminate\Http\Request;

class MatchingPartnerController extends Controller
{
    public function index(Request $request, MatchingPartnerService $service)
    {
        $validated = $request->validate([
            'language_id' => ['nullable', 'integer'],
            'limit_exhibitors' => ['nullable', 'integer', 'min:1', 'max:50'],
            'limit_visitors' => ['nullable', 'integer', 'min:1', 'max:50'],
            'limit_networking_per_name' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $userId = (int) $request->user()->id;

        $result = $service->getPartners([
            'user_id' => $userId,
            'data_source_id' => (int) $validated['live_chat_data_source_id'],
            'language_id' => (int) ($validated['language_id'] ?? 1),
            'limit_exhibitors' => (int) ($validated['limit_exhibitors'] ?? 10),
            'limit_visitors' => (int) ($validated['limit_visitors'] ?? 10),
            'limit_networking_per_name' => (int) ($validated['limit_networking_per_name'] ?? 10),
        ]);

        return response()->json([
            'code' => 'OK',
            'message' => '',
            'result' => $result,
        ]);
    }
}
