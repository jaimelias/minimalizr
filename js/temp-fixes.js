
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

                if(typeof scrollTop === 'number')
                {
                    window.scrollTo(0, scrollTop);
                }
            },
        });
    }
}

//fix scroll top on jquery slim

const $ = jQuery;
