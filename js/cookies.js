jQuery(() => {
	set_cookies();
});

const set_cookies = () => {
	set_channel();
	set_landing_path();
	set_landing_domain();
	set_device();
}

const set_device = () => {
	if(getCookie('device') == '')
	{
		var device = 'Desktop';
		
		if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))
		{
			device = 'Mobile';
		}
		
		setCookie('device', device, 30);
		
		if(getCookie('device') == '')
		{
			setCookie('device', 'undefined', 30);
		}				
	}
	$('input.device').each(function(){
		$(this).val(getCookie('device'));
	});	
}

const set_landing_domain = () => {
	if(getCookie('landing_domain') == '')
	{
		setCookie('landing_domain', window.location.hostname, 30);
		
		if(getCookie('landing_domain') == '')
		{
			setCookie('landing_domain', 'undefined', 30);
		}		
	}
	$('input.landing_domain').each(function(){
		$(this).val(getCookie('landing_domain'));
	});		
}

const set_landing_path = () => {
	if(getCookie('landing_path') == '')
	{
		setCookie('landing_path', window.location.pathname, 30);
		
		if(getCookie('landing_path') == '')
		{
			setCookie('landing_path', 'undefined', 30);
		}	
	}
	$('input.landing_path').each(function(){
		$(this).val(getCookie('landing_path'));
	});		
}

const set_channel = () => {
	if(getCookie('channel') == '')
	{
		var channel = 'Organic';
		var google = /google/i;
		var bing = /bing/i;
		var yahoo = /yahoo/i;
		var yandex = /yandex/i;
		var baidu = /baidu/i;
		var instagram = /instagram/i;
		var twitter = /twitter/i;
		var facebook = /facebook/i;
			
		if(document.referrer.match(google))
		{
			if(window.location.href.indexOf('gclid') > -1)
			{
				channel = 'Google Ads';
			}
			else
			{
				channel = 'Google';
			}
		}
		else if(document.referrer.match(bing))
		{
			if(window.location.href.indexOf('msclkid') > -1)
			{
				channel = 'Bing Ads';
			}
			else
			{
				channel = 'Bing';
			}
		}
		else if(document.referrer.match(yahoo))
		{
			channel = 'Yahoo';
		}		
		else if(document.referrer.match(baidu))
		{
			channel = 'Baidu';
		}
		else if(document.referrer.match(yandex))
		{
			channel = 'Yandex';
		}				
		else if(document.referrer.match(instagram))
		{
			channel = 'Instagram';
		}
		else if(document.referrer.match(twitter))
		{
			channel = 'Twitter';
		}	
		else if(document.referrer.match(facebook))
		{
			channel = 'Facebook';
		}
		setCookie('channel', channel, 30);
	}
	
	
	$('input.channel').each(function(){
		$(this).val(getCookie('channel'));
	});	
}

const setCookie = (cname, cvalue, exdays) => {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+ d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

const getCookie = (cname) => {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return '';
}

const getUrlParameter = (sParam) => {
	const sPageURL = decodeURIComponent(window.location.search.substring(1));
	const sURLVariables = sPageURL.split('&');
	let sParameterName = null;

	for (let i = 0; i < sURLVariables.length; i++) {
		sParameterName = sURLVariables[i].split('=');

		if (sParameterName[0] === sParam) {
			return sParameterName[1] === undefined ? true : sParameterName[1];
		}
	}
}	
