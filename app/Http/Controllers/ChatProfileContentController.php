<?php

namespace App\Http\Controllers;

use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Repositories\ChatProfileContentRepository;

class ChatProfileContentController extends Controller
{
    public function __construct(
        private readonly ChatProfileContentRepository $chatProfileContentRepository,
        private readonly ResponseService $responseService
    ) {
    }

    public function filters(Request $request): \Illuminate\Http\JsonResponse
    {
        $lang = (string) $request->query('lang', 'jpn');
        $onlyEnabled = (int) $request->query('only_enabled', 0) === 1;

        return $this->responseService->success(
            data: $this->chatProfileContentRepository->getFilterFields($lang, $onlyEnabled),
        );
    }
}
