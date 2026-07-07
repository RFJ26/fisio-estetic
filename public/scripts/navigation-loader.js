(function () {
    'use strict';

    var loader = document.createElement('div');
    loader.id = 'page-loader';
    document.documentElement.appendChild(loader);

    function showLoader() {
        loader.classList.add('active');
        document.body.classList.add('page-loading');
    }

    function hideLoader() {
        loader.classList.remove('active');
        document.body.classList.remove('page-loading');
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (!link || !link.href) {
            return;
        }

        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
            return;
        }

        try {
            var url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) {
                return;
            }
        } catch (error) {
            return;
        }

        showLoader();
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form && form.tagName === 'FORM' && !form.hasAttribute('data-no-loader')) {
            showLoader();
        }
    });

    window.addEventListener('pageshow', hideLoader);
    window.addEventListener('load', hideLoader);
})();
