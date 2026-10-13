/*
 * Email protection (Settings → General → "Protect email addresses"):
 * reassembles in the browser the addresses that App\Support\EmailProtector
 * hid in the HTML.
 */
(function () {
    function decode(value) {
        try {
            var binary = atob(value);
            var bytes = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            return Array.from(new TextDecoder().decode(bytes)).reverse().join('');
        } catch (e) {
            return null;
        }
    }

    function reveal(root) {
        root.querySelectorAll('a[data-cms-email]').forEach(function (link) {
            var address = decode(link.getAttribute('data-cms-email'));
            if (address) {
                link.setAttribute('href', 'mailto:' + address);
                link.removeAttribute('data-cms-email');
            }
        });
        root.querySelectorAll('span.cms-at').forEach(function (span) {
            span.replaceWith('@');
        });
        root.querySelectorAll('[data-cms-at]').forEach(function (element) {
            element.getAttribute('data-cms-at').split(' ').forEach(function (name) {
                var value = element.getAttribute(name);
                if (value !== null) {
                    element.setAttribute(name, value.split('(at)').join('@'));
                }
            });
            element.removeAttribute('data-cms-at');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { reveal(document); });
    } else {
        reveal(document);
    }
})();
