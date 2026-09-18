
const storeFieldNames = ['first_name', 'lastname', 'country_calling_code', 'phone', 'email', 'repeat_email', 'country', 'city', 'address'];

//refresh page to removed disabled button
window.addEventListener('pageshow', event =>  {
    const historyTraversal = event.persisted;


    if(typeof Storage !== 'undefined')
    {
        if ( historyTraversal && sessionStorage.getItem('last_form_submit_url') === window.location.href ) 
        {
            sessionStorage.removeItem('last_form_submit_url');
            window.location.reload();
        }
    }

});

jQuery(() => {

    storePopulate();
	whatsappButton();
});

const sha512 = async (message)  => {
  // Encode the string as UTF-8 bytes
  const msgBuffer = new TextEncoder().encode(message);

  // Hash it
  const hashBuffer = await crypto.subtle.digest('SHA-512', msgBuffer);

  // Convert ArrayBuffer to hex string
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

  return hashHex;
}


const whatsappButton = async () => {
    const modal = jQuery('#dy-whatsapp-modal')
    const qrcode = jQuery('#dy-whatsapp-qrcode')
    const link = jQuery('#dy-whatsapp-link > a')

    let { whatsappNumber } = dyCoreArgs
    let href = ''

    jQuery('#dy-whatsapp-modal-close').click(() => {
        modal.addClass('hidden')
    })

    jQuery('.button-whatsapp').click(async e => {
        e.preventDefault()

        if (!whatsappNumber) return

        if (!href) {
            const url = new URL(whatsappNumber, 'https://wa.me')
            const pageTitle = jQuery('title').text().trim()

            if (pageTitle) {
                url.searchParams.set('text', pageTitle)
            }

            href = url.href
        }

        if (/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            window.location = href
            return
        }

        qrcode.empty()

        new QRCode('dy-whatsapp-qrcode', {
            text: href,
            width: 200,
            height: 200,
            colorDark: '#075e54',
            colorLight: '#dcf8c6',
            correctLevel: QRCode.CorrectLevel.H
        })

        link.attr('href', href)

        modal.removeClass('hidden')
    })
}

const formToArray = thisForm => {
   
    let data = thisForm
        .serializeArray()
        .map(o => {

        let {value} = o;

        if(typeof value === 'string')
        {
            o.value = o.value.trim();
        }

        return o;

     });
    
     thisForm.find('input:checkbox').each(function () { 
        const {name, checked: value} = this;

         data.push({ name, value });
     });
 
     thisForm.find(':disabled').each(function () { 
        const {name, value} = this;

         data.push({ name, value });
     });
     
     return data;
 };



let nonceCache
let nonceExpiresAt = 0

const getNonce = async (retry = 0) => {
    const { wpJsonUrl } = dyCoreArgs

    if (!Number.isInteger(retry) || retry < 0 || retry > 10) {
        throw new RangeError('retry must be an integer between 0 and 10')
    }

    if (Date.now() < nonceExpiresAt) {
        return nonceCache
    }

    for (let attempt = 0; attempt <= retry; attempt++) {
        const url = new URL(`${wpJsonUrl}/args`)

        url.searchParams.set('timestamp', Date.now().toString())

        const headers = new Headers({
            pragma: 'no-cache',
            'cache-control': 'no-cache'
        })

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers
            })

            if (!response.ok) {
                let errorBody = ''

                try {
                    errorBody = await response.text()
                } catch {
                    errorBody = '[unable to read response body]'
                }

                throw new Error(
                    `Unable to get nonce from ${url}: ${response.status} ${response.statusText}` +
                    `${errorBody ? ` - ${errorBody}` : ''}\n${url}`
                )
            }

            const data = await response.json()

            nonceCache = data
            nonceExpiresAt = Date.now() + 10_000

            return data
        } catch (error) {
            console.error(
                `Nonce request failed (${attempt + 1}/${retry + 1}):`,
                error
            )

            if (attempt >= retry) {
                throw error
            }

            // Immediately continues to the next attempt
        }
    }
}

const handleSubmitButton = thisForm => {

    jQuery(thisForm).find('button').prop('disabled', true);

    if(typeof Storage !== 'undefined')
    {
        sessionStorage.setItem('last_form_submit_url', window.location.href);
    }
    
};

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

const executeTurnstileWithRetry = async (
    widgetId,
    {
        maxRetries = 5,
        timeoutMs = 10000,
        retryDelayMs = 1000
    } = {}
) => {
    let lastError;

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
            const token = await new Promise((resolve, reject) => {
                let settled = false;
                let timeoutId;

                const finish = (callback, value) => {
                    if (settled) return;

                    settled = true;
                    clearTimeout(timeoutId);
                    delete window.dyTurnstileWaiters[widgetId];
                    callback(value);
                };

                timeoutId = setTimeout(() => {
                    finish(reject, new Error('Turnstile token request timed out.'));
                }, timeoutMs);

                window.dyTurnstileWaiters[widgetId] = {
                    resolve: token => token
                        ? finish(resolve, token)
                        : finish(reject, new Error('Turnstile returned an empty token.')),
                    reject: error => finish(reject, error)
                };

                try {
                    turnstile.reset(widgetId);
                    turnstile.execute(widgetId);
                } catch (error) {
                    finish(reject, error);
                }
            });

            return token;
        } catch (error) {
            lastError = error;

            if (attempt === maxRetries) break;

            console.warn(
                `Turnstile attempt ${attempt + 1}/${maxRetries + 1} failed. Retrying...`,
                error
            );

            await sleep(retryDelayMs);
        }
    }

    throw lastError || new Error('Unable to obtain a Turnstile token.');
};

const signDyTransaction = async ({signUrl, signRequest, widgetId, maxRetries = 2}) => {
    let tx_id;
    let lastSignError;

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
            if (attempt > 0) {
                await sleep(10000);
            }

            // Turnstile tokens are single-use, so retries need fresh tokens.
            const signTransactionToken = await executeTurnstileWithRetry(widgetId);
            const signBody = new URLSearchParams({
                ...signRequest,
                'cf-turnstile-response': signTransactionToken
            });

            const signResponse = await fetch(signUrl, {
                method: 'POST',
                body: signBody
            });

            if (!signResponse.ok) {
                throw new Error(`Transaction signing failed: ${signResponse.status}`);
            }

            ({tx_id} = await signResponse.json());

            if (!tx_id) {
                throw new Error('Transaction signing returned no transaction ID.');
            }

            return tx_id;
        } catch (error) {
            lastSignError = error;

            if (attempt === maxRetries) {
                throw new Error(
                    `Transaction signing failed after ${maxRetries + 1} attempts.`,
                    {cause: error}
                );
            }

            console.warn(
                `Transaction signing attempt ${attempt + 1}/${maxRetries + 1} failed. Retrying in 10 seconds...`,
                error
            );
        }
    }

    throw new Error('Transaction signing failed.', {cause: lastSignError});
};

const hasTurnstileWidgets = () => (
    typeof turnstile !== 'undefined'
    && typeof window?.dyTurnstileWidgets?.turnstileWidget1 !== 'undefined'
    && typeof window?.dyTurnstileWidgets?.turnstileWidget2 !== 'undefined'
    && window.dyTurnstileWaiters
);

const createFormSubmit = async form => {


    //disable button to prevent double-click
    handleSubmitButton(form);

    const {lang} = dyCoreArgs;
    let formFields = formToArray(form);
	const method = String(form.attr('data-method')).toLowerCase();
	let action = atob(form.attr('data-action'));  
    const hasEmail = formFields.some(i => i.name === 'email');

    const isStorageAvailable = typeof Storage !== 'undefined';

    if(method === 'post' && hasEmail)
    {
        //lang param
        formFields.push({name: 'lang', value: lang});

        //store contact fields in sesstionStorage
        formFields.forEach(o => {
            const {name, value} = o;
    
            if(storeFieldNames.includes(name) && isStorageAvailable)
            {
                sessionStorage.setItem(name, value);
            }
        });

        if (hasTurnstileWidgets()) {
            try {

                const {turnstileWidget1, turnstileWidget2} = window.dyTurnstileWidgets || {};

                

                const { wpJsonUrl, post_id } = dyCoreArgs;
                const signUrl = new URL(`${wpJsonUrl}/tx/${post_id}`);
                const signRequest = {
                    dy_request: form.find('[name="dy_request"]').val() || '',
                    email: form.find('[name="email"]').val() || '',
                    action: 'sign-transaction'
                };
                const tx_id = await signDyTransaction({
                    signUrl,
                    signRequest,
                    widgetId: turnstileWidget1
                });

                formFields = formFields.filter(({ name }) => (
                    name !== 'cf-turnstile-response' && name !== 'tx_id'
                ));
                formFields.push({ name: 'tx_id', value: tx_id });

                const submitTransactionToken = await executeTurnstileWithRetry(turnstileWidget2);

                console.log({submitTransactionToken})

                formFields.push({
                    name: 'cf-turnstile-response',
                    value: submitTransactionToken
                });

            } catch (error) {
                console.error('Turnstile submission failed:', error);
                form.find('button').prop('disabled', false);
                alert(error.message || 'Unable to submit the form. Please try again.');
                return false;
            }
        }
    }

    formSubmit({method, action, formFields});
};

const formSubmit = ({method, action, formFields}) => {

	const newForm =  document.createElement('form');
	newForm.method = method;
	newForm.action = action;    


    formFields.forEach(i => {
        let input = document.createElement('input');
        input.name = i.name;
        input.value = i.value;
        newForm.appendChild(input);
    });

    //console.log({formFields});

    document.body.appendChild(newForm);

    newForm.submit();
};

const storePopulate = () => {
	
    if(typeof Storage !== 'undefined')
    {
        jQuery('form').each(function(){
            const thisForm = jQuery(this);

            if(thisForm.attr('data-action') &&  thisForm.attr('data-method'))
            {
                const formFields = formToArray(thisForm);
                
                formFields.forEach(i => {
                    const name = i.name;
                    const value = sessionStorage.getItem(name);
                    const field = thisForm.find('[name="'+name+'"]');
                    const tag = field.prop('tagName');
                    const type = field.attr('type');
                    
                    if(value && storeFieldNames.includes(name))
                    {
                        if(tag == 'INPUT')
                        {
                            if(type == 'checkbox' || type == 'radio')
                            {
                                field.prop('checked', true);
                            }
                            else
                            {
                                field.val(value);
                            }
                        }
                        else if(tag == 'TEXTAREA' || tag == 'SELECT')
                        {
                            field.val(value);
                        }			
                    }
                });
            }
        });
    }
}

const sendGa4Event = (eventName, eventParams = {}) => {
	const destination = (
		typeof dyCoreArgs !== 'undefined'
		&& dyCoreArgs.google_analytics_id
	)
		? dyCoreArgs.google_analytics_id
		: '';

	if(typeof window.gtag !== 'function' || !destination)
	{
		return false;
	}

    window.gtag('event', eventName, {
        ...eventParams,
        send_to: destination
    });

	return true;
};

