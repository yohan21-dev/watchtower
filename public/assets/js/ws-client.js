/**
 * Connects to the STI CCTV Portal websocket server and keeps every
 * [data-status-for="nvr:ID"] / [data-status-for="camera:ID"] element on the
 * page in sync in real time, without a page reload.
 *
 * Expects a global `STI_WS_URL` to be set by the page (see dashboard.php).
 */
(function () {
    const indicatorDot = document.getElementById('wsDot');
    const indicatorText = document.getElementById('wsText');
    let socket;
    let retryDelay = 1500;

    function setIndicator(state) {
        if (!indicatorDot) return;
        indicatorDot.classList.remove('connected', 'disconnected');
        if (state === 'connected') {
            indicatorDot.classList.add('connected');
            indicatorText.textContent = 'Live';
        } else {
            indicatorDot.classList.add('disconnected');
            indicatorText.textContent = 'Reconnecting…';
        }
    }

    function applyStatus(type, id, status) {
        const dot = document.querySelector(`[data-status-dot="${type}:${id}"]`);
        const label = document.querySelector(`[data-status-label="${type}:${id}"]`);
        if (dot) dot.setAttribute('data-status', status);
        if (label) {
            label.setAttribute('data-status', status);
            label.textContent = status;
        }
    }

    function connect() {
        if (typeof STI_WS_URL === 'undefined' || !STI_WS_URL) return;

        socket = new WebSocket(STI_WS_URL);

        socket.addEventListener('open', () => {
            setIndicator('connected');
            retryDelay = 1500;
            socket.send(JSON.stringify({ type: 'ping' }));
        });

        socket.addEventListener('message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.type === 'status_update' && Array.isArray(msg.updates)) {
                    msg.updates.forEach((u) => applyStatus(u.type, u.id, u.status));
                }
            } catch (e) {
                /* ignore malformed frame */
            }
        });

        socket.addEventListener('close', () => {
            setIndicator('disconnected');
            setTimeout(connect, retryDelay);
            retryDelay = Math.min(retryDelay * 1.5, 15000);
        });

        socket.addEventListener('error', () => {
            socket.close();
        });
    }

    document.addEventListener('DOMContentLoaded', connect);
})();
