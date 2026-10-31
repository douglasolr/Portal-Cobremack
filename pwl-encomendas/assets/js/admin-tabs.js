(function () {
    'use strict';

    var root = document.querySelector('.pwl-ui');
    if (!root || !window.fetch || !window.DOMParser || !window.history || !window.history.pushState) return;

    var cache = new Map();
    var currentUrl = window.location.href;
    cache.set(currentUrl, Promise.resolve(root.innerHTML));

    function readPage(url) {
        if (cache.has(url)) return cache.get(url);
        var request = fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) throw new Error('A aba não pôde ser carregada.');
                return response.text();
            })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var pageRoot = parsed.querySelector('.pwl-ui');
                if (!pageRoot) throw new Error('Conteúdo da aba não encontrado.');
                return pageRoot.innerHTML;
            })
            .catch(function (error) {
                cache.delete(url);
                throw error;
            });
        cache.set(url, request);
        return request;
    }

    function preloadTabs() {
        Array.prototype.forEach.call(root.querySelectorAll('a[data-pwl-encomendas-tab]'), function (link) {
            var url = new URL(link.href, window.location.href);
            if (url.origin === window.location.origin && url.href !== currentUrl) readPage(url.href).catch(function () {});
        });
    }

    function activate(url, updateHistory) {
        readPage(url).then(function (markup) {
            root.innerHTML = markup;
            currentUrl = url;
            if (updateHistory) window.history.pushState({ pwlEncomendasTab: true }, '', url);
            document.dispatchEvent(new Event('pwl:encomendas:tab-loaded'));
            preloadTabs();
        }).catch(function () {
            window.location.href = url;
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-pwl-encomendas-tab]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        event.preventDefault();
        if (url.href !== currentUrl) activate(url.href, true);
    });

    window.addEventListener('popstate', function () {
        var url = window.location.href;
        if (url !== currentUrl) activate(url, false);
    });

    preloadTabs();
}());
