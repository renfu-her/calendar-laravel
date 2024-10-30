<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class GoogleCalendarController extends Controller
{
    private $client;
    private $calendars = [
        [
            'id' => 'renfu.her@gmail.com',
            'className' => 'gcal-event-primary',
            'color' => '#1a73e8',
            'textColor' => '#ffffff'
        ],
        [
            'id' => 'zivhsiao@gmail.com',
            'className' => 'gcal-event-secondary',
            'color' => '#137333',
            'textColor' => '#ffffff'
        ],
        [
            'id' => 'jenfuhe@besttour.com.tw',
            'className' => 'gcal-event-tertiary',
            'color' => '#ea8600',
            'textColor' => '#ffffff'
        ]
    ];

    public function __construct()
    {
        // 初始化 Google Client
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));

        // 添加所需的權限範圍
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.profile');
        $this->client->addScope('https://www.googleapis.com/auth/calendar');

        // 設置訪問類型為離線，以獲取 refresh token
        $this->client->setAccessType('offline');
        // 強制顯示同意畫面
        $this->client->setPrompt('consent');
    }

    // 連接 Google OAuth
    public function connect()
    {
        return redirect($this->client->createAuthUrl());
    }

    // Google OAuth 回調
    public function callback(Request $request)
    {
        if ($request->has('code')) {
            try {
                $token = $this->client->fetchAccessTokenWithAuthCode($request->get('code'));

                if (!isset($token['error'])) {
                    $user = Auth::user();

                    if ($user) {
                        // 確保保存完整的 token 信息
                        if (!isset($token['refresh_token'])) {
                            // 如果沒有 refresh token，可能需要撤銷訪問並重新授權
                            return redirect()->route('connect.google.calendar')
                                ->with('error', '需要重新授權以獲取完整訪問權限');
                        }

                        User::where('id', $user->id)->update(['google_token' => json_encode($token)]);
                        return redirect()->route('calendar')
                            ->with('success', '成功連接到 Google Calendar');
                    }

                    return redirect()->route('login')
                        ->with('error', '用戶未登入');
                }

                return redirect()->route('calendar')
                    ->with('error', '獲取 Google Calendar 授權失敗');
            } catch (\Exception $e) {
                return redirect()->route('calendar')
                    ->with('error', '連接 Google Calendar 時發生錯誤：' . $e->getMessage());
            }
        }

        return redirect()->route('calendar')
            ->with('error', '無效的請求');
    }

    // 獲取日曆事件
    public function getEvents(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            if (!$user->google_token) {
                return response()->json([
                    'error' => 'Not connected to Google Calendar',
                    'needsReauth' => true
                ], 401);
            }

            $this->setupClient($user);
            $service = new GoogleCalendar($this->client);
            $events = [];

            // 從 FullCalendar 傳來的請求中提取 start 和 end 參數
            $start = Carbon::parse($request->query('start'))->setTimezone('Asia/Taipei');
            $end = Carbon::parse($request->query('end'))->setTimezone('Asia/Taipei');

            $optParams = [
                'maxResults' => 250,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => $start->format('c'),
                'timeMax' => $end->format('c'),
                'timeZone' => 'Asia/Taipei',
            ];

            // 獲取所有日曆的事件
            foreach ($this->calendars as $calendar) {
                try {
                    $results = $service->events->listEvents($calendar['id'], $optParams);

                    foreach ($results->getItems() as $event) {
                        $eventStart = $event->start->dateTime ?? $event->start->date;
                        $eventEnd = $event->end->dateTime ?? $event->end->date;

                        // 檢查事件是否可編輯
                        $canEdit = $calendar['id'] === 'primary' ||
                            $event->getCreator()->getEmail() === $user->email;

                        $description = $event->getDescription();
                        $events[] = [
                            'id' => $event->id,
                            'title' => $event->getSummary() ?? '(無標題)',
                            'description' => $this->convertBrToNewline($description),
                            'start' => Carbon::parse($eventStart)->setTimezone('Asia/Taipei')->format('c'),
                            'end' => Carbon::parse($eventEnd)->setTimezone('Asia/Taipei')->format('c'),
                            'allDay' => !isset($event->start->dateTime),
                            'className' => $calendar['className'],
                            'color' => $calendar['color'],
                            'textColor' => $calendar['textColor'],
                            'calendarId' => $calendar['id'],
                            'editable' => $canEdit, // 添加編輯權限標記
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error("Error fetching events from calendar {$calendar['id']}: " . $e->getMessage());
                    continue;  // 如果某個日曆獲取失敗，繼續獲取其他日曆
                }
            }

            return response()->json($events);
        } catch (\Exception $e) {
            if ($e->getCode() === 401) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'needsReauth' => true
                ], 401);
            }
            return response()->json(['error' => 'Failed to fetch events: ' . $e->getMessage()], 500);
        }
    }


    // 顯示日曆視圖
    public function showCalendar()
    {
        return view('calendar');
    }

    // 創建日曆事件
    public function createEvent(Request $request)
    {
        try {
            $user = Auth::user();
            $this->setupClient($user);
            $service = new GoogleCalendar($this->client);

            $event = new Event([
                'summary' => $request->input('title'),
                'description' => $this->convertNewlineToBr($request->input('description')),
                'start' => [
                    'dateTime' => Carbon::parse($request->start)->format('c'),
                    'timeZone' => 'Asia/Taipei',
                ],
                'end' => [
                    'dateTime' => Carbon::parse($request->end)->format('c'),
                    'timeZone' => 'Asia/Taipei',
                ],
            ]);

            $calendarId = $request->input('calendarId', 'primary');
            $event = $service->events->insert($calendarId, $event);

            return response()->json([
                'id' => $event->id,
                'title' => $event->summary,
                'description' => $this->convertBrToNewline($event->description),
                'start' => Carbon::parse($event->start->dateTime)->setTimezone('Asia/Taipei')->format('c'),
                'end' => Carbon::parse($event->end->dateTime)->setTimezone('Asia/Taipei')->format('c'),
                'calendarId' => $calendarId
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => '創建事件失敗：' . $e->getMessage()], 500);
        }
    }

    // 更新日曆事件
    public function updateEvent(Request $request, $eventId)
    {
        try {
            $user = Auth::user();
            $this->setupClient($user);
            $service = new GoogleCalendar($this->client);

            // 從請求中獲取日曆ID
            $calendarId = $request->input('calendarId');
            if (!$calendarId) {
                throw new \Exception('缺少日曆ID');
            }

            Log::debug('Updating event', [
                'calendarId' => $calendarId,
                'eventId' => $eventId,
                'request' => $request->all()
            ]); // 調試用

            // 獲取現有事件
            $event = $service->events->get($calendarId, $eventId);

            if ($request->has('title')) {
                $event->setSummary($request->input('title'));
            }

            if ($request->has('description')) {
                $description = $request->input('description');
                $event->setDescription($description);
            }

            if ($request->has('start')) {
                $startDateTime = new EventDateTime();
                $startDateTime->setDateTime(Carbon::parse($request->start)->format('c'));
                $startDateTime->setTimeZone('Asia/Taipei');
                $event->setStart($startDateTime);
            }

            if ($request->has('end')) {
                $endDateTime = new EventDateTime();
                $endDateTime->setDateTime(Carbon::parse($request->end)->format('c'));
                $endDateTime->setTimeZone('Asia/Taipei');
                $event->setEnd($endDateTime);
            }

            $updatedEvent = $service->events->update($calendarId, $eventId, $event);

            return response()->json([
                'id' => $updatedEvent->id,
                'title' => $updatedEvent->summary,
                'description' => $this->convertBrToNewline($updatedEvent->description),
                'start' => Carbon::parse($updatedEvent->start->dateTime)->setTimezone('Asia/Taipei')->format('c'),
                'end' => Carbon::parse($updatedEvent->end->dateTime)->setTimezone('Asia/Taipei')->format('c'),
                'calendarId' => $calendarId
            ]);
        } catch (\Exception $e) {
            Log::error('Event update failed', [
                'error' => $e->getMessage(),
                'calendarId' => $calendarId ?? null,
                'eventId' => $eventId
            ]); // 調試用

            return response()->json(['error' => '更新事件失敗：' . $e->getMessage()], 500);
        }
    }

    // 刪除日曆事件
    public function deleteEvent(Request $request, $eventId)
    {
        try {
            $user = Auth::user();
            $this->setupClient($user);

            $service = new GoogleCalendar($this->client);
            $calendarId = $request->input('calendarId', 'primary');  // 從請求中獲取日曆ID

            $service->events->delete($calendarId, $eventId);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete event: ' . $e->getMessage()], 500);
        }
    }

    // 輔助方法來設置客戶端並檢查 Token
    private function setupClient($user)
    {
        if (!$user->google_token) {
            throw new \Exception('Not connected to Google Calendar');
        }

        // 將 JSON 字符串解碼為數組
        $accessToken = json_decode($user->google_token, true);
        if (!$accessToken) {
            throw new \Exception('Invalid token format');
        }

        $this->client->setAccessToken($accessToken);

        // 檢查並刷新訪問令牌
        $this->checkAndRefreshAccessToken($user, $accessToken);
    }

    // 檢查令牌是否過期並刷新
    private function checkAndRefreshAccessToken($user, array $accessToken)
    {
        if ($this->client->isAccessTokenExpired()) {
            if (isset($accessToken['refresh_token'])) {
                try {
                    $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);
                    if (!isset($newAccessToken['error'])) {
                        // 確保保留原有的 refresh_token
                        if (!isset($newAccessToken['refresh_token'])) {
                            $newAccessToken['refresh_token'] = $accessToken['refresh_token'];
                        }

                        User::where('id', $user->id)->update([
                            'google_token' => json_encode($newAccessToken)
                        ]);

                        $this->client->setAccessToken($newAccessToken);
                        return;
                    }
                } catch (\Exception $e) {
                    Log::error('Token refresh failed: ' . $e->getMessage());
                    // 清除無效的 token
                    User::where('id', $user->id)->update(['google_token' => null]);
                    throw new \Exception('需要重新授權 Google Calendar', 401);
                }
            }

            // 清除無效的 token
            User::where('id', $user->id)->update(['google_token' => null]);
            throw new \Exception('需要重新授權 Google Calendar', 401);
        }
    }

    // 新增一個重新授權的方法
    public function reconnect()
    {
        $user = Auth::user();
        if ($user) {
            // 清除現有的 token
            User::where('id', $user->id)->update(['google_token' => null]);
        }
        return redirect()->route('connect.google.calendar');
    }

    // 在控制器中添加輔助方法
    private function convertBrToNewline($text)
    {
        if (empty($text)) return '';

        // 先解碼 Unicode 轉義序列
        $text = json_decode('"' . $text . '"');

        // 處理所有可能的換行標記
        $text = str_replace(
            ['<br />', '<br/>', '<br>', '\n', '\r\n'],
            "\n",
            $text
        );

        // 解碼 HTML 實體
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }

    private function convertNewlineToBr($text)
    {
        if (empty($text)) return '';

        // 統一換行符
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 轉換為 <br/>
        $text = str_replace("\n", '<br/>', $text);

        return trim($text);
    }
}
