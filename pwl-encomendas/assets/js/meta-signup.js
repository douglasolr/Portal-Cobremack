(function () {
    'use strict';

    var config = window.PWLWhatsAppSignup || {};
    var signupEventSeen = false;
    var loginCallbackSeen = false;

    function report(message) {
        var output = document.getElementById('pwl-encomendas-meta-output');
        if (output) output.textContent = message;
    }

    function enableButton() {
        var button = document.getElementById('pwl-encomendas-start-meta-signup');
        if (button && window.FB && config.appId && config.configId) button.disabled = false;
    }

    function saveEvent(eventData) {
        var form = new URLSearchParams();
        form.append('action', 'pwl_encomendas_meta_signup_event');
        form.append('nonce', config.nonce || '');
        form.append('event', JSON.stringify(eventData));
        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: form.toString() })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (response && response.success && response.data && response.data.message) report(response.data.message);
                else report('O navegador recebeu um retorno da Meta, mas o WordPress não conseguiu registrá-lo. Confira sua sessão de administrador.');
            })
            .catch(function () { report('O fluxo retornou dados, mas houve falha ao comunicar com o WordPress.'); });
    }

    window.addEventListener('message', function (event) {
        var host = '';
        try { host = new URL(event.origin).hostname; } catch (ignoreOrigin) { return; }
        if (host !== 'facebook.com' && !host.endsWith('.facebook.com')) return;
        var payload = event.data;
        if (typeof payload === 'string') {
            try { payload = JSON.parse(payload); } catch (ignore) { return; }
        }
        if (!payload || payload.type !== 'WA_EMBEDDED_SIGNUP') return;
        signupEventSeen = true;
        saveEvent({ event: 'WA_EMBEDDED_SIGNUP', signup_event: payload.event || 'unknown', data_keys: Object.keys(payload.data || {}) });
    });

    window.fbAsyncInit = function () {
        if (!window.FB) return;
        window.FB.init({ appId: config.appId, cookie: true, xfbml: false, version: 'v25.0' });
        enableButton();
    };

    document.addEventListener('pwl:encomendas:tab-loaded', enableButton);

    if (config.appId) {
        var sdk = document.createElement('script');
        sdk.async = true;
        sdk.defer = true;
        sdk.crossOrigin = 'anonymous';
        sdk.src = 'https://connect.facebook.net/en_US/sdk.js';
        document.head.appendChild(sdk);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('#pwl-encomendas-start-meta-signup');
        if (button) {
            event.preventDefault();
            if (!window.FB || !config.appId || !config.configId) {
                report('O SDK da Meta ou os identificadores ainda não estão disponíveis.');
                return;
            }
            signupEventSeen = false;
            loginCallbackSeen = false;
            report('Abrindo o cadastro hospedado pela Meta. Não conclua se o fluxo indicar migração ou substituição do número principal.');
            window.FB.login(function (response) {
                loginCallbackSeen = true;
                var result = { event: 'FB_LOGIN_CALLBACK', data_keys: [], code_received: !!(response && response.authResponse && response.authResponse.code), status: response && response.status ? response.status : 'unknown' };
                if (result.code_received && !signupEventSeen) result.signup_event = 'missing';
                saveEvent(result);
            }, {
                config_id: config.configId,
                response_type: 'code',
                override_default_response_type: true,
                extras: { setup: {}, featureType: 'whatsapp_business_app_onboarding' }
            });
            window.setTimeout(function () {
                var output = document.getElementById('pwl-encomendas-meta-output');
                if (loginCallbackSeen && !signupEventSeen && output && output.textContent.indexOf('A Meta autorizou o login') !== -1) {
                    report('A Meta autorizou o login, mas não enviou o evento WA_EMBEDDED_SIGNUP. O cadastro incorporado não foi iniciado ou a configuração informada não está habilitando esse produto. Confira no Meta Developers se o Configuration ID salvo pertence à variação “WhatsApp Embedded Signup” e ao onboarding do WhatsApp Business App.');
                }
            }, 2500);
        }
    });

    enableButton();
}());
