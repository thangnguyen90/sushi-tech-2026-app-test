<?php

namespace App\Http\Controllers;

use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Http\JsonResponse;
//use App\Services\BusinessApproveWebhookService;

class WebhookController extends Controller
{

    public function __construct( private readonly ResponseService $responseService)
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
            Process::path(base_path())->start("php artisan ".$item['name']. ' /'. $item['fileurl']);
        }
        return $this->responseService->success('Webhook processed successfully', 200, status: 201);
    }

//    public function businessApprovement(Request $request): JsonResponse
//    {
//        $data = $request->all();
//        $businessApproveWebhookService = app(BusinessApproveWebhookService::class);
//        $businessApproveWebhookService->process($data);
//        return $this->responseService->success('Webhook processed successfully', 200, status: 201);
//    }
}
