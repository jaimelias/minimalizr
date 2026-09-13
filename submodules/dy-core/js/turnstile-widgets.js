window.dyTurnstileWaiters = window.dyTurnstileWaiters || {};
window.dyTurnstileWidgets = window.dyTurnstileWidgets || {};


const notifyTurnstile = (widgetId, type, value) => {
	const waiter = window.dyTurnstileWaiters[widgetId];

	if (!waiter) {
		return;
	}

	delete window.dyTurnstileWaiters[widgetId];

	if (type === 'resolve') {
		waiter.resolve(value);
	} else {
		waiter.reject(value);
	}
};

const createTurnstileWidget = ({
	container,
	action,
	id,
	appearance
}) => {

	window.dyTurnstileWidgets[id] = turnstile.render(container, {
		sitekey: turnstileSiteKey,
		action,
		appearance,
		callback: token => notifyTurnstile(window.dyTurnstileWidgets[id], 'resolve', token),
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
	action: 'sign-transaction',
	appearance: 'execute'
});

createTurnstileWidget({
	id: 'turnstileWidget2', 
	container: '#turnstile-container-2', 
	action: 'submit-transaction',
	appearance: 'interaction-only'
});