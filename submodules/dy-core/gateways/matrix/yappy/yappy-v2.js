/* Yappy browser events are hints. Payment approval comes exclusively from the IPN. */
(() => {
    const mount = async () => {
        const root = document.querySelector('#dy-yappy-v2');
        const config = window.dyYappyV2;
        if (!root || !config) return;
        const button = root.querySelector('btn-yappy');
        const message = root.querySelector('[role="status"]');
        let starting = false;
        let checking = false;
        let finished = false;
        let timer;
        let polls = 0;

        const request = async (url, method = 'GET') => {
            const response = await fetch(method === 'GET' ? `${url}${url.includes('?') ? '&' : '?'}tx_id=${encodeURIComponent(config.txId)}` : url, {
                method, credentials: 'same-origin', cache: 'no-store',
                headers: {'Content-Type': 'application/json', 'X-Dy-Yappy-Token': config.csrf},
                ...(method === 'POST' ? {body: JSON.stringify({tx_id: config.txId})} : {}),
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                const error = new Error(data.message || config.unavailable);
                error.status = response.status;
                throw error;
            }
            return data;
        };

        const checkStatus = async () => {
            if (checking || finished) return;
            checking = true;
            clearTimeout(timer);
            try {
                const data = await request(config.statusUrl);
                if (['approved', 'declined', 'cancelled', 'expired', 'error'].includes(data.status)) {
                    finished = true;
                    window.location.reload();
                    return;
                }
                if (['creating', 'uncertain'].includes(data.state)) {
                    button.hidden = true;
                    message.textContent = config.uncertain;
                }
            } catch (error) {
                message.textContent = error.message;
                if (error.status === 403) {
                    finished = true;
                    button.hidden = true;
                }
            } finally {
                checking = false;
                if (!finished && ++polls < 180) timer = setTimeout(checkStatus, 5000);
            }
        };

        button.addEventListener('eventClick', async () => {
            if (starting || finished) return;
            starting = true;
            button.isButtonLoading = true;
            message.textContent = config.waiting;
            try {
                const data = await request(config.startUrl, 'POST');
                button.eventPayment(data.body);
            } catch (error) {
                message.textContent = error.message;
            } finally {
                starting = false;
                button.isButtonLoading = false;
                await checkStatus();
            }
        });
        const onResult = () => {
            button.isButtonLoading = false;
            message.textContent = config.waiting;
            checkStatus();
        };
        button.addEventListener('eventSuccess', onResult);
        button.addEventListener('eventError', onResult);
        button.addEventListener('isYappyOnline', event => {
            if (event.detail === false) message.textContent = config.unavailable;
        });

        // Reading status on refresh never creates an order or opens the payment modal.
        checkStatus();
        try {
            if (!window.customElements.get('btn-yappy')) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.type = 'module';
                    script.src = config.sdkUrl;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }
            await window.customElements.whenDefined('btn-yappy');
        } catch {
            message.textContent = config.unavailable;
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, {once: true});
    else mount();
})();
