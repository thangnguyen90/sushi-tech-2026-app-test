<?php

namespace App\Http\Controllers;

use App\Repositories\UsersRepository;
use App\Services\Eventos\User\UserService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use App\Repositories\LiveChatProfilesRepository;
use TypeError;

class LiveChatProfilesController extends Controller
{

    public function __construct(
        private readonly UsersRepository    $usersRepository,
        private readonly ResponseService    $responseService,
        private readonly LiveChatProfilesRepository $liveChatProfilesRepository,
        private readonly UserService       $userService,
    )
    {
    }

    public function checkUserFistLoginAndAgreePolicy(Request $request) : \Illuminate\Http\JsonResponse
    {
        try {
            $uuid = $request->header('user-uuid');
            if(!$uuid){
                return $this->responseService->error(
                    message: 'Missing user uuid.',
                    status: 400,
                );
            }
            $user = $this->usersRepository->firstWhere('uuid', $uuid );
            [$isFirstLogin , $isAgreed] = $this->CheckUserIdWithUuid($user, $uuid, true);
            return $this->responseService->success(
                data: [
                    'is_first_login' => $isFirstLogin,
                    'policy_agreed' => $isAgreed,
                ],
            );
        } catch (Exception|TypeError $e) {
            return $this->responseService->error(
                message: 'Failed to check user login and agreement status.',
                status: 500,
            );
        }
    }

    public function userAgreement( Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $uuid = $request->header('user-uuid');
            if(!$uuid){
                return $this->responseService->error(

                    message: 'Missing user uuid.',
                );
            }
            $user = $this->usersRepository->firstWhere('uuid', $uuid);
            [$isFirstLogin , $isAgreed] = $this->CheckUserIdWithUuid($user, $uuid);
            return $this->responseService->success(data: [
                'is_first_login' => $isFirstLogin,
                'policy_agreed' => $isAgreed,
            ]);
        }catch (Exception|TypeError $e){
            return $this->responseService->error(
                message: 'Failed to record user agreement.',
                status: 500,
            );
        }

    }

    /**
     * @throws Exception
     */
    private  function CheckUserIdWithUuid($user, string $uuid, $isCheck = false): array
    {
        $isFirstLogin = true;
        $isAgreed = false;
        if (!$user) {
            $id = $this->getIdLiveChatProfile($uuid);
            $this->usersRepository->create([
                'uuid' => $uuid,
                'user_id' => $id,
                'policy_agreed' => false,
            ]);
        }else if (!$isCheck){
            $isFirstLogin = false;
            if($user->user_id === null){
                $id = $this->getIdLiveChatProfile($uuid);
                $user->user_id = $id;
            }
            $user->policy_agreed = true;
            $user->save();
            $isAgreed= true;
        }else {$isAgreed = $user->policy_agreed;}
        return [$isFirstLogin, $isAgreed];
    }
    /**
     * @param $uuid
     * @return string|null
     * @throws Exception
     */
    public function getIdLiveChatProfile($uuid): ?string{
        $dataUser = $this->userService->getUsersByUuid($uuid);
        if(empty($dataUser['account'])){
            $dataUser['account'] = null;
        }else{
            $liveChatProfile = $this->liveChatProfilesRepository->firstWhere('mail_address', $dataUser['account']);

            $dataUser['account'] = $liveChatProfile ? ($liveChatProfile?->user_id ??  $liveChatProfile?->exhibitor_administrator_id):null ;
        }
        return $dataUser['account'];
    }
}
