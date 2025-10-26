{{-- DaisyUI Toast Notifications --}}
<div class="toast toast-top toast-end z-50">
    <div
        x-data="{ notifications: [] }"
        @notify.window="notifications.push($event.detail); setTimeout(() => notifications.shift(), 5000)"
    >
        <template x-for="(notification, index) in notifications" :key="index">
            <div role="alert" class="alert" :class="{
                'alert-success': notification.type === 'success',
                'alert-error': notification.type === 'error',
                'alert-warning': notification.type === 'warning',
                'alert-info': notification.type === 'info'
            }">
                <span>
                    <strong x-text="notification.title"></strong>
                    <span x-show="notification.description" x-text="': ' + notification.description"></span>
                </span>
            </div>
        </template>
    </div>
</div>
