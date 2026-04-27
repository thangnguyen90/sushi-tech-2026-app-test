<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionUserShowRequest;
use App\Services\Eventos\User\EventosUserLookupService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ReceptionUserShowController extends Controller
{
    public function __construct(
        private readonly EventosUserLookupService $eventosUserLookupService,
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(ReceptionUserShowRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userUuid = (string) $validated['user_uuid'];

        try {
            $visitor = $this->eventosUserLookupService->resolveByUuid($userUuid);
        } catch (Throwable) {
            return $this->responseService->error(
                message: '来場者情報の取得に失敗しました',
                code: 'RECEPTION_USER_LOOKUP_FAILED',
                data: [
                    'user_uuid' => $userUuid,
                ],
                status: 500,
            );
        }

        if (! $this->hasResolvedVisitor($visitor)) {
            return $this->responseService->error(
                message: '来場者が見つかりません',
                code: 'RECEPTION_USER_NOT_FOUND',
                data: [
                    'user_uuid' => $userUuid,
                ],
                status: 404,
            );
        }

        return $this->responseService->success(
            data: $visitor,
            code: 'OK',
            message: '',
        );
    }

    /**
     * @param  array{user_uuid: string, user_id: int|null, name: string|null, email: string|null, company_name: string|null}  $visitor
     */
    private function hasResolvedVisitor(array $visitor): bool
    {
        return $visitor['user_id'] !== null
            || $visitor['name'] !== null
            || $visitor['email'] !== null
            || $visitor['company_name'] !== null;
    }
}
