
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

const formToArray = form => {
   
    let data = jQuery(form)
        .serializeArray()
        .map(o => {

        let {value} = o;

        if(typeof value === 'string')
        {
            o.value = o.value.trim();
        }

        return o;

     });
    
     jQuery(form).find('input:checkbox').each(function () { 
        const {name, checked: value} = this;

         data.push({ name, value });
     });
 
     jQuery(form).find(':disabled').each(function () { 
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

const handleSubmitButton = form => {
    jQuery(form).find('button').prop('disabled', true);

    if(typeof Storage !== 'undefined')
    {
        sessionStorage.setItem('last_form_submit_url', window.location.href);
    }
    
};

const createFormSubmit = async (form) => {

    //disable button to prevent double-click
    handleSubmitButton(form);

    const {lang} = dyCoreArgs;
	let formFields = formToArray(form);
	const method = String(jQuery(form).attr('data-method')).toLowerCase();
	let action = atob(jQuery(form).attr('data-action'));  
	const nonce = jQuery(form).attr('data-nonce') || '';  
    const hasEmail = (typeof formFields.find(i => i.name === 'email') !== 'undefined') ? true : false;
    let hashParams = jQuery(form).attr('data-hash-params') || '';
    const gclid = (jQuery(form).attr('data-gclid')) ? true : false;

    if(nonce)
    {
        const { dy_nonce} = await getNonce();

        if(dy_nonce)
        {
            if(nonce === 'slug')
            {
                action += `/${dy_nonce}`;
            }
            else if(nonce === 'param')
            {
                formFields.push({name: 'dy_nonce', value: dy_nonce});
            }
        }
    }

    if(method === 'post' && hasEmail)
    {

        //lang param
        formFields.push({name: 'lang', value: lang});

        //store contact fields in sesstionStorage
        formFields.forEach(o => {
            const {name, value} = o;
    
            if(storeFieldNames.includes(name) && typeof Storage !== 'undefined')
            {
                sessionStorage.setItem(name, value);
            }
        });

        //tracking cookie params
        [...visitCookies, ...googleAdsCookies].forEach(x => {

            const value = getCookie(x);

            if(value)
            {
                formFields.push({name: x, value: getCookie(x)});
            }
        });
    }

    if(hashParams)
    {
        let hashMessage = '';
        hashParams = hashParams.split(',');

        if(Array.isArray(hashParams))
        {
            hashParams.forEach(v => {
                hashMessage += jQuery(form).find(`[name="${v}"]`).val();
            });
        }

        if(hashMessage)
        {
            const hash = await sha512(hashMessage);

            formFields.push({name: 'hash', value: hash});
        }
    }

    if(gclid)
    {
        const gclidValue = getCookie('gclid');

        if(gclidValue)
        {
            if(method === 'post')
            {
                const actionUrl = new URL(action);
                const {searchParams} = actionUrl;
                searchParams.set('gclid', gclidValue);
                action = actionUrl.toString();
            }
            else if(method === 'get')
            {
                formFields.push({name: 'gclid', value: gclidValue});
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

            if(jQuery(thisForm).attr('data-action') &&  jQuery(thisForm).attr('data-method'))
            {
                const formFields = formToArray(thisForm);
                
                formFields.forEach(i => {
                    const name = i.name;
                    const value = sessionStorage.getItem(name);
                    const field = jQuery(thisForm).find('[name="'+name+'"]');
                    const tag = jQuery(field).prop('tagName');
                    const type = jQuery(field).attr('type');
                    
                    if(value && storeFieldNames.includes(name))
                    {
                        if(tag == 'INPUT')
                        {
                            if(type == 'checkbox' || type == 'radio')
                            {
                                jQuery(field).prop('checked', true);
                            }
                            else
                            {
                                jQuery(field).val(value);
                            }
                        }
                        else if(tag == 'TEXTAREA' || tag == 'SELECT')
                        {
                            jQuery(field).val(value);
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