<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;
use App\Jobs\AlarmbotJob;
use App\Util\Context;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
//        AuthorizationException::class,
//        HttpException::class,
//        ModelNotFoundException::class,
//        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if (app()->runningInConsole()) {
            $input = new ArgvInput();
            $botMessage = [
                'type' => '[command]',
                'time' => date('Y-m-d H:i:s'),
                'command' => (string)$input,
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace()[0],
            ];
            $botMessage = json_encode($botMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            Log::error($botMessage);
            dispatch(new AlarmbotJob('technical', $botMessage));

        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function render($request, Exception $exception)
    {
        switch (true) {
            case $exception instanceof ApiException:
                $uid = Context::get("userid");
                $errorCode = $exception->getCode();
                $botMessage = [
                    'type' => '[api]',
                    'time' => date('Y-m-d H:i:s'),
                    'url' => str_replace(['http://', 'https://'], ['*ttp://', '*ttps://'], $request->fullUrl()),    # 机器人会访问一遍路径, 改成不是链接
                    'uid' => $uid,
                    'request' => $request->post(),
                    'code' => $errorCode,
                    'message' => $exception->getMessage(),
                    'token' => $request->cookie("token"),
                ];
                $botMessage = json_encode($botMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                Log::error($botMessage);

                if (!in_array($errorCode, [
                    ERROR_USER_REG_INVALID,
                    ERROR_USER_PASSWORD_WRONG,
                    ERROR_USER_NAME_EXISTS,
                    BIZ_PASSPORT_ERROR_NOT_BIND, # 绑定账号
                    ERROR_USER_ERR_TOKEN, # token失效
                    ERROR_USER_NICKNAMELIMIT, # 昵称次数
                    ERROR_CODE_OVERTIMES, # 手机验证码发送次数超限
                    ERROR_CODE_INVALID, # 验证码不正确或已过期
                    ERROR_BIZ_LIVE_NOT_ACTIVE,#不在直播状态
                    ERROR_CHAT_MESSAGE_LOW_LEVEL, #
                    ERROR_BIZ_PAYMENT_DIAMOND_BALANCE_DUE, # 钻石余额不足
                    ERROR_BIZ_CHATROOM_USER_HAS_SILENCED, # 禁言
                    ERROR_BIZ_CHATROOM_KICK_REJECT, # 踢出
                    ERROR_CHAT_MESSAGE_NUM_OVERRUN, # 聊天字数超限
                    ERROR_CHAT_MESSAGE_OVER_FREQUENCY, #聊天频次超限
                    ERROR_CODE_INTERVAL_TIME, # 手机验证码发送间隔太短
                    ERROR_PHONENUM_INVALID, # 您输入的手机号码有误
                    ERROR_USER_NAME_INVALID, # 昵称为2-8位的中文/字母/数字
                ])) {
                    dispatch(new AlarmbotJob('technical', $botMessage));
                }

                return apiRenderError($exception->getCode(), $exception->getMessage());
                break;
            default:
                $uid = Context::get("userid");
                $botMessage = [
                    'type' => '[program]',
                    'time' => date('Y-m-d H:i:s'),
                    'url' => str_replace(['http://', 'https://'], ['*ttp://', '*ttps://'], $request->fullUrl()),    # 机器人会访问一遍路径, 改成不是链接
                    'uid' => $uid,
                    'request' => $request->post(),
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
                $botMessage = json_encode($botMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                Log::error($botMessage);
                dispatch(new AlarmbotJob('technical', $botMessage));

                return parent::render($request, $exception);
        }

        return parent::render($request, $exception);
    }
}
