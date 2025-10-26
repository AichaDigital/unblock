{{-- DaisyUI Toast Notifications --}}
<div class="toast toast-top toast-end">
    <div
        x-data="{ notifications: [] }"
        @notify.window="notifications.push($event.detail); setTimeout(() => notifications.shift(), 5000)"
    >
        <template x-for="(n, index) in notifications" :key="index">
            <div role="alert" :class="'alert alert-' + n.type">
                <span x-text="n.title + (n.description ? ': ' + n.description : '')"></span>
            </div>
        </template>
    </div>
</div>
