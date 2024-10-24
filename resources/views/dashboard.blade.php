@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('儀表板') }}</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <h2>歡迎, {{ Auth::user()->name }}!</h2>
                        <p>您已成功登入。</p>

                        @if (Auth::user()->google_id)
                            <div class="alert alert-info" role="alert">
                                <h4 class="alert-heading">Google 帳戶資訊</h4>
                                您是通過 Google 帳戶登入的。
                                <br>
                                Google 帳戶 ID: {{ Auth::user()->google_id }}
                                <br>
                                Google 帳戶郵箱: {{ Auth::user()->email }}

                                <div class="mt-3">
                                    <a href="{{ route('google.reauthorize') }}" class="btn btn-primary"
                                        onclick="return confirm('確定要重新授權嗎？這將清除現有的授權並重新連接。')">
                                        重新授權 Google 帳戶
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-secondary" role="alert">
                                您是通過常規方式登入的。
                            </div>
                        @endif

                        <div class="mt-4">
                            <h3>快速連結</h3>
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <a href="#">個人資料設定</a>
                                </li>
                                <li class="list-group-item">
                                    <a href="#">我的訊息</a>
                                </li>
                                <li class="list-group-item">
                                    <a href="#">任務列表</a>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-4">
                            <h3>系統狀態</h3>
                            <p>當前在線用戶數: XX</p>
                            <p>系統版本: X.X.X</p>
                        </div>

                        @if (Auth::user()->google_token)
                            <div class="alert alert-success">
                                您已連接 Google Calendar
                                <a href="{{ route('calendar') }}" class="btn btn-primary">查看日曆</a>
                            </div>
                        @else
                            <div class="alert alert-info">
                                您尚未連接 Google Calendar
                                <a href="{{ route('connect.google.calendar') }}" class="btn btn-primary">
                                    連接 Google Calendar
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
