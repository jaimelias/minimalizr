window.dyTurnstileWaiters = window.dyTurnstileWaiters || {};
window.dyTurnstileWidgets = window.dyTurnstileWidgets || {};


const notifyTurnstile = (widgetId, type, value) => {
	const waiter = window.dyTurnstileWaiters[widgetId];

	if (!waiter || typeof waiter[type] !== 'function') {
		return;
	}

	waiter[type](value);
};

const createTurnstileWidget = ({
	container,
	action,
	id
}) => {

	const {turnstileSiteKey} = turnstileArgs;

	window.dyTurnstileWidgets[id] = turnstile.render(container, {
		sitekey: turnstileSiteKey,
		action,
		execution: 'execute',
		appearance: 'interaction-only',
		retry: 'never',
		'refresh-expired': 'never',
		'refresh-timeout': 'never',
		callback: token => notifyTurnstile(window.dyTurnstileWidgets[id], 'resolve', token),
		'before-interactive-callback': () =>
			notifyTurnstile(window.dyTurnstileWidgets[id], 'pause'),
		'after-interactive-callback': () =>
			notifyTurnstile(window.dyTurnstileWidgets[id], 'resume'),
		'timeout-callback': () =>
			notifyTurnstile(
				window.dyTurnstileWidgets[id],
				'reject',
				new Error(`Turnstile "${id}" challenge timed out.`)
			),
		'expired-callback': () =>
			notifyTurnstile(
				window.dyTurnstileWidgets[id],
				'reject',
				new Error(`Turnstile "${id}" token expired.`)
			),
		'error-callback': code =>
			notifyTurnstile(
				window.dyTurnstileWidgets[id],
				'reject',
				new Error(`Turnstile "${id}" error: ${code}`)
			)
	});

}

createTurnstileWidget({
	id: 'turnstileWidget1', 
	container: '#turnstile-container-1', 
	action: 'sign-transaction'
});

createTurnstileWidget({
	id: 'turnstileWidget2', 
	container: '#turnstile-container-2', 
	action: 'submit-transaction'
});
