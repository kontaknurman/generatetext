(function ($) {
    'use strict';

    var AJAX_URL = generatetextData.ajaxUrl;
    var NONCE = generatetextData.nonce;

    function apiPost(action, data, callback) {
        data.action = action;
        data.nonce = NONCE;

        $.post(AJAX_URL, data, function (response) {
            if (response.success) {
                callback(null, response.data);
            } else {
                callback(response.data?.message || 'Request failed');
            }
        }).fail(function () {
            callback('Network error');
        });
    }

    function getEditorContent() {
        // Try the main content TinyMCE editor specifically (Visual mode)
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('content');
            if (editor && !editor.isHidden()) {
                var text = editor.getContent({ format: 'text' });
                if (text && text.trim()) {
                    return text;
                }
            }
        }
        // Fallback to textarea (Text mode, or TinyMCE not ready)
        var raw = $('#content').val() || '';
        // Strip HTML tags for plain text
        var tmp = document.createElement('div');
        tmp.innerHTML = raw;
        return tmp.textContent || tmp.innerText || '';
    }

    function showNotice(msg, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var $notice = $('<div class="notice ' + cls + ' is-dismissible generatetext-notice"><p>' + $('<span>').text(msg).html() + '</p></div>');
        $('#generatetext-metabox .inside').prepend($notice);
        setTimeout(function () { $notice.fadeOut(function () { $notice.remove(); }); }, 5000);
    }

    // Add meta box via JS (for classic editor)
    $(document).ready(function () {
        var features = (generatetextData && generatetextData.features) ? generatetextData.features : {};

        var buttons = '';
        if (features.tags !== false) {
            buttons += '<button type="button" class="button generatetext-btn" id="gt-gen-tags">Generate Tags</button> ';
        }
        if (features.category !== false) {
            buttons += '<button type="button" class="button generatetext-btn" id="gt-suggest-cat">Suggest Category</button> ';
        }
        if (features.title !== false) {
            buttons += '<button type="button" class="button generatetext-btn" id="gt-gen-title">Generate Title</button>';
        }

        if (!buttons) {
            return; // All features disabled, don't render metabox
        }

        var $metabox = $(
            '<div id="generatetext-metabox" class="postbox">' +
            '<h2 class="hndle"><span>GenerateText AI</span></h2>' +
            '<div class="inside">' +
            '<p class="generatetext-description">AI-powered content tools</p>' +
            buttons +
            '</div></div>'
        );

        $('#side-sortables').prepend($metabox);

        // Generate Tags
        $('#gt-gen-tags').on('click', function () {
            var $btn = $(this);
            var content = getEditorContent();
            if (!content) { showNotice('Please write some content first.', 'error'); return; }

            $btn.prop('disabled', true).text('Generating...');
            var postId = $('#post_ID').val() || 0;

            apiPost('generatetext_tags', { content: content, post_id: postId }, function (err, data) {
                $btn.prop('disabled', false).text('Generate Tags');
                if (err) { showNotice(err, 'error'); return; }

                // Update tag input
                if (data.tags && data.tags.length) {
                    var tagInput = $('#new-tag-post_tag');
                    if (tagInput.length) {
                        tagInput.val(data.tags.join(', '));
                        $('.tagadd').trigger('click');
                    }
                    showNotice('Tags generated: ' + data.tags.join(', '));
                }
            });
        });

        // Suggest Category
        $('#gt-suggest-cat').on('click', function () {
            var $btn = $(this);
            var content = getEditorContent();
            if (!content) { showNotice('Please write some content first.', 'error'); return; }

            $btn.prop('disabled', true).text('Analyzing...');

            apiPost('generatetext_categories', { content: content }, function (err, data) {
                $btn.prop('disabled', false).text('Suggest Category');
                if (err) { showNotice(err, 'error'); return; }

                if (data.categories && data.categories.length) {
                    // Uncheck all category checkboxes (both main list and popular list)
                    $('#categorychecklist input[type=checkbox], #categorychecklist-pop input[type=checkbox]')
                        .prop('checked', false);

                    // Check suggested categories by term_id
                    data.categories.forEach(function (cat) {
                        var id = parseInt(cat.id, 10);
                        // Target by checkbox ID (most reliable) and by value attribute
                        $('#in-category-' + id + ', #in-popular-category-' + id)
                            .prop('checked', true);
                        $('input[name="post_category[]"][value="' + id + '"]')
                            .prop('checked', true);
                    });

                    var names = data.categories.map(function (c) { return c.name; });
                    showNotice('Categories set: ' + names.join(', '));
                }
            });
        });

        // Generate Title
        $('#gt-gen-title').on('click', function () {
            var $btn = $(this);
            var content = getEditorContent();
            if (!content) { showNotice('Please write some content first.', 'error'); return; }

            $btn.prop('disabled', true).text('Generating...');

            apiPost('generatetext_title', { content: content }, function (err, data) {
                $btn.prop('disabled', false).text('Generate Title');
                if (err) { showNotice(err, 'error'); return; }

                if (data.title) {
                    $('#title').val(data.title).trigger('change');
                    showNotice('Title generated!');
                }
            });
        });

        // TinyMCE rewrite button is registered via PHP filters (mce_buttons + mce_external_plugins)
        // See generatetext-tinymce.js
    });
})(jQuery);
