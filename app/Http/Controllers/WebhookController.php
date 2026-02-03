<?php

namespace App\Http\Controllers;

use App\Repositories\UsersRepository;
use App\Services\BusinessApproveWebhookService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WebhookController extends Controller
{


    public function __construct(
        private readonly ResponseService               $responseService,
        private readonly BusinessApproveWebhookService $businessApproveWebhookService,
        private readonly UsersRepository               $usersRepository,
    )
    {

    }

    public function handleCsvListTriggerWebhook(Request $request): JsonResponse
    {
        // Handle the webhook logic here
        // For example, process the CSV list trigger
        //        $key = $request->header('x-api-key');
        //        $privateKey = config('eventos.trigger_command_key');
        //        if ($key !== $privateKey) {
        //            return $this->responseService->error( 'Unauthorized',401,  [], 401);
        //        }
        $data = $request->all();
        foreach ($data as $item) {
            Process::path(base_path())->start("php artisan " . $item['name'] . ' /' . $item['fileurl']);
        }
        return $this->responseService->success('Webhook processed successfully', 200, status: 201);
    }

    public function businessApprovement(Request $request): JsonResponse
    {
        $data = $request->all();
        Log::channel('webhook')->info($data);

        $this->businessApproveWebhookService->process($data);
        return $this->responseService->success('Webhook processed successfully', 200, status: 201);
    }

    public function userRegistration(Request $request): JsonResponse
    {
        $data = $request->all();
        Log::channel('webhook')->info($data);
        if ($data["module_code"] === "Register") {
            $user = $this->usersRepository->findByUuid($data['user']['user_uuid']);
            if ($user) {
                $user->user_id = $data['user']['user_id'];
                $user->save();
            }else{
                $this->usersRepository->create([
                    'uuid' => $data['user']['user_uuid'],
                    'user_id' => $data['user']['user_id'],
                    'is_first_login' => true,
                ]);
            }
        }
        return $this->responseService->success('Register user', 200, status: 201);
    }
}
