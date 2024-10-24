<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleLoginController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    // Redirect user to Google's OAuth 2.0 server for authentication
    public function redirect()
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        $url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/calendar', // Added Calendar API scope
            'access_type' => 'offline', // Ensures we get a refresh token
            'prompt' => 'consent', // Force consent to get refresh token
        ]);

        return redirect($url . '?' . $query);
    }

    // Handle OAuth 2.0 callback and exchange the authorization code for an access token
    public function callback(Request $request)
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        $code = $request->get('code');

        if (!$code) {
            return redirect()->route('login')->with('error', 'Authorization code not provided');
        }

        try {
            // Exchange authorization code for access token
            $response = $this->client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ],
            ]);

            $tokenData = json_decode($response->getBody(), true);

            if (isset($tokenData['error'])) {
                return redirect()->route('login')->with('error', 'Failed to get access token: ' . $tokenData['error']);
            }

            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;

            // Fetch user info using access token
            $response = $this->client->get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $userInfo = json_decode($response->getBody(), true);

            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $userInfo['email']],
                [
                    'name' => $userInfo['name'],
                    'google_id' => $userInfo['id'],
                    'password' => bcrypt(Str::random(16)), // Create a random password as we are using Google login
                    'google_token' => json_encode($tokenData), // Store the full token data, including refresh token if available
                ]
            );

            // Log the user in
            Auth::login($user);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to authenticate with Google: ' . $e->getMessage());
        }
    }

    // Method to handle fetching events from Google Calendar
    public function getCalendarEvents()
    {
        // 檢查是否有登入的用戶
        $user =  Auth::user();

        if (!$user) {
            // 用戶未登入，重定向到登入頁面
            return redirect()->route('login')->with('error', 'You are not logged in.');
        }

        if (!$user->google_token) {
            // 用戶沒有連接 Google，提示錯誤
            return redirect()->route('login')->with('error', 'Not connected to Google.');
        }

        $tokenData = json_decode($user->google_token, true);

        try {
            // 獲取訪問令牌
            $accessToken = $tokenData['access_token'];

            // 檢查令牌是否已過期並刷新
            if ($this->isTokenExpired($tokenData)) {
                // 檢查是否有 refresh_token
                if (!isset($tokenData['refresh_token'])) {
                    return redirect()->route('login')->with('error', 'No refresh token available. Please reconnect to Google.');
                }

                // 刷新訪問令牌
                $newTokenData = $this->refreshAccessToken($tokenData['refresh_token']);
                if ($newTokenData) {
                    // 保存新的 token 並更新用戶資料
                    $user->google_token = json_encode($newTokenData);
                    $user->save();
                    $accessToken = $newTokenData['access_token'];
                } else {
                    return redirect()->route('login')->with('error', 'Failed to refresh access token.');
                }
            }

            // 使用有效的訪問令牌請求 Google Calendar 事件
            $response = $this->client->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'query' => [
                    'maxResults' => 250,
                    'singleEvents' => true,
                    'orderBy' => 'startTime',
                    'timeMin' => date('c'), // 獲取從現在開始的事件
                ],
            ]);

            // 解析事件資料
            $events = json_decode($response->getBody(), true);

            // 返回視圖，並傳入事件資料
            return view('calendar', ['events' => $events['items']]);
        } catch (\Exception $e) {
            // 捕捉異常並返回錯誤訊息
            return redirect()->route('login')->with('error', 'Failed to fetch calendar events: ' . $e->getMessage());
        }
    }


    // Check if the token is expired
    private function isTokenExpired($tokenData)
    {
        if (!isset($tokenData['expires_in'])) {
            return true; // Assume expired if no expiry time
        }

        $expiryTime = $tokenData['created_at'] + $tokenData['expires_in'];
        return $expiryTime < time();
    }

    // Refresh the access token
    private function refreshAccessToken($refreshToken)
    {
        try {
            $clientId = config('services.google.client_id');
            $clientSecret = config('services.google.client_secret');

            $response = $this->client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ],
            ]);

            $newTokenData = json_decode($response->getBody(), true);
            if (isset($newTokenData['error'])) {
                return null;
            }

            // Merge with refresh token
            $newTokenData['refresh_token'] = $refreshToken;
            return $newTokenData;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function reauthorize()
    {
        $user = Auth::user();
        if ($user) {
            // 清除現有的 token
            $user->google_token = null;
            $user->save();
        }

        // 重定向到 Google 授權頁面
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        $url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent', // 強制顯示同意畫面
        ]);

        return redirect($url . '?' . $query);
    }
}
