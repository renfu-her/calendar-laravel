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
                            <label class="form-label">描述</label>
                            <textarea class="form-control" id="eventDescription" rows="3"></textarea>
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

        .gcal-event-primary {
            background-color: #1a73e8 !important;
            border-color: #1a73e8 !important;
            color: #ffffff !important;
        }

        .gcal-event-secondary {
            background-color: #137333 !important;
            border-color: #137333 !important;
            color: #ffffff !important;
        }

        .gcal-event-tertiary {
            background-color: #ea8600 !important;
            border-color: #ea8600 !important;
            color: #ffffff !important;
        }

        /* 懸停效果 */
        .gcal-event-primary:hover,
        .gcal-event-secondary:hover,
        .gcal-event-tertiary:hover {
            opacity: 0.9;
        }

        /* 確保文字清晰可見 */
        .fc-event-title {
            font-weight: 500 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        #eventDescription {
            white-space: pre-wrap;
            min-height: 80px;
            font-family: inherit;
            line-height: 1.5;
        }
    </style>
@endpush

@push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/locales/zh-tw.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        $(document).ready(function() {
            // 防止重複初始化
            if (window.calendarInitialized) {
                return;
            }
            window.calendarInitialized = true;

            var calendar;
            var eventModal;
            var currentEvent = null;
            var $monthPicker = $('#monthPicker');
            var $goToDate = $('#goToDate');

            // 初始化 Modal
            eventModal = new bootstrap.Modal($('#eventModal')[0]);

            // 移除已存在的事件監聽器
            $('#saveEvent').off('click');
            $('#deleteEvent').off('click');
            $goToDate.off('click');

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
                eventSources: [{
                        googleCalendarId: 'renfu.her@gmail.com',
                        className: 'gcal-event-primary',
                        color: '#4285f4',
                        id: 'gc:renfu.her@gmail.com',
                        eventDataTransform: function(event) {
                            if (!event.extendedProps || !event.extendedProps.processed) {
                                event.extendedProps = event.extendedProps || {};
                                event.extendedProps.calendarId = 'renfu.her@gmail.com';
                                event.extendedProps.processed = true;
                            }
                            return event;
                        }
                    },
                    {
                        googleCalendarId: 'zivhsiao@gmail.com',
                        className: 'gcal-event-secondary',
                        color: '#34a853',
                        id: 'gc:zivhsiao@gmail.com',
                        eventDataTransform: function(event) {
                            event.extendedProps = event.extendedProps || {};
                            event.extendedProps.calendarId = 'zivhsiao@gmail.com';
                            return event;
                        }
                    },
                    {
                        googleCalendarId: 'jenfuhe@besttour.com.tw',
                        className: 'gcal-event-tertiary',
                        color: '#fbbc05',
                        id: 'gc:jenfuhe@besttour.com.tw',
                        eventDataTransform: function(event) {
                            event.extendedProps = event.extendedProps || {};
                            event.extendedProps.calendarId = 'jenfuhe@besttour.com.tw';
                            return event;
                        }
                    }
                ],
                // 需要設置 Google Calendar API Key
                googleCalendarApiKey: '{{ config('services.google.calendar_api_key') }}',
                editable: true, // 啟用拖放編輯
                eventDraggable: true, // 允許事件拖動
                eventResizeable: true, // 允許調整事件長度

                // 事件拖放後的處理
                eventDrop: function(info) {
                    const event = info.event;
                    const startDate = new Date(event.start);
                    const endDate = event.end ? new Date(event.end) : startDate;

                    // 獲取 calendarId
                    const calendarId = event.source ? 
                        event.source.id.replace('gc:', '') : // 從事件源獲取
                        event.extendedProps.calendarId; // 從擴展屬性獲取

                    console.log('Event Drop Data:', {
                        eventId: event.id,
                        calendarId: calendarId,
                        start: startDate,
                        end: endDate
                    });

                    if (!calendarId) {
                        info.revert();
                        alert('更新失敗：缺少日曆ID');
                        return;
                    }

                    $.ajax({
                        url: '{{ route('events.update', ':eventId') }}'.replace(':eventId', event.id),
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: JSON.stringify({
                            start: formatDateTime(startDate),
                            end: formatDateTime(endDate),
                            calendarId: calendarId
                        }),
                        contentType: 'application/json',
                        success: function(response) {
                            console.log('Update success:', response);
                        },
                        error: function(xhr) {
                            console.error('Update failed:', xhr.responseJSON);
                            info.revert();
                            alert('更新失敗：' + (xhr.responseJSON?.error || '未知錯誤'));
                        }
                    });
                },

                // 調整事件時長
                eventResize: function(info) {
                    const event = info.event;
                    const startDate = new Date(event.start);
                    const endDate = new Date(event.end);
                    
                    // 獲取 calendarId
                    const calendarId = event.source ? 
                        event.source.id.replace('gc:', '') : 
                        event.extendedProps.calendarId;
                    
                    console.log('Event Resize Data:', {
                        eventId: event.id,
                        calendarId: calendarId,
                        start: startDate,
                        end: endDate
                    });

                    if (!calendarId) {
                        info.revert();
                        alert('更新失敗：缺少日曆ID');
                        return;
                    }

                    $.ajax({
                        url: '{{ route('events.update', ':eventId') }}'.replace(':eventId', event.id),
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: JSON.stringify({
                            start: formatDateTime(startDate),
                            end: formatDateTime(endDate),
                            calendarId: calendarId
                        }),
                        contentType: 'application/json',
                        success: function(response) {
                            console.log('Update success:', response);
                        },
                        error: function(xhr) {
                            console.error('Update failed:', xhr.responseJSON);
                            info.revert();
                            alert('更新失敗：' + (xhr.responseJSON?.error || '未知錯誤'));
                        }
                    });
                },

                // 事件渲染時的處理（只處理顏色）
                eventDidMount: function(info) {
                    // 設置事件顏色
                    if (info.event.classNames.includes('gcal-event-primary')) {
                        info.el.style.backgroundColor = '#1a73e8';
                        info.el.style.borderColor = '#1a73e8';
                    } else if (info.event.classNames.includes('gcal-event-secondary')) {
                        info.el.style.backgroundColor = '#137333';
                        info.el.style.borderColor = '#137333';
                    } else if (info.event.classNames.includes('gcal-event-tertiary')) {
                        info.el.style.backgroundColor = '#ea8600';
                        info.el.style.borderColor = '#ea8600';
                    }
                }
            });

            // 確保只渲染一次
            if (!calendar.isRendered) {
                calendar.render();
            }

            // 移除舊的事件監聽器
            calendar.removeAllEventListeners();

            // 重新綁定事件監聽器
            $('#saveEvent').on('click', function() {
                const title = $('#eventTitle').val();
                let description = $('#eventDescription').val();
                description = convertNewlineToBr(description);
                const start = $('#eventStart').val();
                const end = $('#eventEnd').val();

                if (currentEvent) {
                    // 獲取 calendarId
                    const calendarId = currentEvent.extendedProps.calendarId;

                    // 調試日誌
                    console.log('Updating event with data:', {
                        eventId: currentEvent.id,
                        calendarId: calendarId,
                        title: title,
                        description: description,
                        start: start,
                        end: end
                    });

                    if (!calendarId) {
                        alert('錯誤：找不到日曆ID');
                        return;
                    }

                    $.ajax({
                        url: '{{ route('events.update', ':eventId') }}'.replace(':eventId',
                            currentEvent.id),
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: JSON.stringify({
                            title: title,
                            description: description,
                            start: start,
                            end: end,
                            calendarId: calendarId
                        }),
                        contentType: 'application/json',
                        success: function(data) {
                            console.log('Update success:', data);
                            currentEvent.setProp('title', data.title);
                            currentEvent.setExtendedProp('description', data.description);
                            currentEvent.setStart(data.start);
                            currentEvent.setEnd(data.end);
                            eventModal.hide();
                        },
                        error: function(xhr) {
                            console.error('Update failed:', xhr.responseJSON);
                            alert('更新失敗：' + (xhr.responseJSON?.error || '未知錯誤'));
                        }
                    });
                }
            });

            $('#deleteEvent').on('click', function() {
                if (currentEvent && confirm('確定要刪除這個事件嗎？')) {
                    $.ajax({
                        url: '{{ route('events.delete', ':eventId') }}'.replace(':eventId',
                            currentEvent.id),
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

            $goToDate.on('click', function() {
                if ($monthPicker.val()) {
                    var date = new Date($monthPicker.val() + '-01');
                    calendar.gotoDate(date);
                }
            });

            // 其他事件監聽器綁定
            calendar.on('eventClick', function(info) {
                info.jsEvent.preventDefault();
                currentEvent = info.event;

                // 調試日誌
                console.log('Current Event Data:', {
                    id: currentEvent.id,
                    title: currentEvent.title,
                    extendedProps: currentEvent.extendedProps,
                    source: currentEvent.source ? currentEvent.source.id : null
                });

                // 獲取 calendarId
                const calendarId = currentEvent.source ?
                    currentEvent.source.id.replace('gc:', '') : // 如果是從 Google Calendar 來的事件
                    currentEvent.extendedProps.calendarId; // 如果是從我們的數據來的事件

                // 保存 calendarId 到當前事件對象
                currentEvent.setExtendedProp('calendarId', calendarId);

                $('#eventTitle').val(currentEvent.title);
                let description = currentEvent.extendedProps.description || '';
                description = description.replace(/<br\s*\/?>/gi, '\n');
                $('#eventDescription').val(description);
                $('#eventStart').val(formatDateTime(currentEvent.start));
                $('#eventEnd').val(formatDateTime(currentEvent.end || currentEvent.start));
                $('#deleteEvent').show();
                $('.modal-title').text('編輯事件');
                eventModal.show();
            });

            calendar.on('eventDrop', function(info) {
                updateEvent(info.event);
            });

            calendar.on('eventResize', function(info) {
                updateEvent(info.event);
            });

            calendar.on('dateClick', function(info) {
                currentEvent = null;
                $('#eventTitle').val('');
                $('#eventDescription').val('');
                $('#eventStart').val(formatDateTime(info.date));
                $('#eventEnd').val(formatDateTime(new Date(info.date.getTime() + 4 * 60 * 60 * 1000)));
                $('#deleteEvent').hide();
                $('.modal-title').text('新增事件');
                eventModal.show();
            });

            calendar.on('eventMouseEnter', function(info) {
                const description = info.event.extendedProps.description;
                if (description) {
                    $(info.el).tooltip({
                        title: description.replace(/<br\s*\/?>/gi, '\n'),
                        placement: 'top',
                        trigger: 'hover',
                        container: 'body',
                        html: true
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

            // 輔助函數：將換行符轉換為 <br/>
            function convertNewlineToBr(text) {
                if (!text) return '';
                return text.replace(/\n/g, '<br/>');
            }

            // 輔助函數：將 <br/> 轉換為換行符
            function convertBrToNewline(text) {
                if (!text) return '';
                return text.replace(/<br\s*\/?>/gi, '\n');
            }
        });
    </script>
@endpush
