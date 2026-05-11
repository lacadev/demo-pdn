(function ($) {
    'use strict';

    if (!window.lacaHelpPasteImage || !window.lacaHelpPasteImage.ajaxUrl) {
        return;
    }

    var SELECTOR_TEXTAREA = 'textarea.wp-editor-area';
    var uploading = false;
    var badgeEl = null;

    function ensureBadge() {
        if (badgeEl && document.body.contains(badgeEl)) {
            return badgeEl;
        }

        badgeEl = document.createElement('div');
        badgeEl.id = 'laca-help-paste-badge';
        badgeEl.setAttribute('role', 'status');
        badgeEl.style.position = 'fixed';
        badgeEl.style.right = '20px';
        badgeEl.style.bottom = '20px';
        badgeEl.style.zIndex = '999999';
        badgeEl.style.padding = '10px 14px';
        badgeEl.style.borderRadius = '10px';
        badgeEl.style.fontSize = '13px';
        badgeEl.style.fontWeight = '600';
        badgeEl.style.boxShadow = '0 6px 18px rgba(0,0,0,0.15)';
        badgeEl.style.display = 'none';
        badgeEl.style.transition = 'opacity .2s ease';
        badgeEl.style.opacity = '0';
        document.body.appendChild(badgeEl);

        return badgeEl;
    }

    function showBadge(message, type, autoHideMs) {
        var el = ensureBadge();
        var bg = '#2271b1';
        var color = '#ffffff';

        if (type === 'success') {
            bg = '#1f9d55';
        } else if (type === 'error') {
            bg = '#d63638';
        } else if (type === 'loading') {
            bg = '#3858e9';
        }

        el.textContent = message;
        el.style.background = bg;
        el.style.color = color;
        el.style.display = 'block';

        requestAnimationFrame(function () {
            el.style.opacity = '1';
        });

        if (autoHideMs && autoHideMs > 0) {
            window.setTimeout(function () {
                el.style.opacity = '0';
                window.setTimeout(function () {
                    el.style.display = 'none';
                }, 180);
            }, autoHideMs);
        }
    }

    function getClipboardData(event) {
        if (!event) {
            return null;
        }
        if (event.clipboardData) {
            return event.clipboardData;
        }
        if (event.originalEvent && event.originalEvent.clipboardData) {
            return event.originalEvent.clipboardData;
        }
        return null;
    }

    function getImageFileFromClipboard(event) {
        var cd = getClipboardData(event);
        if (!cd) {
            return null;
        }

        var i;
        if (cd.items && cd.items.length) {
            for (i = 0; i < cd.items.length; i++) {
                var item = cd.items[i];
                if (item.kind === 'file' && item.type && item.type.indexOf('image/') === 0) {
                    var f = item.getAsFile();
                    if (f) {
                        return f;
                    }
                }
                if (item.type && item.type.indexOf('image/') === 0) {
                    f = item.getAsFile();
                    if (f) {
                        return f;
                    }
                }
            }
        }

        if (cd.files && cd.files.length) {
            for (i = 0; i < cd.files.length; i++) {
                if (cd.files[i].type && cd.files[i].type.indexOf('image/') === 0) {
                    return cd.files[i];
                }
            }
        }

        return null;
    }

    function hasImageInClipboard(event) {
        return getImageFileFromClipboard(event) !== null;
    }

    function uploadClipboardImage(file) {
        var formData = new FormData();
        formData.append('action', 'laca_help_paste_image');
        formData.append('nonce', window.lacaHelpPasteImage.nonce);
        formData.append('image', file, file.name || 'clipboard-image.png');

        return $.ajax({
            url: window.lacaHelpPasteImage.ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        });
    }

    function escapeAttr(url) {
        return String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function imageHtml(url) {
        return '<p><img src="' + escapeAttr(url) + '" alt="" /></p>';
    }

    function insertAtCursorTextarea(textarea, html) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var value = textarea.value || '';
        textarea.value = value.substring(0, start) + html + value.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + html.length;
        $(textarea).trigger('change');
    }

    function insertInTinyMce(editor, html) {
        if (!editor) {
            return;
        }
        editor.focus();
        editor.insertContent(html);
        editor.save();
        if (typeof editor.fire === 'function') {
            editor.fire('change');
        }
    }

    function runUpload(file, onDoneHtml) {
        if (!file || uploading) {
            return;
        }
        uploading = true;
        showBadge('\u0110ang t\u1ea3i \u1ea3nh l\xean...', 'loading');

        uploadClipboardImage(file)
            .done(function (response) {
                if (!response || !response.success || !response.data || !response.data.url) {
                    showBadge('T\u1ea3i \u1ea3nh th\u1ea5t b\u1ea1i.', 'error', 3500);
                    return;
                }
                onDoneHtml(imageHtml(response.data.url));
                showBadge('\u0110\xe3 ch\xe8n \u1ea3nh.', 'success', 2000);
            })
            .fail(function (xhr) {
                var msg = window.lacaHelpPasteImage.i18n.uploadFail;
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                window.alert(msg);
                showBadge('T\u1ea3i \u1ea3nh th\u1ea5t b\u1ea1i.', 'error', 3500);
            })
            .always(function () {
                uploading = false;
            });
    }

    function onPasteInTextarea(event) {
        if (!hasImageInClipboard(event)) {
            return;
        }

        var file = getImageFileFromClipboard(event);
        if (!file) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        var target = event.target;
        runUpload(file, function (html) {
            insertAtCursorTextarea(target, html);
        });
    }

    function bindIframePaste(editor) {
        if (!editor || editor.__lacaIframePasteBound) {
            return;
        }

        editor.on('init', function () {
            var doc = editor.getDoc();
            if (!doc || doc.__lacaPasteCapture) {
                return;
            }
            doc.__lacaPasteCapture = true;

            doc.addEventListener(
                'paste',
                function (nativeEv) {
                    if (!hasImageInClipboard(nativeEv)) {
                        return;
                    }
                    var file = getImageFileFromClipboard(nativeEv);
                    if (!file) {
                        return;
                    }
                    nativeEv.preventDefault();
                    nativeEv.stopPropagation();
                    runUpload(file, function (html) {
                        insertInTinyMce(editor, html);
                    });
                },
                true
            );
        });

        editor.__lacaIframePasteBound = true;
    }

    function bindEditorPasteEvent(editor) {
        if (!editor || editor.__lacaEditorPasteBound) {
            return;
        }

        editor.on('paste', function (e) {
            var nativeEv = e;
            if (e && typeof e.getNative === 'function') {
                nativeEv = e.getNative();
            }
            if (!hasImageInClipboard(nativeEv)) {
                return;
            }
            var file = getImageFileFromClipboard(nativeEv);
            if (!file) {
                return;
            }
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            if (nativeEv && typeof nativeEv.preventDefault === 'function') {
                nativeEv.preventDefault();
            }
            runUpload(file, function (html) {
                insertInTinyMce(editor, html);
            });
        });

        editor.__lacaEditorPasteBound = true;
    }

    function bindOneEditor(editor) {
        if (!editor) {
            return;
        }
        bindIframePaste(editor);
        bindEditorPasteEvent(editor);
    }

    function bindAllTinyMceEditors() {
        if (!window.tinymce) {
            return;
        }
        if (typeof window.tinymce.each === 'function') {
            window.tinymce.each(window.tinymce.editors, bindOneEditor);
            return;
        }
        var eds = window.tinymce.editors;
        if (!eds) {
            return;
        }
        if (Array.isArray(eds)) {
            eds.forEach(bindOneEditor);
            return;
        }
        Object.keys(eds).forEach(function (k) {
            bindOneEditor(eds[k]);
        });
    }

    $(document).on('paste', SELECTOR_TEXTAREA, onPasteInTextarea);

    $(document).on('tinymce-editor-init', function (event, editor) {
        bindOneEditor(editor);
    });

    $(function () {
        bindAllTinyMceEditors();
        var tries = 0;
        var timer = window.setInterval(function () {
            tries += 1;
            bindAllTinyMceEditors();
            if (tries >= 25) {
                window.clearInterval(timer);
            }
        }, 400);
    });
})(jQuery);
