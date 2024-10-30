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

        /* Google Calendar 事件樣式 */
        .gcal-event {
            background-color: #4285f4;
            border-color: #4285f4;
        }

        .gcal-event-secondary {
            background-color: #34a853;
            border-color: #34a853;
        }

        .gcal-event-tertiary {
            background-color: #fbbc05;
            border-color: #fbbc05;
        }
    </style>
@endpush

@push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/locales/zh-tw.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        $(document).ready(function() {
            var calendar;
            var eventModal;
            var currentEvent = null;
            var $monthPicker = $('#monthPicker');
            var $goToDate = $('#goToDate');

            // 設置月份選擇器的初始值為當前月份
            var today = new Date();
            $monthPicker.val(today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0'));

            // 初始化 Modal
            eventModal = new bootstrap.Modal($('#eventModal')[0]);

            // 初始化 FullCalendar
            calendar = new FullCalendar.Calendar($('#calendar')[0], {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                timeZone: 'local',
                locale: 'zh-tw',
                firstDay: 1,
                events: '{{ route('get.events') }}',
                editable: true,
                selectable: true,
                displayEventTime: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                    meridiem: false
                },
                select: function(info) {
                    currentEvent = null;
                    $('#eventTitle').val('');
                    $('#eventStart').val(formatDateTime(info.start));
                    $('#eventEnd').val(formatDateTime(info.end));
                    $('#deleteEvent').hide();
                    eventModal.show();
                },
                eventClick: function(info) {
                    currentEvent = info.event;
                    $('#eventTitle').val(currentEvent.title);
                    
                    // 使用事件的本地時間
                    const start = currentEvent.start;
                    const end = currentEvent.end || start;
                    
                    $('#eventStart').val(formatDateTime(start));
                    $('#eventEnd').val(formatDateTime(end));
                    $('#deleteEvent').show();
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
            $goToDate.on('click', function() {
                if ($monthPicker.val()) {
                    var date = new Date($monthPicker.val() + '-01');
                    calendar.gotoDate(date);
                }
            });

            // 當日曆視圖改變時更新月份選擇器
            calendar.on('datesSet', function(info) {
                var date = info.view.currentStart;
                $monthPicker.val(date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0'));
            });

            // 儲存事件
            $('#saveEvent').on('click', function() {
                var title = $('#eventTitle').val();
                var start = $('#eventStart').val();
                var end = $('#eventEnd').val();

                if (currentEvent) {
                    // 更新現有事件
                    $.ajax({
                        url: '{{ route('events.update', ':eventId') }}'.replace(':eventId', currentEvent.id),
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: JSON.stringify({
                            title: title,
                            start: start,
                            end: end
                        }),
                        contentType: 'application/json',
                        success: function(data) {
                            currentEvent.setProp('title', data.title);
                            currentEvent.setStart(data.start);
                            currentEvent.setEnd(data.end);
                            eventModal.hide();
                        }
                    });
                } else {
                    // 創建新事件
                    $.ajax({
                        url: '{{ route('events.create') }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: JSON.stringify({
                            title: title,
                            start: start,
                            end: end
                        }),
                        contentType: 'application/json',
                        success: function(data) {
                            calendar.addEvent(data);
                            eventModal.hide();
                        }
                    });
                }
            });

            // 刪除事件
            $('#deleteEvent').on('click', function() {
                if (currentEvent && confirm('確定要刪除這個事件嗎？')) {
                    $.ajax({
                        url: '{{ route('events.delete', ':eventId') }}'.replace(':eventId', currentEvent.id),
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            currentEvent.remove();
                            eventModal.hide();
                        }
                    });
                }
            });

            // 輔助函數：格式化日期時間
            function formatDateTime(date) {
                // 確保輸入是 Date 對象
                const d = date instanceof Date ? date : new Date(date);
                
                // 獲取本地時間的年月日時分
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                
                // 直接返回本地時間格式
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            // 輔助函數：更新事件
            function updateEvent(event) {
                const startDate = new Date(event.start);
                const endDate = event.end ? new Date(event.end) : startDate;
                
                $.ajax({
                    url: '{{ route('events.update', ':eventId') }}'.replace(':eventId', event.id),
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: JSON.stringify({
                        start: formatDateTime(startDate),
                        end: formatDateTime(endDate)
                    }),
                    contentType: 'application/json',
                    success: function(data) {
                        // 如果需要，可以更新事件顯示
                    }
                });
            }
        });
    </script>
@endpush
