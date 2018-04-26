(function (d, w) {
    w.AMOPRO = w.AMOPRO || {};
    AMOPRO.fields = w.AMOPRO.fields || {};

    var visitor_uid = null,
        utm = JSON.parse(localStorage['utm'] || '{}'),
        $_GET = parseGETParameters(),
        forms = d.getElementsByTagName('form');

    if(typeof AMOPRO.amo_account !== 'undefined'){
        AMOPRO.fields['amo_account'] = AMOPRO.amo_account;
    }
    for (k in $_GET) {
        if (k.indexOf('utm_') === 0) {
            utm[k] = $_GET[k];
        }
    }
    localStorage.setItem('utm', JSON.stringify(utm));

    for(k in utm){
        if(utm.hasOwnProperty(k)){
            AMOPRO.fields[k] = utm[k];
        }
    }

    AMOPRO.init = function(){

        if (visitor_uid === null) {
            addScript(
                'https://piper.amocrm.ru/pixel/js/identifier/pixel_identifier.js',
                'amo_pixel_identifier_js',
                function () {
                    var amopx = setTimeout(function tick() {
                        visitor_uid = w.AMOPIXEL_IDENTIFIER.getVisitorUid();
                        if (visitor_uid) {
                            AMOPRO.fields['amo_visitor_uid'] = visitor_uid;
                            for (var i = 0; i < forms.length; i++) {
                                var field = forms[i].querySelector('[name="amo_visitor_uid"]');
                                if (field === null) {
                                    forms[i].appendChild(hiddenField('amo_visitor_uid', visitor_uid));
                                } else {
                                    field.value = visitor_uid;
                                }
                            }
                        } else {
                            amopx = setTimeout(tick, 50);
                            return false;
                        }
                    }, 80);
                });
        } else {
            AMOPRO.fields['amo_visitor_uid'] = visitor_uid;
        }
        for (var i = 0; i < forms.length; i++) {
            for(name in AMOPRO.fields){
                if (AMOPRO.fields.hasOwnProperty(name)) {
                    var field = forms[i].querySelector('[name="' + name + '"]');
                    if (field === null) {
                        forms[i].appendChild(hiddenField(name, AMOPRO.fields[name]));
                    } else {
                        field.value = AMOPRO.fields[name];
                    }
                }
            }
        }
    };

    AMOPRO.init();

    /**
     * Загружает скрипт асинхронно
     * @param src
     * @param callback
     */
    function addScript(src, id, callback) {
        if ( typeof id !== "string" ) {
            callback = id;
            id = undefined;
        }
        var n = d.getElementsByTagName("script")[0],
            s = d.createElement('script');
        s.src = src;
        s.type = "text/javascript";
        s.async = true;
        if(id){ s.id = id; }

        n.parentNode.insertBefore(s, n);

        var loaded = false;

        function onload() {
            if (loaded) return; // повторный вызов
            loaded = true;
            if (typeof callback === 'function') {
                callback();
            }
        }

        s.onload = onload; // все браузеры, IE с версии 9

        s.onreadystatechange = function () { // IE8-
            if (this.readyState === 'loaded' || this.readyState === 'complete') {
                setTimeout(onload, 0);
            }
        };
    }
    /**
     * Возвращает скрытое поле для формы
     * @param {string} name  атрибут поля
     * @param {string} value атрибут поля
     */
    function hiddenField(name, value) {
        var f = d.createElement('input');
        f.type = 'hidden';
        f.name = name;
        f.value = value;
        f.id = 'f_'+name;
        return f;
    }

    /**
     * Распарсить GET-параметры из адресной строки.
     * @return {object} GET-параметры в виде объекта.
     */
    function parseGETParameters() {
        var result = {},
            gets = decodeURIComponent(window.location.search.replace(/&amp;/g, '&').substring(1))
                .split('&');
        for (var i = 0; i < gets.length; i++) {
            var get = gets[i].split('=');
            result[get[0]] = get[1] === undefined ? '' : get[1];
        }
        return result;
    }

})(document, window);