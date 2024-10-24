@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col">
                <h1>我的日曆</h1>
            </div>
            <div class="col-auto">
                <div class="input-group">
                    <input type="month" id="monthPicker" class="form-control">
                    <button class="btn btn-primary" id="goToDate">前往</button>
                </div>
            </div>
        </div>
        <div id='calendar'></div>
    </div>

    <!-- 新增事件的 Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">事件詳情</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="eventForm">
                        <div class="mb-3">
                            <label class="form-label">標題</label>
                            <input type="text" class="form-control" id="eventTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">開始時間</label>
                            <input type="datetime-local" class="form-control" id="eventStart" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">結束時間</label>
                            <input type="datetime-local" class="form-control" id="eventEnd" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-danger" id="deleteEvent" style="display: none;">刪除</button>
                    <button type="button" class="btn btn-primary" id="saveEvent">儲存</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet' />
    <style>
        /* 自定義樣式 */
        #monthPicker {
            min-width: 200px;
        }
    </style>
@endpush

@push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/locales/zh-tw.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendar;
            var eventModal;
            var currentEvent = null;
            var monthPicker = document.getElementById('monthPicker');
            var goToDateBtn = document.getElementById('goToDate');

            // 設置月份選擇器的初始值為當前月份
            var today = new Date();
            monthPicker.value = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');

            // 初始化 Modal
            eventModal = new bootstrap.Modal(document.getElementById('eventModal'));

            // 初始化 FullCalendar
            calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                timeZone: 'Asia/Taipei',
                locale: 'zh-tw',
                firstDay: 1,
                events: '{{ route('get.events') }}',
                editable: true,
                selectable: true,
                select: function(info) {
                    currentEvent = null;
                    document.getElementById('eventTitle').value = '';
                    document.getElementById('eventStart').value = formatDateTime(info.start);
                    document.getElementById('eventEnd').value = formatDateTime(info.end);
                    document.getElementById('deleteEvent').style.display = 'none';
                    eventModal.show();
                },
                eventClick: function(info) {
                    currentEvent = info.event;
                    document.getElementById('eventTitle').value = currentEvent.title;
                    document.getElementById('eventStart').value = formatDateTime(currentEvent.start);
                    document.getElementById('eventEnd').value = formatDateTime(currentEvent.end);
                    document.getElementById('deleteEvent').style.display = 'block';
                    eventModal.show();
                },
                eventDrop: function(info) {
                    updateEvent(info.event);
                },
                eventResize: function(info) {
                    updateEvent(info.event);
                }
            });

            calendar.render();

            // 監聽月份選擇器變更
            goToDateBtn.addEventListener('click', function() {
                if (monthPicker.value) {
                    var date = new Date(monthPicker.value + '-01');
                    calendar.gotoDate(date);
                }
            });

            // 當日曆視圖改變時更新月份選擇器
            calendar.on('datesSet', function(info) {
                var date = info.view.currentStart;
                monthPicker.value = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            });

            // 儲存事件
            document.getElementById('saveEvent').addEventListener('click', function() {
                var title = document.getElementById('eventTitle').value;
                var start = document.getElementById('eventStart').value;
                var end = document.getElementById('eventEnd').value;

                if (currentEvent) {
                    // 更新現有事件
                    fetch('{{ url('events') }}/' + currentEvent.id, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                title: title,
                                start: start,
                                end: end
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            currentEvent.setProp('title', data.title);
                            currentEvent.setStart(data.start);
                            currentEvent.setEnd(data.end);
                            eventModal.hide();
                        });
                } else {
                    // 創建新事件
                    fetch('{{ route('events.create') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                title: title,
                                start: start,
                                end: end
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            calendar.addEvent(data);
                            eventModal.hide();
                        });
                }
            });

            // 刪除事件
            document.getElementById('deleteEvent').addEventListener('click', function() {
                if (currentEvent) {
                    if (confirm('確定要刪除這個事件嗎？')) {
                        fetch('{{ url('events') }}/' + currentEvent.id, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                currentEvent.remove();
                                eventModal.hide();
                            });
                    }
                }
            });

            // 輔助函數：格式化日期時間
            function formatDateTime(date) {
                const d = new Date(date);
                // 轉換為台北時間
                const taipeiTime = new Date(d.toLocaleString('en-US', {
                    timeZone: 'Asia/Taipei'
                }));
                return taipeiTime.toISOString().slice(0, 16);
            }

            // 輔助函數：更新事件
            function updateEvent(event) {
                fetch('{{ url('events') }}/' + event.id, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            start: event.start.toISOString(),
                            end: event.end ? event.end.toISOString() : event.start.toISOString()
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        // 如果需要，可以更新事件顯示
                    });
            }
        });
    </script>
@endpush
