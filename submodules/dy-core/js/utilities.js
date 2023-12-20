
const excludeGeolocation = ['country_code3', 'is_eu', 'country_tld', 'languages', 'country_flag', 'geoname_id', 'time_zone_current_time', 'time_zone_dst_savings', 'time_zone_is_dst', 'zipcode', 'continent_code', 'continent_name'];
const storeFieldNames = ['first_name', 'lastname', 'country_calling_code', 'phone', 'email', 'repeat_email', 'country', 'city', 'address'];

//refresh page to removed disabled button
window.addEventListener('pageshow', event =>  {
    const historyTraversal = event.persisted;

    if ( historyTraversal && sessionStorage.getItem('last_form_submit_url') === window.location.href ) 
    {
        sessionStorage.removeItem('last_form_submit_url');
        window.location.reload();
    }
});

jQuery(() => {

    storePopulate();
	whatsappButton();
});


const whatsappButton = () => {

    const modal = jQuery('#dy-whatsapp-modal');
    const qrcode = jQuery('#dy-whatsapp-qrcode');
    const link = jQuery('#dy-whatsapp-link');

    jQuery('.button-whatsapp').each(function(){

        const el = jQuery(this);

        jQuery(el).click(function(e){

            e.preventDefault();

            const href = jQuery(el).attr('href');

            if(/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))
            {
                window.location = href;
                return true;;
            }

            jQuery(qrcode).text('');

            new QRCode("dy-whatsapp-qrcode", {
                text: href,
                width: 200,
                height: 200,
                colorDark : "#075e54",
                colorLight : "#dcf8c6",
                correctLevel : QRCode.CorrectLevel.H
            });

            jQuery(link).attr({href});

            jQuery(modal).toggleClass('hidden');

        });


        //closes the modal
        jQuery('#dy-whatsapp-modal-close').click(function(){

            jQuery(modal).toggleClass('hidden');

        });



    });

};

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




const getGeoLocation = async () => {
    const {ipGeoLocation} = dyCoreArgs;
    let output = [];

    return fetch(`https://api.ipgeolocation.io/ipgeo?apiKey=${ipGeoLocation.token}`).then(resp => {
        if(resp.ok)
        {
            return resp.json();
        }
        else
        {
            throw Error(resp.statusText);
        }
    }).then(data => {

        for(let k in data)
        {
            if(typeof data[k] !== 'object')
            {
                if(!excludeGeolocation.includes(k))
                {
                    output.push({name: `geo_loc_${k}`, value: data[k]});
                }
            }
        }

        return output;
    })
};

const getNonce = async () => {
    const {homeUrl} = dyCoreArgs;
    const now = Date.now();
    const url = `${homeUrl}/wp-json/dy-core/args?timestamp=${now}`;
    const headers = new Headers();
    headers.append('pragma', 'no-cache');
    headers.append('cache-control', 'no-cache'); 
    
    const init = {
        method: 'GET',
        headers,
    };

    var req = new Request(url);

    return fetch(req, init).then(resp => {
        if(resp.ok)
        {
            return resp.json();
        }
        else
        {
            throw Error('Unable to get nonce');
        }
    }).then(data => data.dy_nonce);
};

const handleSubmitButton = form => {
    jQuery(form).find('button').prop('disabled', true);
    sessionStorage.setItem('last_form_submit_url', window.location.href);
};

const createFormSubmit = async (form) => {

    //disable button to prevent double-click
    handleSubmitButton(form);

    const {ipGeoLocation, lang} = dyCoreArgs;
	let formFields = formToArray(form);
	const method = String(jQuery(form).attr('data-method')).toLowerCase();
	let action = jQuery(form).attr('data-action');  
	const nonce = jQuery(form).attr('data-nonce') || '';  
    const hasEmail = (typeof formFields.find(i => i.name === 'email') !== 'undefined') ? true : false;
    let hashParams = jQuery(form).attr('data-hash-params') || '';
    const gclid = (jQuery(form).attr('data-gclid')) ? true : false;

    if(nonce)
    {
        const nonceData = await getNonce();

        if(nonceData)
        {
            if(nonce === 'slug')
            {
                action += `/${nonceData}`;
            }
            else if(nonce === 'param')
            {
                formFields.push({name: 'dy_nonce', value: nonceData});
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
    
            if(storeFieldNames.includes(name))
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

        //geolocation
        if(ipGeoLocation)
        {
            const geoLocation = await getGeoLocation();

            if(geoLocation)
            {
                formFields = [...formFields, ...geoLocation];
            }
        }
    }

    if(hashParams)
    {
        let hash = '';
        hashParams = hashParams.split(',');

        if(Array.isArray(hashParams))
        {
            hashParams.forEach(v => {
                hash += jQuery(form).find(`[name="${v}"]`).val();
            });
        }

        if(hash)
        {
            formFields.push({name: 'hash', value: sha512(hash)});
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