if (typeof sessionStorage === 'undefined' || sessionStorage === null)
{
    class SessionStorageFallback {

        constructor(){
            this.defaultExpirationInDays = 1;
        }
    
        setItem(key, value){
            setCookie(key, value, this.defaultExpirationInDays);
        };
        
        getItem(key){
            return getCookie(key);
        };
        
        removeItem(key){
            setCookie(key, '', -1);
        };
        
        getKey(index){
    
            const cookies = document.cookie.split(';');
            if (index >= 0 && index < cookies.length) {
                const [cookieKey] = cookies[index].split('=');
                return cookieKey.trim();
            }
            return null;
        };
        
        clear(){
            const cookies = document.cookie.split(';');
            for (const cookie of cookies) {
                const [cookieKey] = cookie.split('=');
                setCookie(cookieKey, '', -1);
            }
        };
    }

    const sessionStorage = new SessionStorageFallback();
}