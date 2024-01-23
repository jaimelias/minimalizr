
//jquery slim fixes


if (typeof jQuery === 'function' && typeof jQuery.fn.init === 'function') {

    if(typeof jQuery().animate === 'undefined')
    {
        jQuery.fn.extend({
            animate: function(o) {
                if(!o)
                {
                    return false;
                }

                const {scrollTop} = o;

                if(scrollTop)
                {
                    window.scrollTo(0, 0);
                }
            },
        });
    }
}

//beaver builder fix scroll top

const $ = jQuery;

