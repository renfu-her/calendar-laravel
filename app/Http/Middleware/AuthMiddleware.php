<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 檢查用戶是否已登入
        if (!Auth::check()) {
            // 如果是 AJAX 請求
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => '未授權的訪問'
                ], 401);
            }
            
            // 一般請求則重定向到登入頁面
            return redirect()->route('login')->with('error', '請先登入');
        }

        return $next($request);
    }
}
